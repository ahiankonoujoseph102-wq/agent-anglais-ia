<?php

namespace App\Support\Alerts;

use App\Mail\PaymentAlertMail;
use App\Models\AdminAlert;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoie une alerte à l'administrateur par deux canaux : notification sur chaque téléphone abonné et e-mail.
 *
 * Règles : une alerte ne bloque et ne ralentit jamais l'apprenant (envoi APRÈS la réponse, délais courts) ; un
 * canal qui n'est pas réglé est ignoré sans erreur ; un échec est noté sur la page « Alertes » avec une raison
 * courte et sans secret ; un appareil désabonné (404 ou 410) est supprimé.
 */
final class AdminAlerts
{
    /**
     * Envoi après la réponse HTTP (ou la fin de la commande) : l'apprenant n'attend rien.
     */
    public static function dispatch(AlertMessage $alert): void
    {
        defer(static function () use ($alert) {
            app(self::class)->send($alert);
        })->always();
    }

    public function pushConfigured(): bool
    {
        return WebPushTransport::configured();
    }

    public function mailConfigured(): bool
    {
        $mailer = (array) config('mail.mailers.'.config('mail.default'));

        return config('alerts.emails') !== []
            && filled(config('mail.from.address'))
            && ($mailer['transport'] ?? null) === 'smtp'
            && filled($mailer['host'] ?? null)
            && filled($mailer['username'] ?? null)
            && filled($mailer['password'] ?? null);
    }

    /**
     * Envoie l'alerte sur les canaux réglés et note chaque résultat.
     *
     * @param  list<string>  $channels  canaux à utiliser (tous par défaut)
     * @return array<string, array{status: string, reason: ?string}> résultat par canal réglé
     */
    public function send(AlertMessage $alert, array $channels = ['push', 'mail']): array
    {
        $results = [];

        try {
            if (in_array('push', $channels, true) && $this->pushConfigured()) {
                $results['push'] = $this->sendPush($alert);
            }

            if (in_array('mail', $channels, true) && $this->mailConfigured()) {
                $results['mail'] = $this->sendMail($alert);
            }

            foreach ($results as $channel => $result) {
                $this->record($alert, $channel, $result['status'], $result['reason']);
            }

            $this->prune();
        } catch (Throwable $e) {
            Log::warning('Alerte de paiement : enregistrement impossible.', ['reason' => Redact::reason($e->getMessage())]);
        }

        return $results;
    }

    /**
     * @return array{status: string, reason: ?string}
     */
    private function sendPush(AlertMessage $alert): array
    {
        $subscriptions = PushSubscription::query()->get();

        if ($subscriptions->isEmpty()) {
            return ['status' => AdminAlert::FAILED, 'reason' => 'aucun téléphone n\'est abonné'];
        }

        $transport = app(PushTransport::class);
        $payload = $alert->pushPayload();
        $sent = 0;
        $reason = null;

        foreach ($subscriptions as $subscription) {
            $result = $transport->send($subscription, $payload);

            if ($result->sent) {
                $sent++;
                $subscription->forceFill(['last_sent_at' => now()])->save();
            } elseif ($result->gone) {
                $subscription->delete();
                $reason ??= $result->reason;
            } else {
                $reason ??= $result->reason;
            }
        }

        if ($sent > 0) {
            return ['status' => AdminAlert::SENT, 'reason' => null];
        }

        Log::warning('Alerte de paiement : notification non envoyée.', ['reason' => $reason]);

        return ['status' => AdminAlert::FAILED, 'reason' => $reason];
    }

    /**
     * @return array{status: string, reason: ?string}
     */
    private function sendMail(AlertMessage $alert): array
    {
        try {
            Mail::to(config('alerts.emails'))->send(new PaymentAlertMail($alert));
        } catch (Throwable $e) {
            $reason = Redact::reason($e->getMessage());
            Log::warning('Alerte de paiement : e-mail non envoyé.', ['reason' => $reason]);

            return ['status' => AdminAlert::FAILED, 'reason' => $reason];
        }

        return ['status' => AdminAlert::SENT, 'reason' => null];
    }

    private function record(AlertMessage $alert, string $channel, string $status, ?string $reason): void
    {
        AdminAlert::create([
            'kind' => $alert->kind,
            'channel' => $channel,
            'status' => $status,
            'title' => mb_strimwidth($alert->title, 0, 160, '…'),
            'reason' => $reason,
            'payment_id' => $alert->paymentId,
        ]);
    }

    /** Garde les dernières alertes seulement. */
    private function prune(): void
    {
        $keep = (int) config('alerts.keep');

        if (random_int(1, 20) !== 1) {
            return;
        }

        $threshold = AdminAlert::query()->orderByDesc('id')->skip($keep)->value('id');

        if ($threshold !== null) {
            AdminAlert::query()->where('id', '<=', $threshold)->delete();
        }
    }
}
