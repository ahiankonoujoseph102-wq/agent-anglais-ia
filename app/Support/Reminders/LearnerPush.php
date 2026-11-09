<?php

namespace App\Support\Reminders;

use App\Models\LearnerPushSubscription;
use App\Models\User;
use App\Support\Alerts\PushTransport;
use App\Support\Alerts\WebPushTransport;

/**
 * Notification sur les téléphones d'un élève (Web Push, table learner_push_subscriptions) : rappels de séance,
 * demande d'avis sur Google… Un appareil qui n'existe plus (404, 410) est retiré.
 */
final class LearnerPush
{
    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $message
     * @return string 'sent', 'failed', 'none' (aucun téléphone abonné) ou 'skipped' (notifications non réglées)
     */
    public static function send(User $user, array $message): string
    {
        if (! WebPushTransport::configured()) {
            return 'skipped';
        }

        $devices = LearnerPushSubscription::query()->where('user_id', $user->id)->get();

        if ($devices->isEmpty()) {
            return 'none';
        }

        $transport = app(PushTransport::class);
        $ok = false;

        foreach ($devices as $device) {
            $result = $transport->send($device, $message);

            if ($result->sent) {
                $ok = true;
                $device->forceFill(['last_sent_at' => now()])->save();
            } elseif ($result->gone) {
                $device->delete();
            }
        }

        return $ok ? 'sent' : 'failed';
    }
}
