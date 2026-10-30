<?php

namespace App\Support\Videos;

use App\Mail\VideoReadyMail;
use App\Models\LessonVideo;
use App\Support\Alerts\Redact;
use App\Support\MailQuota;
use App\Support\PrivateStorage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Vidéos de promotion et du Club envoyées par e-mail au fondateur (config videos.mail.to), puis retirées du stockage.
 * Chaque vidéo prête part UNE fois, avec sa description et le texte à publier dans le corps du message, la version
 * légère « e-mail » en pièce jointe et un lien vers la pleine qualité tant qu'elle est gardée. Rien n'est effacé tant
 * que l'e-mail n'est pas parti ; un envoi raté est réessayé, puis « Renvoyer l'e-mail » dans la page « Vidéos ».
 */
class VideoMailer
{
    /**
     * Un passage (commande videos:mail) : envoie les vidéos prêtes, puis efface ce qui n'a plus à être gardé.
     *
     * @return array{sent: int, failed: int, purged: int, waiting: int}
     */
    public function run(): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'purged' => 0, 'waiting' => 0];

        // Envoi d'e-mails pas réglé (Brevo) : rien n'est envoyé ni effacé.
        if (! MailQuota::configured() || ! filled(self::recipient())) {
            $result['waiting'] = $this->pending()->count();

            return $result;
        }

        foreach ($this->pending()->limit(max(1, (int) config('videos.mail.per_pass')))->get() as $video) {
            if (! MailQuota::allowsVideo()) {
                break;
            }

            $this->send($video) ? $result['sent']++ : $result['failed']++;
        }

        $result['purged'] = $this->purgeExpired();
        $result['waiting'] = $this->pending()->count();

        return $result;
    }

    /** Adresse qui reçoit les vidéos. */
    public static function recipient(): string
    {
        return trim((string) config('videos.mail.to'));
    }

    /** Adresse masquée pour l'affichage (« am*****@gmail.com »). */
    public static function maskedRecipient(): string
    {
        $address = self::recipient();

        return preg_replace_callback('/^([^@]{2})([^@]*)@/', fn ($m) => $m[1].str_repeat('*', max(1, mb_strlen($m[2]))).'@', $address) ?? $address;
    }

    /**
     * Envoie l'e-mail d'une vidéo. Pièce jointe refusée (taille) : nouvel essai sans elle, avec le lien seulement.
     */
    public function send(LessonVideo $video): bool
    {
        $disk = PrivateStorage::disk();
        $attach = $video->mail_path !== null && $disk->exists($video->mail_path);
        $link = $this->link($video);
        $error = null;

        foreach ($attach ? [true, false] : [false] as $withAttachment) {
            // Sans pièce jointe ni lien, l'e-mail n'apporterait pas la vidéo : rien n'est envoyé.
            if (! $withAttachment && $link === null) {
                $error ??= 'Ni version e-mail ni lien vers la vidéo.';

                break;
            }

            try {
                Mail::to(self::recipient())->send(new VideoReadyMail($video, $withAttachment, $link, $this->keepUntil($video)));
                $video->update(['mail_status' => LessonVideo::MAIL_SENT, 'mailed_at' => now(), 'mail_error' => $withAttachment ? null : 'Envoyée sans pièce jointe (lien seulement).', 'next_mail_at' => null]);

                $this->afterSent($video);

                return true;
            } catch (Throwable $e) {
                $error = Redact::reason($e->getMessage());
                Log::warning('Vidéo : e-mail au fondateur en échec.', ['video' => $video->id, 'attachment' => $withAttachment, 'error' => class_basename($e)]);
            }
        }

        $attempts = $video->mail_attempts + 1;
        $video->update([
            'mail_attempts' => $attempts,
            'mail_error' => mb_substr((string) $error, 0, 300),
            'mail_status' => $attempts >= (int) config('videos.mail.max_attempts') ? LessonVideo::MAIL_FAILED : null,
            'next_mail_at' => now()->addMinutes((int) config('videos.mail.retry_minutes')),
        ]);

        return false;
    }

    /** « Renvoyer l'e-mail » : la vidéo repart au prochain passage (seulement si ses fichiers sont encore là). */
    public function retry(LessonVideo $video): bool
    {
        if ($video->status !== LessonVideo::READY || $video->purged_at !== null || $video->video_path === null) {
            return false;
        }

        $video->update(['mail_status' => null, 'mail_attempts' => 0, 'mail_error' => null, 'next_mail_at' => null]);

        return true;
    }

    /** Jusqu'à quand la pleine qualité reste dans le stockage (null : effacée dès l'e-mail parti). */
    public function keepUntil(LessonVideo $video): ?CarbonInterface
    {
        $days = VideoSettings::keepDays();

        return $days > 0 ? ($video->mailed_at ?? now())->copy()->addDays($days) : null;
    }

    /** Lien signé vers la pleine qualité, valable tant qu'elle est gardée (7 jours au plus : limite du stockage). */
    private function link(LessonVideo $video): ?string
    {
        $until = $this->keepUntil($video);

        if ($until === null || $video->video_path === null) {
            return null;
        }

        try {
            return PrivateStorage::disk()->temporaryUrl($video->video_path, now()->addSeconds(min((int) now()->diffInSeconds($until), 7 * 86400 - 60)), [
                'ResponseContentType' => 'video/mp4',
                'ResponseContentDisposition' => 'attachment; filename="'.$video->downloadName().'"',
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /** E-mail parti : la version légère est effacée ; tout le reste aussi si rien n'est gardé. */
    private function afterSent(LessonVideo $video): void
    {
        if (VideoSettings::keepDays() === 0) {
            $this->purge($video);

            return;
        }

        if ($video->mail_path !== null) {
            PrivateStorage::disk()->delete($video->mail_path);
            $video->update(['mail_path' => null]);
        }
    }

    /** Vidéos envoyées dont la durée de garde est finie : effacées. */
    private function purgeExpired(): int
    {
        $limit = now()->subDays(VideoSettings::keepDays());
        $count = 0;

        LessonVideo::query()->where('mail_status', LessonVideo::MAIL_SENT)->whereNull('purged_at')->where('mailed_at', '<=', $limit)
            ->limit(20)->get()->each(function (LessonVideo $video) use (&$count) {
                $this->purge($video);
                $count++;
            });

        return $count;
    }

    /**
     * Efface tout le dossier de la vidéo (vidéo, version e-mail, voix). Le script, le texte à publier et les mesures
     * restent ; « Refaire la vidéo » prépare alors une nouvelle voix.
     */
    public function purge(LessonVideo $video): void
    {
        PrivateStorage::disk()->deleteDirectory($video->folder());
        $video->update(['video_path' => null, 'mail_path' => null, 'audio_path' => null, 'purged_at' => now()]);
    }

    /** @return Builder<LessonVideo> */
    private function pending()
    {
        return LessonVideo::query()->where('status', LessonVideo::READY)->whereNull('mail_status')->whereNull('purged_at')->whereNotNull('video_path')
            ->where(fn ($query) => $query->whereNull('next_mail_at')->orWhere('next_mail_at', '<=', now()))
            ->orderByRaw("case when kind = 'club' then 0 else 1 end")->orderBy('ready_at');
    }
}
