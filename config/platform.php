<?php

/*
|--------------------------------------------------------------------------
| Réglages de la plateforme
|--------------------------------------------------------------------------
|
| Tout ce qui relève du commerce (nom, formules, prix, durées) se règle ici
| et nulle part ailleurs : le code et les vues lisent ces valeurs via
| config('platform.…'). Sur Laravel Cloud, chaque déploiement reconstruit le cache de configuration
| (« php artisan optimize ») : modifier une variable, puis redéployer.
|
*/

return [

    // Nom commercial affiché sur tout le site.
    'name' => env('PLATFORM_NAME', 'Talkami'),

    /*
    | Marque : logos, accroche, nom court et couleurs de l'application installée.
    | Le nom est ci-dessus ; les pages l'affichent via config('platform.name')
    | et le logo via <x-logo>. Logos officiels dans public/brand/ (préparés par
    | tools/brand/prepare-logos.py à partir de docs/brand/reference/).
    | Le professeur garde son propre nom : config('avatar.model.name').
    */
    'brand' => [
        'short_name' => env('PLATFORM_SHORT_NAME', 'Talkami'),
        'tagline' => 'Votre professeur d\'anglais à l\'oral',
        'logo' => [
            'header' => 'brand/logo-horizontal-640.webp',  // fond clair
            'footer' => 'brand/logo-blanc-640.webp',       // fond bleu nuit
        ],
        // Couleurs de l'application installée (barre d'état, écran de démarrage).
        // Les garder identiques à --c-primary et --c-bg de public/css/tokens.css.
        'theme_color' => '#0E2A5C',
        'background_color' => '#F4F6FB',
    ],

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
        // Le professeur est prévenu ce nombre de minutes avant la fin pour conclure.
        'warning_minutes_before_end' => 1,
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
    | Suivi des conversations vocales (évaluation et séances)
    |--------------------------------------------------------------------------
    |
    | Le navigateur envoie un signal toutes les « heartbeat_seconds ». Sans
    | signal depuis « stale_after_seconds », la conversation est considérée
    | comme interrompue et clôturée par le serveur.
    |
    */
    'live' => [
        'heartbeat_seconds' => 15,
        'stale_after_seconds' => 60,
        // En dessous de ce temps restant, une nouvelle séance n'est pas ouverte.
        'min_start_seconds' => 30,
        // Demi-duplex : le micro n'est pas envoyé pendant la voix du professeur, ni
        // pendant ce délai après (sa voix ne doit pas revenir par le haut-parleur).
        'echo_guard_ms' => 400,
        // Le temps continue pendant une pause ; au-delà de cette durée, la conversation se termine.
        'pause_max_seconds' => 120,
        // Volume de la voix du professeur (1 = tel quel). Un limiteur empêche toute saturation.
        // Variable absente, vide ou nulle : 2 (une voix muette n'est jamais voulue).
        'voice_gain' => (float) env('GEMINI_VOICE_GAIN') ?: 2.0,
        // Annulation d'écho du navigateur. Sur Android, Chrome passe alors le téléphone en mode
        // « communication » : la voix suit le volume d'appel, souvent plus bas. Le demi-duplex
        // empêche déjà l'écho ; à couper (false) seulement après un essai sans écho sur téléphone.
        // Seules les valeurs « false », « 0 », « off » ou « no » la coupent ; absente ou vide : activée.
        'echo_cancellation' => ! in_array(env('LIVE_ECHO_CANCELLATION'), [false, '0', 'off', 'no'], true),
        // Sans aucun son capté pendant ce temps alors que c'est à l'apprenant de parler : message.
        'silent_mic_seconds' => 8,
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
    | Coordonnées du centre
    |--------------------------------------------------------------------------
    |
    | Affichées dans le pied de page et sur la page Contact.
    |
    */
    'contact' => [
        'address' => 'Adidogomé, Lycée moderne 2, Lomé, Togo',
        'hours' => 'Du lundi au samedi, de 8 h à 20 h',
        'phones' => ['+228 72 24 02 31', '+228 79 74 21 27', '+228 79 16 23 90'],
        'email' => 'ahiankonoujoseph102@gmail.com',
        'whatsapp' => '+228 72 24 02 31',
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
