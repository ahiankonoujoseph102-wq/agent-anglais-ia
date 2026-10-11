<?php

/*
|--------------------------------------------------------------------------
| Paiement manuel par Mobile Money
|--------------------------------------------------------------------------
|
| L'apprenant envoie l'argent depuis son téléphone sur l'un des numéros
| ci-dessous, puis déclare son paiement sur le site (opérateur, numéro
| payeur, référence, capture d'écran). L'administrateur le valide ou le
| refuse : aucune API de paiement n'est utilisée.
|
| Les coordonnées ne sont écrites QUE dans ce fichier. Après une
| modification en production : « php artisan config:cache ».
|
| La clé de chaque pays et de chaque opérateur est enregistrée avec les
| paiements : ne pas la renommer une fois des paiements reçus.
|
*/

return [

    'countries' => [
        'TG' => [
            'name' => 'Togo',
            'holder' => 'Ahiankonou Kokou Joseph',
            'operators' => [
                'mixx' => ['name' => 'Mixx by Yas', 'number' => '+228 72 24 02 31'],
                'flooz' => ['name' => 'Flooz', 'number' => '+228 79 16 23 90'],
            ],
        ],
        'BF' => [
            'name' => 'Burkina Faso',
            'holder' => 'Anderson Passoule Oyo',
            'operators' => [
                'orange_money' => ['name' => 'Orange Money', 'number' => '+226 07 27 63 81'],
                'wave' => ['name' => 'Wave', 'number' => '+226 03 53 21 76'],
            ],
        ],
        'BJ' => [
            'name' => 'Bénin',
            'holder' => 'KOUAGOU Noutcha Adam Fernando',
            'operators' => [
                'mtn' => ['name' => 'MTN', 'number' => '+229 01 51 53 39 35'],
                'moov' => ['name' => 'Moov', 'number' => '+229 01 94 55 59 14'],
                'celtiis' => ['name' => 'Celtiis', 'number' => '+229 01 40 28 57 61'],
            ],
        ],
    ],

    // Numéro WhatsApp prévenu après un paiement (bouton « Prévenir sur WhatsApp »).
    'whatsapp' => '+228 72 24 02 31',

    /*
    | Captures d'écran des paiements : stockage privé, visible seulement par
    | l'administrateur. Disque vide = disque par défaut de Laravel : « local »
    | (storage/app/private) en développement, le bucket privé attaché comme
    | disque par défaut sur Laravel Cloud (son système de fichiers est éphémère).
    */
    'proofs' => [
        'disk' => env('PAYMENT_PROOFS_DISK'),
        'directory' => 'payment-proofs',
        'max_kilobytes' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    // Rappel de renouvellement affiché ce nombre de jours avant la fin de l'abonnement.
    'renewal_reminder_days' => 3,

];
