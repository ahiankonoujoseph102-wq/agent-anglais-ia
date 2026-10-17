<?php

/*
|--------------------------------------------------------------------------
| Paiement manuel par Mobile Money
|--------------------------------------------------------------------------
|
| L'apprenant envoie l'argent depuis son téléphone sur le numéro affiché, puis
| envoie la capture de l'écran de confirmation. L'IA la lit et des règles
| (App\Support\PaymentChecks) décident d'un accès provisoire de 24 heures ;
| l'administrateur valide ou refuse ensuite. Aucune API de paiement.
|
| Numéro de réception (« number ») et titulaire (« holder ») de chaque
| opérateur : valeurs PAR DÉFAUT, affichées sur le site. Une valeur saisie
| dans l'administration, page « Réglages » (table settings,
| App\Support\SiteSettings), les remplace ; un champ vide dans les Réglages
| revient à la valeur par défaut. Un opérateur sans numéro (ni par défaut, ni
| dans les Réglages) n'est pas proposé aux apprenants.
|
| La clé de chaque pays et de chaque opérateur est enregistrée avec les
| paiements et les réglages : ne pas la renommer une fois des paiements reçus.
|
*/

return [

    'countries' => [
        'TG' => [
            'name' => 'Togo',
            'dial_code' => '228',
            'operators' => [
                // « aliases » : mots qui identifient l'opérateur sur une capture (en minuscules).
                // « number » et « holder » : valeurs par défaut, remplaçables dans les Réglages.
                'mixx' => ['name' => 'Mixx by Yas', 'aliases' => ['mixx', 'yas'], 'number' => '+228 72 24 02 31', 'holder' => 'Ahiankonou Kokou Joseph'],
                'flooz' => ['name' => 'Flooz', 'aliases' => ['flooz', 'moov'], 'number' => '+228 79 16 23 90', 'holder' => 'Ahiankonou Kokou Joseph'],
            ],
        ],
        'BF' => [
            'name' => 'Burkina Faso',
            'dial_code' => '226',
            'operators' => [
                'orange_money' => ['name' => 'Orange Money', 'aliases' => ['orange'], 'number' => '+226 07 27 63 81', 'holder' => 'Anderson Passoule Oyo'],
                'wave' => ['name' => 'Wave', 'aliases' => ['wave'], 'number' => '+226 03 53 21 76', 'holder' => 'Anderson Passoule Oyo'],
            ],
        ],
        'BJ' => [
            'name' => 'Bénin',
            'dial_code' => '229',
            'operators' => [
                'mtn' => ['name' => 'MTN', 'aliases' => ['mtn', 'momo'], 'number' => '+229 01 51 53 39 35', 'holder' => 'KOUAGOU Noutcha Adam Fernando'],
                'moov' => ['name' => 'Moov', 'aliases' => ['moov', 'flooz'], 'number' => '+229 01 94 55 59 14', 'holder' => 'KOUAGOU Noutcha Adam Fernando'],
                'celtiis' => ['name' => 'Celtiis', 'aliases' => ['celtiis', 'celtis'], 'number' => '+229 01 40 28 57 61', 'holder' => 'KOUAGOU Noutcha Adam Fernando'],
            ],
        ],
    ],

    /*
    | Captures d'écran des paiements : stockage privé (disque réglé par PRIVATE_FILES_DISK,
    | voir config/filesystems.php), visibles seulement par l'administrateur.
    | Le navigateur compresse les images trop lourdes avant l'envoi (public/js/proof-upload.js).
    */
    'proofs' => [
        'directory' => 'payment-proofs',
        'max_kilobytes' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        // Envois de captures par heure et par compte.
        'per_hour' => 5,
    ],

    /*
    | Lecture de la capture et accès provisoire (App\Support\PaymentChecks).
    */
    'provisional' => [
        // Durée de l'accès provisoire accordé quand toutes les règles passent.
        'hours' => 24,
        // Le paiement doit dater de moins de ce nombre d'heures…
        'max_age_hours' => 48,
        // … et pas du futur, avec cette marge (fuseaux horaires, horloge du téléphone).
        'future_tolerance_minutes' => 90,
        // Devises acceptées sur la capture (franc CFA d'Afrique de l'Ouest).
        'currencies' => ['XOF', 'FCFA', 'CFA', 'F CFA', 'F'],
    ],

    // Rappel de renouvellement affiché ce nombre de jours avant la fin de l'abonnement.
    'renewal_reminder_days' => 3,

];
