<?php

namespace App\Support\Alerts;

use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Notification Web Push (VAPID) par la bibliothèque minishlink/web-push. Un délai court, jamais la clé dans une
 * raison d'échec.
 */
final class WebPushTransport implements PushTransport
{
    public static function configured(): bool
    {
        return filled(config('alerts.vapid.public_key')) && filled(config('alerts.vapid.private_key')) && filled(config('alerts.vapid.subject'));
    }

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        if (! self::configured()) {
            return PushResult::failed('clés VAPID absentes');
        }

        try {
            $webPush = new WebPush(
                ['VAPID' => [
                    'subject' => config('alerts.vapid.subject'),
                    'publicKey' => config('alerts.vapid.public_key'),
                    'privateKey' => config('alerts.vapid.private_key'),
                ]],
                ['TTL' => (int) config('alerts.push_ttl_seconds'), 'urgency' => 'high'],
                new Client(['timeout' => (int) config('alerts.push_timeout_seconds'), 'connect_timeout' => (int) config('alerts.push_timeout_seconds')]),
            );

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        } catch (Throwable $e) {
            return PushResult::failed(Redact::reason($e->getMessage()));
        }

        if ($report->isSuccess()) {
            return PushResult::sent();
        }

        if ($report->isSubscriptionExpired()) {
            return PushResult::gone();
        }

        return PushResult::failed(Redact::reason($report->getReason()));
    }
}
