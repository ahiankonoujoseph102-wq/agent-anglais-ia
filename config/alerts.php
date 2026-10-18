<?php

/*
|--------------------------------------------------------------------------
| Alertes de paiement pour l'administrateur
|--------------------------------------------------------------------------
|
| Une alerte part par DEUX canaux : notification sur le téléphone (Web Push, clés VAPID) et e-mail (SMTP, par
| exemple Brevo). Une alerte qui échoue ne bloque jamais l'apprenant : l'échec est noté sur la page « Alertes ».
|
*/

return [

    // Adresse(s) qui reçoivent les alertes par e-mail, séparées par des virgules (ADMIN_ALERT_EMAIL).
    'emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_ALERT_EMAIL', ''))))),

    // Clés VAPID du Web Push : à créer UNE fois avec « php artisan alerts:generate-vapid », puis à copier dans
    // les variables d'environnement de Laravel Cloud (la clé privée n'est jamais écrite dans le dépôt).
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        // Contact du site pour les services de notifications : « mailto:… » ou une adresse https.
        'subject' => env('VAPID_SUBJECT'),
    ],

    // « L'accès provisoire expire bientôt » : prévenir ce nombre d'heures avant la fin (une seule alerte par paiement).
    'expiry_warning_hours' => 2,

    // Notification : durée de conservation chez le service de notifications si le téléphone est éteint (secondes).
    'push_ttl_seconds' => 3600,

    // Délai d'envoi de chaque notification et de l'e-mail (secondes) : une alerte lente ne retient jamais personne.
    'push_timeout_seconds' => 5,

    // Un clic WhatsApp du même apprenant ne déclenche pas d'autre alerte pendant ce délai (minutes).
    'whatsapp_click_quiet_minutes' => 30,

    // Alertes gardées pour la page « Alertes » (les plus anciennes sont supprimées).
    'keep' => 200,
    'shown' => 20,
];
