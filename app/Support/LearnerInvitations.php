<?php

namespace App\Support;

use App\Enums\Role;
use App\Mail\LearnerInvitationMail;
use App\Models\LearnerInvitation;
use App\Models\User;
use App\Support\Alerts\Redact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Invitations à refaire l'évaluation gratuite après une remise à zéro (app:reset-learners --notify). Mises en file
 * d'attente, puis envoyées chaque heure (learners:send-invitations) dans la marge du jour : celle des documents de
 * révision (platform.courses.review.daily_emails, 280), partagée avec eux ; les e-mails gardés pour les alertes
 * de l'administrateur ne sont jamais pris. Sans adresse e-mail : rien n'est envoyé (noté « sans adresse »).
 */
class LearnerInvitations
{
    /**
     * @param  list<int>  $userIds
     * @return array{queued: int, no_email: int}
     */
    public static function queue(array $userIds): array
    {
        $counts = ['queued' => 0, 'no_email' => 0];

        foreach (User::query()->whereIn('id', $userIds)->where('role', Role::Learner)->get(['id', 'email']) as $user) {
            $status = filled($user->email) ? LearnerInvitation::PENDING : LearnerInvitation::NO_EMAIL;
            LearnerInvitation::query()->updateOrCreate(['user_id' => $user->id], ['status' => $status, 'error' => null, 'sent_at' => null]);
            $counts[$status === LearnerInvitation::PENDING ? 'queued' : 'no_email']++;
        }

        return $counts;
    }

    /**
     * Envoie les invitations en attente, autant que la marge du jour le permet.
     *
     * @return array{sent: int, failed: int, remaining: int, configured: bool}
     */
    public static function sendDue(): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'remaining' => 0, 'configured' => MailQuota::configured()];

        if ($result['configured']) {
            foreach (LearnerInvitation::query()->where('status', LearnerInvitation::PENDING)->with('user')->orderBy('id')->lazyById(50) as $invitation) {
                if (! MailQuota::allowsInvitation()) {
                    break;
                }

                $result[self::send($invitation) === LearnerInvitation::SENT ? 'sent' : 'failed']++;
            }
        }

        $result['remaining'] = LearnerInvitation::query()->where('status', LearnerInvitation::PENDING)->count();

        return $result;
    }

    public static function send(LearnerInvitation $invitation): string
    {
        $email = $invitation->user?->email;

        if (blank($email)) {
            $invitation->update(['status' => LearnerInvitation::NO_EMAIL]);

            return LearnerInvitation::NO_EMAIL;
        }

        try {
            Mail::to($email)->send(new LearnerInvitationMail($invitation->user));
        } catch (Throwable $e) {
            $reason = Redact::reason($e->getMessage());
            Log::warning('Invitation à refaire l\'évaluation : e-mail non envoyé.', ['invitation' => $invitation->id, 'reason' => $reason]);
            $invitation->update(['status' => LearnerInvitation::FAILED, 'error' => mb_strimwidth($reason, 0, 200, '…'), 'sent_at' => now()]);

            return LearnerInvitation::FAILED;
        }

        $invitation->update(['status' => LearnerInvitation::SENT, 'error' => null, 'sent_at' => now()]);

        return LearnerInvitation::SENT;
    }

    /**
     * Chiffres pour la page « Alertes » de l'administration.
     *
     * @return array{total: int, sent: int, pending: int, failed: int, no_email: int, today: int}
     */
    public static function stats(): array
    {
        $counts = LearnerInvitation::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'sent' => (int) ($counts[LearnerInvitation::SENT] ?? 0),
            'pending' => (int) ($counts[LearnerInvitation::PENDING] ?? 0),
            'failed' => (int) ($counts[LearnerInvitation::FAILED] ?? 0),
            'no_email' => (int) ($counts[LearnerInvitation::NO_EMAIL] ?? 0),
            'today' => MailQuota::invitationsToday(),
        ];
    }
}
