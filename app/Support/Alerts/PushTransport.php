<?php

namespace App\Support\Alerts;

use App\Models\PushSubscription;

/**
 * Envoi d'une notification à un appareil abonné. Remplacé par un faux dans les tests : aucun test n'envoie rien.
 */
interface PushTransport
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(PushSubscription $subscription, array $payload): PushResult;
}
