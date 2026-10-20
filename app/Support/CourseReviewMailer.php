<?php

namespace App\Support;

use App\Mail\CourseReviewMail;
use App\Models\CourseReview;
use App\Support\Alerts\AdminAlerts;
use App\Support\Alerts\AlertMessage;
use App\Support\Alerts\Redact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoie le document de révision prêt à l'élève qui a une adresse e-mail (PDF joint). Jamais bloquant : sans adresse,
 * envoi non réglé ou limite du jour atteinte, le document reste téléchargeable ; un échec est noté (raison courte,
 * jamais de clé ni d'adresse). Limite du jour : l'administrateur est prévenu une fois par jour.
 */
class CourseReviewMailer
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const QUOTA = 'quota';

    public const NO_EMAIL = 'no_email';

    public static function send(CourseReview $review): ?string
    {
        $review->loadMissing(['user', 'lesson', 'session']);

        if (! $review->isReady() || $review->mail_status !== null) {
            return $review->mail_status;
        }

        if (blank($review->user?->email)) {
            return self::mark($review, self::NO_EMAIL);
        }

        // Envoi non réglé (aucune valeur MAIL_*) : rien n'est noté, le document reste téléchargeable.
        if (! MailQuota::configured()) {
            return null;
        }

        if (! MailQuota::allowsReview()) {
            self::warnAdministrator();

            return self::mark($review, self::QUOTA);
        }

        try {
            Mail::to($review->user->email)->send(new CourseReviewMail($review));
        } catch (Throwable $e) {
            $reason = Redact::reason($e->getMessage());
            Log::warning('Document de révision : e-mail non envoyé.', ['review' => $review->id, 'reason' => $reason]);

            return self::mark($review, self::FAILED, $reason);
        }

        return self::mark($review, self::SENT);
    }

    private static function mark(CourseReview $review, string $status, ?string $error = null): string
    {
        $review->fill([
            'mail_status' => $status,
            'mail_error' => $error !== null ? mb_strimwidth($error, 0, 200, '…') : null,
            'mailed_at' => now(),
        ])->save();

        return $status;
    }

    /** Une seule alerte par jour quand la limite des documents est atteinte. */
    private static function warnAdministrator(): void
    {
        if (! Cache::add('course-review-mail-quota:'.today()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        $limit = (int) config('platform.courses.review.daily_emails');

        // Envoyée tout de suite : la préparation tourne déjà hors de toute requête de l'élève (planificateur).
        app(AdminAlerts::class)->send(new AlertMessage(
            kind: 'mail_quota',
            title: 'Limite des e-mails du jour atteinte',
            body: "Les {$limit} e-mails de documents de révision du jour sont partis : les suivants restent téléchargeables dans « Mes cours » et repartent demain par le téléchargement seulement.",
            url: route('admin.alerts.index'),
            buttonLabel: 'Voir les alertes',
            tag: 'limite-e-mails',
        ));
    }
}
