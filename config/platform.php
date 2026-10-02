<?php

/*
|--------------------------------------------------------------------------
| Réglages de la plateforme
|--------------------------------------------------------------------------
|
| Tout ce qui relève du commerce (nom, formules, prix, durées) se règle ici
| et nulle part ailleurs : le code et les vues lisent ces valeurs via
| config('platform.…'). Après une modification en production, lancer
| « php artisan config:cache » pour que le changement soit pris en compte.
|
*/

return [

    // Nom commercial affiché sur tout le site.
    'name' => env('PLATFORM_NAME', 'Teacher Joe'),

    // Devise utilisée pour les prix.
    'currency' => 'FCFA',

    /*
    |--------------------------------------------------------------------------
    | Évaluation orale gratuite
    |--------------------------------------------------------------------------
    */
    'assessment' => [
        // Durée maximale de l'évaluation, en minutes (une seule par compte).
        'max_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Séances quotidiennes
    |--------------------------------------------------------------------------
    */
    'lessons' => [
        // Le professeur prévient l'apprenant ce nombre de minutes avant la fin.
        'warning_minutes_before_end' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Formules d'abonnement
    |--------------------------------------------------------------------------
    |
    | La clé (essentiel, intensif, semaine) est enregistrée en base avec chaque
    | abonnement : ne pas la renommer une fois des abonnements vendus.
    | Le nom, les minutes, le prix et la durée sont recopiés dans l'abonnement
    | au moment de l'achat : les modifier ici ne touche que les futurs achats.
    |
    | - daily_minutes : minutes de séance par jour
    | - price         : prix en FCFA
    | - period        : « month » ou « week » (sert au libellé « par mois »)
    | - duration_days : nombre de jours de validité de l'abonnement
    |
    */
    'plans' => [
        'essentiel' => [
            'name' => 'Essentiel',
            'daily_minutes' => 15,
            'price' => 5000,
            'period' => 'month',
            'duration_days' => 30,
        ],
        'intensif' => [
            'name' => 'Intensif',
            'daily_minutes' => 30,
            'price' => 9000,
            'period' => 'month',
            'duration_days' => 30,
        ],
        'semaine' => [
            'name' => 'Semaine',
            'daily_minutes' => 15,
            'price' => 1500,
            'period' => 'week',
            'duration_days' => 7,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Numéros de téléphone
    |--------------------------------------------------------------------------
    |
    | Les numéros sont enregistrés au format international (+22890123456).
    | Un numéro saisi sans indicatif et de la longueur locale attendue reçoit
    | l'indicatif par défaut.
    |
    */
    'phone' => [
        'default_country_code' => env('PHONE_DEFAULT_COUNTRY_CODE', '228'),
        'local_length' => (int) env('PHONE_LOCAL_LENGTH', 8),
    ],

];
