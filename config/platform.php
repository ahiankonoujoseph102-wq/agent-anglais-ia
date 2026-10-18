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
            'header' => 'brand/logo-horizontal-320.webp',  // fond clair (320 pixels : affiché à environ 130 pixels)
            'footer' => 'brand/logo-blanc-320.webp',       // fond bleu nuit
        ],
        // Couleurs de l'application installée (barre d'état, écran de démarrage).
        // Les garder identiques à --c-primary et --c-bg de public/css/tokens.css.
        'theme_color' => '#0E2A5C',
        'background_color' => '#F4F6FB',
        // Style des pastilles d'icône, sur tout le site (comparer sur /admin/icones) :
        // « night » (bleu nuit en relief, icône orange), « gradient » (dégradé orange, icône blanche)
        // ou « duotone » (fond blanc, icône bleu nuit remplie d'orange clair). App\Support\IconStyle.
        'icon_style' => 'night',
        // Signature en bas du pied de page : « Développé par … ».
        'developer' => 'Joseph Ahiankonou',
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
        // Après l'annonce du niveau : questions libres puis conclusion, en français.
        // Ce temps s'ajoute à partir du verdict (durée totale coupée par le serveur :
        // max_minutes + questions_seconds au plus).
        'questions_seconds' => 120,
        // Nombre de questions auxquelles le professeur répond.
        'questions_max' => 3,
        // UNE indication « passez à la conclusion » ce nombre de secondes avant la fin.
        'conclusion_cue_seconds' => 30,
        // Phrase fixe qui termine la conclusion : sa détection coupe la conversation.
        'closing_phrase' => 'On se retrouve de l\'autre côté !',
        // À la limite, la phrase en cours peut finir pendant ce nombre de secondes au plus.
        'closing_grace_seconds' => 6,
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
    | « Mes cours » : l'apprenant apporte une leçon d'anglais, Teacher Joe la réexplique
    |--------------------------------------------------------------------------
    |
    | Séances à part : elles ne consomment PAS les minutes de la séance orale quotidienne.
    | Fichiers sur le disque privé (App\Support\PrivateStorage). Tout se règle ici.
    |
    */
    'courses' => [
        // Séances « Mes cours » par jour et par apprenant (nouvelle leçon ou révision d'une ancienne).
        // Une conversation qui n'a pas pu démarrer (panne) ne compte pas.
        'sessions_per_day' => 5,
        // Durée d'une séance, en minutes (le serveur coupe à la limite).
        'session_minutes' => 10,
        // Teacher Joe est prévenu ce nombre de secondes avant la fin : il conclut poliment.
        'warning_seconds' => 90,
        // Photos : nombre de pages, poids d'une photo et de l'ensemble (kilooctets). Le navigateur les
        // réduit avant l'envoi (côté le plus long, qualité JPEG) ; ces plafonds valent sans réduction.
        'max_photos' => 4,
        'photo_max_kilobytes' => 6144,
        'photos_total_max_kilobytes' => 16384,
        'photo_max_side' => 1600,
        'photo_quality' => 0.82,
        // PDF : poids (kilooctets) et nombre de pages.
        'pdf_max_kilobytes' => 10240,
        'pdf_max_pages' => 10,
        // Lectures de leçons (réussies ou non) par heure et par apprenant : protège le coût de la lecture.
        'reads_per_hour' => 10,
        // Dossier des fichiers sur le disque privé.
        'directory' => 'courses',
        // Texte de la leçon transmis à Teacher Joe, en caractères au plus.
        'text_limit' => 12000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message de bienvenue de Teacher Joe (après l'inscription, puis à l'ouverture d'un accès)
    |--------------------------------------------------------------------------
    |
    | Salutation selon l'heure du pays de l'apprenant (déduit de l'indicatif du téléphone,
    | fuseaux dans config/payment.php) : « Bonjour » de morning_from à evening_from (exclu),
    | « Bonsoir » le reste du temps. Les textes sont dans config/teacher.php (welcome).
    |
    */
    'welcome' => [
        // Heures (0 à 23, heure locale du pays) : bascule du « Bonjour » au « Bonsoir ».
        'morning_from' => 5,
        'evening_from' => 15,
        // Prénom : premier mot du nom saisi, lettres, tirets et apostrophes seulement, longueur au plus.
        'name_max_length' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Accès offert par l'administrateur (démonstrations dans les écoles, auprès des parents)
    |--------------------------------------------------------------------------
    |
    | Mêmes droits que la formule choisie (séance orale et « Mes cours » avec leurs limites).
    | Jamais un paiement ni un revenu. Se règle ici.
    |
    */
    'granted_access' => [
        // Durées proposées en un geste (jours) ; une durée au choix est aussi possible.
        'presets' => [1, 3, 7],
        // Durée la plus longue qu'on puisse offrir, en jours.
        'max_days' => 30,
        // L'apprenant est invité à choisir une formule ce nombre de jours avant la fin (1 : le jour de la fin et la veille).
        'reminder_days' => 1,
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
            // Badge affiché sur la carte de la formule, partout (jamais de statistique).
            'badge' => 'Recommandée',
            // Formule mise en avant : badge orange et contour orange de la carte.
            'recommended' => true,
        ],
        'intensif' => [
            'name' => 'Intensif',
            'daily_minutes' => 30,
            'price' => 9000,
            'period' => 'month',
            'duration_days' => 30,
            'badge' => 'Progrès plus rapides',
        ],
        'semaine' => [
            'name' => 'Semaine',
            'daily_minutes' => 15,
            'price' => 1500,
            'period' => 'week',
            'duration_days' => 7,
            'badge' => 'Pour essayer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Coordonnées du centre
    |--------------------------------------------------------------------------
    |
    | Affichées dans le pied de page, sur la page Contact, les attestations et les
    | boutons WhatsApp. Valeurs PAR DÉFAUT : une valeur saisie dans l'administration,
    | page « Réglages » (App\Support\SiteSettings), les remplace ; un champ vide dans
    | les Réglages revient à ces valeurs. Les horaires ne se règlent qu'ici.
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
