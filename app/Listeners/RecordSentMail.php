<?php

namespace App\Listeners;

use App\Mail\AdminRecoveryMail;
use App\Mail\CourseReviewMail;
use App\Mail\FollowUpMail;
use App\Mail\LearnerInvitationMail;
use App\Mail\LessonReminderMail;
use App\Mail\PaymentAlertMail;
use App\Mail\SubscriptionEndedMail;
use App\Mail\VideoReadyMail;
use App\Models\MailJournal;
use App\Models\User;
use App\Support\Redact;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Note chaque e-mail réellement parti vers un élève (tous les envois du site passent par ici), pour le résumé
 * envoyé au fondateur (mail:digest). Jamais les e-mails de l'équipe (alertes, vidéos, récupération) ; jamais bloquant.
 */
class RecordSentMail
{
    /** E-mails destinés à l'équipe : jamais notés. */
    public const IGNORED = [PaymentAlertMail::class, VideoReadyMail::class, AdminRecoveryMail::class];

    public const KINDS = [
        FollowUpMail::class => 'Relance',
        LessonReminderMail::class => 'Rappel',
        SubscriptionEndedMail::class => 'Bilan de fin de formule',
        CourseReviewMail::class => 'Document de révision',
        LearnerInvitationMail::class => 'Invitation à refaire l\'évaluation',
    ];

    public function handle(MessageSent $event): void
    {
        try {
            $class = $event->data['__laravel_mailable'] ?? null;

            if ($class === null || in_array($class, self::IGNORED, true)) {
                return;
            }

            $kind = self::KINDS[$class] ?? class_basename($class);
            $label = $event->data['body']['label'] ?? null;

            if (is_string($label) && $label !== '') {
                $kind .= ' : '.$label;
            }

            $email = $event->sent->getOriginalMessage();
            $team = array_map('mb_strtolower', (array) config('alerts.emails'));

            foreach ($email->getTo() as $address) {
                $recipient = mb_strtolower($address->getAddress());

                if (in_array($recipient, $team, true)) {
                    continue;
                }

                MailJournal::create([
                    'user_id' => User::query()->where('email', $recipient)->value('id'),
                    'recipient' => mb_strimwidth($recipient, 0, 190),
                    'kind' => mb_strimwidth($kind, 0, 120, '…'),
                    'subject' => mb_strimwidth((string) $email->getSubject(), 0, 200, '…'),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Journal des e-mails : enregistrement impossible.', ['reason' => Redact::reason($e->getMessage())]);
        }
    }
}
