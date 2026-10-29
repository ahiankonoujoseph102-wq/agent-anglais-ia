<?php

namespace App\Support\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\TeacherVoice;
use App\Support\Gemini\VoiceSampler;
use App\Support\PrivateStorage;
use App\Support\Speech\Loudness;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\Teachers\Teacher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * Voix d'une vidéo de leçon : quelques demandes de synthèse vocale (plusieurs parties du script par demande,
 * recoupées ensuite par VoiceSplitter ; une par phrase pour les vidéos de présentation), TOUTES chez le même fournisseur
 * et avec la même voix (jamais deux voix dans une vidéo). Si ce fournisseur ne peut pas finir (limite du jour,
 * clé refusée), la vidéo ENTIÈRE est refaite chez le suivant. Les parties sont ensuite assemblées en un seul
 * fichier WAV, avec un court silence entre elles, et leurs heures de début et de fin (sous-titres) sont gardées.
 */
class VideoVoice
{
    public const DONE = 'done';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    /**
     * Fournisseurs des vidéos, dans l'ordre réglé (config videos.voice.providers), avec une clé.
     *
     * @return list<SpeechProvider>
     */
    public static function providers(?Teacher $teacher = null): array
    {
        $names = array_map('strtolower', (array) config('videos.voice.providers', []));

        // Voix choisie dans l'essai comparatif (voix de Teacher Joe) : son fournisseur passe en premier.
        if (self::isDefault($teacher) && ($chosen = VideoSettings::voice()) !== null) {
            array_unshift($names, $chosen['provider']);
        }

        $names = array_unique($names);

        return array_values(array_filter(
            array_map(fn (string $name) => SpeechProviders::find($name), $names),
            fn (?SpeechProvider $provider) => $provider !== null && $provider->hasKey(),
        ));
    }

    /**
     * Voix utilisée par ce fournisseur pour les vidéos du professeur (Teacher Joe par défaut, réglages d'avant). Un
     * autre professeur (Teacher Rose) garde SA voix : celle de ses séances chez Gemini, sa voix d'appel chez OpenAI.
     */
    public static function voiceFor(SpeechProvider $provider, ?Teacher $teacher = null): string
    {
        if (! self::isDefault($teacher)) {
            return match ($provider->name()) {
                'gemini' => (string) ($teacher->geminiVoice() ?: $provider->voice()),
                'openai' => $teacher->openaiSpeechVoice(),
                default => $provider->voice(),
            };
        }

        $chosen = VideoSettings::voice();

        if ($chosen !== null && $chosen['provider'] === $provider->name()) {
            return $chosen['voice'];
        }

        return match ($provider->name()) {
            'gemini' => (string) (config('videos.voice.gemini_voice') ?: TeacherVoice::current()),
            'openai' => (string) (config('videos.voice.openai_voice') ?: $provider->voice()),
            default => $provider->voice(),
        };
    }

    private static function isDefault(?Teacher $teacher): bool
    {
        return $teacher === null || $teacher->isDefault();
    }

    /** Ce fournisseur est-il mis de côté (limite atteinte) ? */
    public static function isPaused(SpeechProvider $provider): bool
    {
        return Cache::has(self::pauseKey($provider));
    }

    /**
     * Prépare la voix, jusqu'à l'heure limite (microtime). DONE : voix assemblée ; PENDING : à reprendre au passage
     * suivant ; FAILED : trop d'échecs.
     */
    public function prepare(LessonVideo $video, float $deadline): string
    {
        $providers = self::providers($video->teacher());

        if ($providers === []) {
            return $this->failure($video, 'Aucun fournisseur de voix n\'a de clé.');
        }

        $provider = collect($providers)->first(fn (SpeechProvider $p) => $p->name() === $video->voice_provider && ! self::isPaused($p))
            ?? collect($providers)->first(fn (SpeechProvider $p) => ! self::isPaused($p));

        if ($provider === null) {
            $video->update(['next_attempt_at' => now()->addMinutes((int) config('videos.retry_minutes')), 'error' => 'Fournisseurs de voix en pause (limite atteinte) : nouvel essai plus tard.']);

            return self::PENDING;
        }

        if ($video->voice_provider !== $provider->name()) {
            $video->update(['voice_provider' => $provider->name(), 'voice_name' => self::voiceFor($provider, $video->teacher())]);
        }

        $disk = PrivateStorage::disk();
        $segments = $video->script['segments'] ?? [];
        $timeout = (int) config('videos.voice.timeout');
        $requested = false;

        $texts = array_map(fn (array $segment) => (string) $segment['spoken'], array_values($segments));
        // Vidéos de leçon et du Club : plusieurs parties par demande (10 demandes par jour chez Gemini en offre
        // gratuite), recoupées ensuite ; vidéos de présentation : une demande par phrase (leurs écrans en dépendent).
        $groups = $video instanceof PresentationVideo
            ? array_map(fn (int $index) => [$index], array_keys($texts))
            : VoiceSplitter::groups($texts, (int) config('videos.voice.group_characters'));

        foreach ($groups as $group) {
            if (collect($group)->every(fn (int $index) => $disk->exists($this->piecePath($video, $provider, $index)))) {
                continue;
            }

            // Pause entre deux demandes (limites par minute des offres gratuites).
            $wait = $requested ? $provider->spacing() : 0;

            if (microtime(true) + $wait + min($timeout, 20) > $deadline) {
                return self::PENDING;
            }

            if ($wait > 0) {
                Sleep::for($wait)->seconds();
            }

            $requested = true;
            $groupTexts = array_map(fn (int $index) => $texts[$index], $group);

            try {
                $wav = $this->synthesize($provider, (string) ($video->voice_name ?: self::voiceFor($provider, $video->teacher())), VoiceSplitter::join($groupTexts), $video->voiceLabel(), $video->voiceStyle());
            } catch (SpeechFailed $e) {
                if ($e->exhaustsProvider() || $e->isRetryable()) {
                    // Limite : ce fournisseur est mis de côté ; la vidéo entière passera au suivant.
                    Cache::put(self::pauseKey($provider), $e->kind, $e->isRetryable() ? now()->addMinutes(2) : now()->endOfDay());
                    $video->update(['error' => $provider->label().' : '.$e->reason().'.']);

                    return self::PENDING;
                }

                return $this->failure($video, $provider->label().' : '.$e->reason().'.');
            }

            if (($parsed = self::parse($wav)) === null) {
                return $this->failure($video, $provider->label().' : audio illisible.');
            }

            foreach (VoiceSplitter::split($parsed, $groupTexts) as $position => $data) {
                $disk->put($this->piecePath($video, $provider, $group[$position]), self::wav($data, $parsed['rate'], $parsed['channels']));
            }
        }

        return $this->assemble($video, $provider, count($segments));
    }

    /**
     * Assemble les parties en un seul WAV (même format), avec un silence entre elles ; garde les heures de chaque partie.
     */
    private function assemble(LessonVideo $video, SpeechProvider $provider, int $count): string
    {
        $disk = PrivateStorage::disk();
        $pieces = [];

        for ($index = 0; $index < $count; $index++) {
            $wav = self::parse((string) $disk->get($this->piecePath($video, $provider, $index)));

            if ($wav === null || ($pieces !== [] && [$wav['rate'], $wav['channels']] !== [$pieces[0]['rate'], $pieces[0]['channels']])) {
                return $this->failure($video, 'Parties de la voix dans des formats différents.');
            }

            $pieces[] = $wav;
        }

        $format = $pieces[0];
        $frame = $format['channels'] * 2;
        $bytesPerSecond = $format['rate'] * $frame;
        $silence = fn (float $seconds) => str_repeat("\0", (int) round($bytesPerSecond * $seconds) - (int) round($bytesPerSecond * $seconds) % $frame);
        $segments = $video->script['segments'] ?? [];
        // Silence voulu après la phrase (« Réfléchissez… » d'une publicité, « Répétez après moi » du Club).
        $pauses = array_map(fn (int $index) => (int) ($segments[$index]['pause_after'] ?? 0) / 1000, array_keys($pieces));
        $natural = array_sum(array_map(fn (array $wav) => strlen($wav['data']) / $bytesPerSecond, $pieces))
            + ($count - 1) * $video->voiceGapMs() / 1000 + array_sum($pauses);
        $tail = 0.0;

        // Club : jamais moins de « min_seconds ». Une voix un peu courte reçoit des silences de répétition plus longs
        // (3 secondes de plus chacun au plus), puis un silence final ; plus courte encore : nouveau script.
        if ($video->isClub() && $natural < ($minimum = (int) config('videos.club.min_seconds'))) {
            $missing = $minimum - $natural + 0.5;

            if ($missing > (float) config('videos.club.max_pad_seconds')) {
                $disk->deleteDirectory($video->folder().'/voice');
                $attempts = $video->attempts + 1;
                $video->update([
                    'status' => $attempts >= (int) config('videos.max_attempts') ? LessonVideo::FAILED : LessonVideo::PLANNED,
                    'script' => null,
                    'attempts' => $attempts,
                    'error' => 'Voix trop courte ('.(int) round($natural).' secondes, '.$minimum.' au moins) : nouveau script.',
                    'next_attempt_at' => null,
                ]);

                return self::FAILED;
            }

            $repeats = array_keys(array_filter($pauses, fn (float $pause) => $pause > 0));
            $each = $repeats === [] ? 0.0 : min(3.0, $missing / count($repeats));

            foreach ($repeats as $index) {
                $pauses[$index] += $each;
            }

            $tail = max(0.0, $missing - $each * count($repeats));
        }

        $data = '';
        $timings = [];

        foreach ($pieces as $index => $wav) {
            if ($index > 0) {
                $data .= $silence($video->voiceGapMs() / 1000);
            }

            $start = strlen($data) / $bytesPerSecond;
            $data .= $wav['data'].$silence($pauses[$index] + ($index === $count - 1 ? $tail : 0.0));
            $timings[] = ['start' => round($start, 3), 'end' => round(strlen($data) / $bytesPerSecond, 3)];
        }

        $duration = strlen($data) / $bytesPerSecond;

        $path = $video->folder().'/voice.wav';
        $disk->put($path, Loudness::normalize(self::wav($data, $format['rate'], $format['channels'])));
        $disk->deleteDirectory($video->folder().'/voice');

        $video->update([
            'status' => LessonVideo::VOICED,
            'audio_path' => $path,
            'timings' => ['segments' => $timings, 'duration' => round($duration, 3)],
            'duration_seconds' => (int) ceil($duration),
            'attempts' => 0,
            'next_attempt_at' => null,
            'error' => null,
        ]);

        return self::DONE;
    }

    public function failure(LessonVideo $video, string $error): string
    {
        $attempts = $video->attempts + 1;
        $failed = $attempts >= (int) config('videos.max_attempts');
        $video->update([
            'attempts' => $attempts,
            'error' => mb_substr($error, 0, 300),
            'next_attempt_at' => now()->addMinutes((int) config('videos.retry_minutes')),
            'status' => $failed ? LessonVideo::FAILED : $video->status,
        ]);

        return $failed ? self::FAILED : self::PENDING;
    }

    /**
     * « Refaire la vidéo » : garde le corps de la voix (même script, même voix) et refait seulement la fin (question
     * du jour, abonnement, adresse du site) chez le MÊME fournisseur avec la MÊME voix, puis réassemble. DONE aussi
     * quand la fin était déjà à jour.
     *
     * @param  array{spoken: string, subtitle: string}  $question
     */
    public function refreshEnding(LessonVideo $video, array $question, float $deadline): string
    {
        $script = $video->script ?? [];
        $script['question'] = $question;
        $ending = VideoEnding::segments($question);
        $body = VideoEnding::body($script);
        $disk = PrivateStorage::disk();

        if ($ending === null) {
            return self::PENDING;
        }

        if (VideoEnding::isCurrent($script) && $video->audio_path !== null && $disk->exists($video->audio_path)) {
            $video->update(['status' => LessonVideo::VOICED, 'script' => $script, 'attempts' => 0, 'error' => null, 'next_attempt_at' => null]);

            return self::DONE;
        }

        $provider = SpeechProviders::find($video->voice_provider);
        $voice = (string) $video->voice_name;
        $wav = $video->audio_path !== null && $disk->exists($video->audio_path) ? self::parse((string) $disk->get($video->audio_path)) : null;
        $bodyEnd = $video->timings['segments'][count($body) - 1]['end'] ?? null;

        if ($provider === null || ! $provider->hasKey() || $wav === null || $bodyEnd === null || $body === []) {
            return $this->failure($video, 'Impossible de refaire la fin avec la même voix (voix d\'origine ou fournisseur indisponible) : préparez une nouvelle vidéo.');
        }

        if (self::isPaused($provider)) {
            $video->update(['next_attempt_at' => now()->addMinutes((int) config('videos.retry_minutes')), 'error' => $provider->label().' en pause (limite atteinte) : nouvel essai plus tard.']);

            return self::PENDING;
        }

        $requested = false;

        foreach ($ending as $index => $segment) {
            $path = $video->folder().'/ending/'.md5($voice.'|'.$segment['spoken']).'.wav';

            if ($disk->exists($path)) {
                continue;
            }

            $wait = $requested ? $provider->spacing() : 0;

            if (microtime(true) + $wait + 20 > $deadline) {
                return self::PENDING;
            }

            if ($wait > 0) {
                Sleep::for($wait)->seconds();
            }

            $requested = true;

            try {
                $piece = $this->synthesize($provider, $voice, $segment['spoken'], 'Vidéo de leçon (fin)');
            } catch (SpeechFailed $e) {
                if ($e->exhaustsProvider() || $e->isRetryable()) {
                    Cache::put(self::pauseKey($provider), $e->kind, $e->isRetryable() ? now()->addMinutes(2) : now()->endOfDay());
                    $video->update(['error' => $provider->label().' : '.$e->reason().'.', 'next_attempt_at' => now()->addMinutes((int) config('videos.retry_minutes'))]);

                    return self::PENDING;
                }

                return $this->failure($video, $provider->label().' : '.$e->reason().'.');
            }

            if (self::parse($piece) === null) {
                return $this->failure($video, $provider->label().' : audio illisible.');
            }

            $disk->put($path, $piece);
        }

        // Corps gardé tel quel (jusqu'à la fin de sa dernière partie), puis la nouvelle fin.
        $bytesPerSecond = $wav['rate'] * $wav['channels'] * 2;
        $keep = (int) round($bodyEnd * $bytesPerSecond);
        $data = substr($wav['data'], 0, $keep - $keep % ($wav['channels'] * 2));
        $timings = array_slice($video->timings['segments'], 0, count($body));
        $gap = (int) round($bytesPerSecond * (int) config('videos.voice.gap_ms') / 1000);
        $gap -= $gap % ($wav['channels'] * 2);

        foreach ($ending as $segment) {
            $piece = self::parse((string) $disk->get($video->folder().'/ending/'.md5($voice.'|'.$segment['spoken']).'.wav'));

            if ($piece === null || $piece['rate'] !== $wav['rate'] || $piece['channels'] !== $wav['channels']) {
                return $this->failure($video, 'La nouvelle fin n\'a pas le même format que la voix d\'origine.');
            }

            $data .= str_repeat("\0", $gap);
            $start = strlen($data) / $bytesPerSecond;
            $data .= $piece['data'];
            $timings[] = ['start' => round($start, 3), 'end' => round(strlen($data) / $bytesPerSecond, 3)];
        }

        $duration = strlen($data) / $bytesPerSecond;
        $disk->put($video->audio_path, Loudness::normalize(self::wav($data, $wav['rate'], $wav['channels'])));
        $disk->deleteDirectory($video->folder().'/ending');
        $script['segments'] = array_merge($body, $ending);

        $video->update([
            'status' => LessonVideo::VOICED,
            'script' => $script,
            'timings' => ['segments' => $timings, 'duration' => round($duration, 3)],
            'duration_seconds' => (int) ceil($duration),
            'attempts' => 0,
            'next_attempt_at' => null,
            'error' => null,
        ]);

        return self::DONE;
    }

    /**
     * @throws SpeechFailed
     */
    public function synthesize(SpeechProvider $provider, string $voice, string $text, string $label, ?string $style = null): string
    {
        $style ??= (string) config('videos.voice.style');
        $timeout = (int) config('videos.voice.timeout');

        if ($provider->name() === 'gemini') {
            return app(VoiceSampler::class)->synthesize($voice, $text, $style, $label, $timeout);
        }

        // OpenAI lit sa voix dans la configuration : la voix des vidéos y est mise le temps de la demande.
        $previous = config('services.openai.tts_voice');
        config(['services.openai.tts_voice' => $voice]);

        try {
            return $provider->synthesize($text, $style, $label, $timeout);
        } finally {
            config(['services.openai.tts_voice' => $previous]);
        }
    }

    private function piecePath(LessonVideo $video, SpeechProvider $provider, int $index): string
    {
        return $video->folder().'/voice/'.$provider->name().'-'.$index.'.wav';
    }

    private static function pauseKey(SpeechProvider $provider): string
    {
        return 'videos.voice.paused.'.$provider->name();
    }

    /**
     * Lit un WAV PCM 16 bits : fréquence, canaux et échantillons ; null sinon.
     *
     * @return array{rate: int, channels: int, data: string}|null
     */
    public static function parse(string $wav): ?array
    {
        if (strlen($wav) < 44 || ! str_starts_with($wav, 'RIFF') || substr($wav, 8, 4) !== 'WAVE') {
            return null;
        }

        $position = 12;
        $format = null;

        while ($position + 8 <= strlen($wav)) {
            $id = substr($wav, $position, 4);
            $size = unpack('V', substr($wav, $position + 4, 4))[1];

            if ($id === 'fmt ') {
                $format = unpack('vformat/vchannels/Vrate/Vbytes/vblock/vbits', substr($wav, $position + 8, 16));
            } elseif ($id === 'data') {
                if ($format === null || $format['format'] !== 1 || $format['bits'] !== 16 || $format['channels'] < 1) {
                    return null;
                }

                $length = min($size, strlen($wav) - $position - 8);
                $length -= $length % ($format['channels'] * 2);

                return ['rate' => $format['rate'], 'channels' => $format['channels'], 'data' => substr($wav, $position + 8, $length)];
            }

            $position += 8 + $size + $size % 2;
        }

        return null;
    }

    /** En-tête WAV PCM 16 bits autour des échantillons. */
    public static function wav(string $data, int $rate, int $channels): string
    {
        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, $channels, $rate, $rate * $channels * 2, $channels * 2, 16)
            .'data'.pack('V', strlen($data)).$data;
    }
}
