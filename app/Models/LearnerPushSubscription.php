<?php

namespace App\Models;

/**
 * Un téléphone d'ÉLÈVE abonné aux rappels de séance (Web Push). Table à part de celle des téléphones de
 * l'administrateur : un élève ne reçoit jamais une alerte de paiement, et l'administrateur jamais un rappel
 * d'élève par cette liste. Mêmes colonnes et même envoi (PushTransport) que PushSubscription.
 */
class LearnerPushSubscription extends PushSubscription
{
    protected $table = 'learner_push_subscriptions';
}
