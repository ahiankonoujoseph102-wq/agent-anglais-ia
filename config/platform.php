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
        // Titre de l'accueil pour les moteurs de recherche et le partage : « {nom} · {home_title} ».
        'home_title' => 'Apprendre à parler anglais depuis votre téléphone',
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
        // Après l'annonce du niveau : mini-leçon découverte (une phrase du niveau trouvé), questions libres puis
        // conclusion, en français. Ces temps s'ajoutent à partir du verdict (durée totale coupée par le serveur :
        // max_minutes + discovery_seconds + questions_seconds au plus, soit 8 minutes).
        'questions_seconds' => 120,
        'discovery_seconds' => 60,
        // Mini-leçon : phrase dite mot pour mot pour l'ouvrir, et nombre de phrases montrées au tableau.
        'discovery_intro' => 'Laissez-moi vous montrer comment nous allons travailler ensemble.',
        'discovery_phrases' => 3,
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
        // Phrases de passage d'un temps à l'autre de la séance du jour, dites MOT POUR MOT par le professeur
        // (config/teacher.php → lesson) : l'écran les reconnaît dans sa transcription et affiche la phase en cours.
        'phase_phrases' => [
            'practice' => "Now, let's practice!",
            'conversation' => "Now, let's talk!",
            'summary' => "Let's sum up!",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Temps du jour, partagé entre la séance quotidienne et « Mes cours »
    |--------------------------------------------------------------------------
    |
    | Chaque formule donne UN temps par jour (plans.*.daily_minutes), à partager librement
    | entre la séance orale du jour et les séances « Mes cours ». Le décompte repart à zéro
    | à minuit, heure de l'application (APP_TIMEZONE, Africa/Lome). L'évaluation gratuite et
    | le bilan de niveau n'y comptent pas. Le serveur accorde le temps au début de chaque
    | séance et la coupe à la fin, quel que soit le fournisseur (App\Models\User::remainingSecondsToday).
    |
    */
    'daily_time' => [
        // En dessous de ce temps restant aujourd'hui, aucune nouvelle séance n'est ouverte (2 minutes).
        'min_start_seconds' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Texte des conversations (jamais l'audio)
    |--------------------------------------------------------------------------
    |
    | Toutes les séances orales (évaluation, séance du jour, « Mes cours », bilan de niveau) : le
    | navigateur envoie les répliques de l'apprenant et de Teacher Joe par petits lots pendant la
    | séance, puis à la fin. L'administrateur les relit (fiche d'un inscrit). Effacées automatiquement
    | (commande transcripts:purge, chaque jour) après keep_days jours.
    |
    */
    'transcripts' => [
        // Durée de conservation du texte des conversations, en jours.
        'keep_days' => 90,
        // Répliques acceptées seulement pour une séance commencée il y a moins de ce nombre d'heures.
        'accept_hours' => 6,
        // Taille des envois : répliques par envoi, caractères par réplique, répliques par séance.
        'max_lines_per_request' => 50,
        'max_line_characters' => 4000,
        'max_lines_per_conversation' => 3000,
        // Événements de la séance (reconnexion, micro fermé, pause…) : par envoi et par séance.
        'max_events_per_request' => 50,
        'max_events_per_conversation' => 2000,
        // Délai de réponse affiché en orange au-delà de cette durée (« Lire la conversation »).
        'slow_reply_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidentialité (page /confidentialite)
    |--------------------------------------------------------------------------
    |
    | Responsable du traitement des données, affiché sur la page « Confidentialité ».
    |
    */
    'privacy' => [
        'controller' => 'Joseph Ahiankonou',
        'email' => 'ahiankonoujoseph102@gmail.com',
        'phone' => '+228 72 24 02 31',
    ],

    /*
    |--------------------------------------------------------------------------
    | « Mes cours » : l'apprenant apporte une leçon d'anglais, Teacher Joe la réexplique
    |--------------------------------------------------------------------------
    |
    | Ces séances prennent sur le temps du jour de la formule (daily_time ci-dessus), partagé
    | avec la séance orale quotidienne. Fichiers sur le disque privé (App\Support\PrivateStorage).
    |
    */
    'courses' => [
        // Durée d'une séance au plus, en minutes, et jamais plus que le temps restant du jour
        // (le serveur coupe à la limite).
        'max_session_minutes' => 10,
        // Teacher Joe est prévenu ce nombre de secondes avant la fin : il conclut poliment.
        'warning_seconds' => 90,
        // Photos : nombre de pages, poids d'une photo et de l'ensemble (kilooctets). Le navigateur les
        // réduit avant l'envoi (côté le plus long, qualité JPEG) ; ces plafonds valent sans réduction.
        'max_photos' => 4,
        'photo_max_kilobytes' => 6144,
        'photos_total_max_kilobytes' => 16384,
        'photo_max_side' => 1600,
        'photo_quality' => 0.82,
        // PDF : poids (kilooctets) et nombre de pages. Gemini et OpenAI acceptent 50 mégaoctets par PDF envoyé
        // directement (documentation lue le 20 octobre 2026) : ces limites restent bien en dessous.
        'pdf_max_kilobytes' => 20480,
        'pdf_max_pages' => 50,
        // Un PDF de plus de part_max_pages pages est lu en deux temps : à l'envoi, un sommaire page par page ;
        // avant la séance, la partie choisie (part_max_pages pages au plus), relue et gardée (course_parts).
        'part_max_pages' => 10,
        // Passage décrit par l'élève (« l'exercice 2 ») : caractères au plus.
        'part_description_max' => 150,
        // Délai accordé à CHAQUE fournisseur pour lire un sommaire ou une partie (secondes) ; le navigateur
        // abandonne l'attente après browser_timeout_seconds avec un message clair.
        'read_timeout_seconds' => 30,
        'browser_timeout_seconds' => 100,
        // « Diagnostic des fournisseurs », essai de l'attente : aussi long que la lecture la plus longue.
        'check_wait_seconds' => 60,
        // Lectures de leçons (réussies ou non) par heure et par apprenant : protège le coût de la lecture.
        'reads_per_hour' => 10,
        // Dossier des fichiers sur le disque privé.
        'directory' => 'courses',
        // Texte de la leçon transmis à Teacher Joe, en caractères au plus.
        'text_limit' => 12000,
        // Document personnel de révision après une séance (App\Support\CourseReviews) : préparé par la tâche
        // du planificateur « courses:prepare-reviews » (chaque minute, jamais deux fois en même temps), au plus
        // batch séances par passage, delay_seconds après la fin de la séance (le texte de la séance a le temps
        // d'arriver). Séance trop courte (min_seconds, min_learner_words mots dits par l'élève) : pas de document.
        // Échec : nouvel essai après retry_minutes, max_attempts essais au plus, puis « Réessayer » (retries_per_hour).
        'review' => [
            'delay_seconds' => 120,
            'batch' => 3,
            'lookback_days' => 7,
            'min_seconds' => 120,
            'min_learner_words' => 15,
            'max_attempts' => 3,
            'retry_minutes' => 10,
            'retries_per_hour' => 3,
            'timeout_seconds' => 45,
            'transcript_limit' => 12000,
            'directory' => 'course-reviews',
            // E-mails de documents par jour : la limite gratuite de Brevo (mail.daily_limit, 300) moins 20 gardés pour
            // les alertes de l'administrateur. Au-delà : document téléchargeable seulement, administrateur prévenu.
            'daily_emails' => 280,
        ],
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
    | Mêmes droits que la formule choisie (le même temps par jour, séance orale et « Mes cours »).
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
        // Fin de tour (public/js/turn-watch.js) : la voix de l'apprenant, mesurée dans le navigateur, est finie
        // depuis ce temps et le fournisseur n'a ni clos le tour ni commencé à répondre (bruit de fond, longue
        // réponse) : le navigateur signale lui-même la fin du tour (Gemini : audioStreamEnd ; OpenAI : silence).
        'end_of_turn_seconds' => 3,
        // Aucune voix du professeur ce temps après la fin de la parole : « Teacher Joe réfléchit, un instant… ».
        'no_answer_seconds' => 8,
        // Toujours rien : message et bouton « Relancer Teacher Joe ».
        'stuck_seconds' => 20,
        // OpenAI : voix annoncée mais plus rien d'audible depuis ce temps (fin de voix perdue) : micro rouvert.
        'voice_stuck_seconds' => 4,
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
    | - daily_minutes : temps par jour, partagé entre la séance orale et « Mes cours »
    | - price         : prix en FCFA
    | - period        : « month » ou « week » (sert au libellé « par mois »)
    | - duration_days : nombre de jours de validité de l'abonnement
    |
    */
    // ORDRE D'AFFICHAGE : les formules sont présentées partout (accueil, /formules, résultat de l'évaluation, paiement,
    // données structurées, fiche du site de Teacher Joe) dans l'ordre de cette liste, du prix le plus bas au plus haut.
    // Pour changer l'ordre, déplacer une formule dans cette liste : rien d'autre à modifier.
    'plans' => [
        'semaine' => [
            'name' => 'Semaine',
            'daily_minutes' => 15,
            'price' => 2000,
            'period' => 'week',
            'duration_days' => 7,
            'badge' => 'Pour essayer',
        ],
        'essentiel' => [
            'name' => 'Essentiel',
            'daily_minutes' => 15,
            'price' => 7500,
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
            'price' => 14000,
            'period' => 'month',
            'duration_days' => 30,
            'badge' => 'Progrès plus rapides',
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
        'hours' => 'Du lundi au samedi, de 8 heures à 20 heures',
        'phones' => ['+228 72 24 02 31', '+228 79 74 21 27', '+228 79 16 23 90'],
        'email' => 'ahiankonoujoseph102@gmail.com',
        'whatsapp' => '+228 72 24 02 31',
        // Lien d'invitation du GROUPE WhatsApp (https://chat.whatsapp.com/…), À COMPLÉTER quand il existe.
        // Vide : le bouton WhatsApp de la page 404 ouvre une conversation avec le numéro ci-dessus.
        'whatsapp_group' => env('WHATSAPP_GROUP_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fondateur (page « À propos » et données structurées Person)
    |--------------------------------------------------------------------------
    |
    | Uniquement des faits vérifiés, donnés par le fondateur. « bio » : sa phrase (page « À propos »,
    | carte « Qui est derrière … ? » de l'accueil, données structurées) ; vide, elle n'est pas affichée
    | et « php artisan app:check-deployment » le signale. Marqueur :platform = nom de la plateforme. « photo » : chemin dans public/ (carré, WebP).
    | « headline » : ce qui suit le nom sur la carte de l'accueil.
    |
    */
    'founder' => [
        'name' => 'Joseph Ahiankonou',
        'role' => 'Fondateur',
        'headline' => 'coach et formateur à Lomé',
        'city' => 'Lomé',
        'country' => 'Togo',
        'country_code' => 'TG',
        'bio' => 'J\'ai créé :platform pour que chaque francophone puisse enfin oser parler anglais, depuis son téléphone, sans gêne.',
        'photo' => 'images/fondateur-joseph-ahiankonou.webp',
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
