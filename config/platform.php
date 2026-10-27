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
        // Titre de l'accueil pour les moteurs de recherche et le partage : « {nom} · {home_title} »
        // (60 caractères au plus en tout ; :teacher = nom du professeur, config('avatar.model.name')).
        'home_title' => 'Cours d\'anglais en ligne avec :teacher',
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
    // Alerte de coût par abonné (« Consommation », alerte à l'administrateur, App\Support\Live\CostWatch) : quand
    // le coût estimé des séances d'un abonné, depuis le début de sa formule, dépasse ce pourcentage du prix de la
    // formule. Taux de change pour comparer le coût (dollars US) au prix (francs CFA). Réglables dans « Réglages ».
    'cost_alert' => [
        'percent' => 50,
        'xof_per_usd' => 600,
    ],

    'assessment' => [
        // Durée maximale de l'évaluation, en minutes (une seule par compte).
        'max_minutes' => 5,
        // Le professeur est prévenu ce nombre de minutes avant la fin pour conclure.
        'warning_minutes_before_end' => 1,
        // Niveau demandé avant ce temps d'évaluation (en secondes) : la première demande est refusée et le professeur
        // pose encore quelques questions (App\Support\Live\LiveEarlyEnd) ; dans les vraies évaluations, il donnait
        // souvent le niveau au bout de deux minutes, pour une évaluation annoncée de cinq.
        'min_verdict_seconds' => 180,
        // Après l'annonce du niveau : mini-leçon personnelle (les vraies erreurs de l'apprenant, App\Support\Live\AssessmentMiniLesson), questions libres puis
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
        // Niveau reçu juste AVANT que le professeur l'annonce : l'écran et la vidéo (même instant) le montrent quand
        // sa voix le dit (repéré dans sa transcription), sinon ce délai après le résultat une fois qu'il s'est tu,
        // et au plus tard level_reveal_max_seconds après. L'enregistrement en base n'attend jamais.
        'level_reveal_delay_seconds' => 4,
        'level_reveal_max_seconds' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Séances quotidiennes
    |--------------------------------------------------------------------------
    */
    'lessons' => [
        // Le professeur prévient l'apprenant ce nombre de minutes avant la fin.
        'warning_minutes_before_end' => 2,
        // Bilan demandé alors qu'il reste plus que (warning + ceci) minutes, et plus du tiers de la séance : la
        // première demande est refusée et le professeur reprend la séance (App\Support\Live\LiveEarlyEnd).
        'early_end_extra_minutes' => 3,
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
        'email' => 'contact@talkami.net',
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
        // Coupure de connexion : le temps de séance est mis en pause (IsLiveConversation). Une coupure compte sur un
        // signal explicite du navigateur (connexion avec le professeur perdue), ou au-delà de ce temps sans aucun
        // signal ; au plus « disconnect_pause_max_seconds » de coupure par séance, ensuite le temps compte de nouveau
        // et la séance se termine si le navigateur ne revient pas. Le bouton « Pause » de l'élève n'est PAS une
        // coupure : le temps continue (pause_max_seconds).
        'disconnect_after_seconds' => 30,
        // LA phrase de cette règle, reprise telle quelle partout (écran de séance, conseils, foire aux questions,
        // fiche du site ; le message vocal de bienvenue la dit aussi) : tests/Feature/DisconnectionRuleTextTest.php.
        'disconnect_rule' => 'Si la connexion est coupée, reprenez votre séance dès que possible : le temps de la coupure est mis en pause, vous ne perdez pas de minutes.',
        'disconnect_pause_max_seconds' => 600,
        // Demi-duplex : le micro n'est pas envoyé pendant la voix du professeur, ni
        // pendant ce délai après (sa voix ne doit pas revenir par le haut-parleur).
        'echo_guard_ms' => 400,
        // Le temps continue pendant une pause ; au-delà de cette durée, la conversation se termine.
        'pause_max_seconds' => 120,
        // Volume de la voix du professeur (1 = tel quel). Un limiteur empêche toute saturation.
        // LIVE_VOICE_GAIN (l'ancienne GEMINI_VOICE_GAIN est encore lue) ; absente, vide ou nulle : 2,5 (une voix
        // muette n'est jamais voulue).
        'voice_gain' => (float) (env('LIVE_VOICE_GAIN') ?: env('GEMINI_VOICE_GAIN')) ?: 2.5,
        // Annulation d'écho du navigateur. Sur Android, Chrome passe alors le téléphone en mode « communication » :
        // la voix suit le volume d'APPEL, souvent bien plus bas. Le demi-duplex (micro fermé pendant la voix du
        // professeur) empêche déjà l'écho : coupée par défaut sur Android, gardée ailleurs. Seules les valeurs
        // « false », « 0 », « off » ou « no » la coupent, « true », « 1 », « on » ou « yes » l'activent.
        'echo_cancellation' => ! in_array(env('LIVE_ECHO_CANCELLATION'), [false, '0', 'off', 'no'], true),
        'echo_cancellation_android' => in_array(env('LIVE_ECHO_CANCELLATION_ANDROID'), [true, '1', 'on', 'yes'], true),
        // Sans aucun son capté pendant ce temps alors que c'est à l'apprenant de parler : message.
        'silent_mic_seconds' => 8,
        // Fin de tour (public/js/turn-watch.js) : la voix de l'apprenant, mesurée dans le navigateur, est finie
        // depuis ce temps et le fournisseur n'a ni clos le tour ni commencé à répondre (bruit de fond, longue
        // réponse) : le navigateur signale lui-même la fin du tour (Gemini : audioStreamEnd ; OpenAI : silence).
        'end_of_turn_seconds' => 2,
        // Attente de la réponse : ce temps après la fin de la parole, « Un instant… » et un léger geste de Teacher Joe
        // (hochement, sourire).
        'instant_seconds' => 2,
        // Toujours aucune voix du professeur : « La connexion est un peu lente, Teacher Joe arrive… ».
        'no_answer_seconds' => 8,
        // Toujours rien : message et bouton « Relancer Teacher Joe ».
        'stuck_seconds' => 20,
        // Textes de l'attente et des coupures (marqueur :teacher). Jamais « réfléchit », « chargement » ni
        // « traitement », jamais un nom de fournisseur.
        'messages' => [
            'instant' => 'Un instant…',
            'slow' => 'La connexion est un peu lente, :teacher arrive…',
            'stuck' => ':teacher ne répond pas. Touchez « Relancer :teacher » pour reprendre.',
            'nudged' => ':teacher va vous répondre…',
            'reconnecting' => ':teacher revient, un instant…',
            'cut' => ':rule Vérifiez votre connexion internet, puis touchez « Reprendre la séance ».',
            'cut_again' => ':rule Touchez « Reprendre la séance » pour réessayer, ou relancez la séance un peu plus tard.',
            'unavailable' => ':teacher n\'est pas joignable pour le moment. Réessayez dans quelques minutes, ou écrivez-nous sur WhatsApp.',
            // La même séance ouverte sur une autre page (App\Support\Live\LivePage) : cette page s'arrête.
            'superseded' => 'Cette séance est maintenant ouverte sur un autre écran ou dans un autre onglet : elle continue là-bas.',
        ],
        // OpenAI : voix annoncée mais plus rien d'audible depuis ce temps (fin de voix perdue) : micro rouvert.
        'voice_stuck_seconds' => 4,
        // Niveau ou bilan demandé trop tôt : la première demande est refusée, le professeur continue
        // (App\Support\Live\LiveEarlyEnd ; seuils : assessment.min_verdict_seconds, lessons.early_end_extra_minutes).
        'refuse_early_end' => true,
        // Gemini : réponse pas encore finie mais plus de son reçu (trou du réseau) : le micro reste fermé au plus ce
        // temps (sans cela, l'écho de la suite de sa phrase partait vers Gemini : phrase coupée ou réponse à lui-même).
        'voice_gap_hold_seconds' => 3,
        // Accueil ou reprise demandés : micro fermé jusqu'à la première parole du professeur, au plus ce temps.
        'greeting_wait_seconds' => 5,
    ],

    /*
    | « Enregistrer ma séance » : vidéo de la séance (Teacher Joe, sa voix et celle de l'apprenant) fabriquée
    | ENTIÈREMENT dans le navigateur (public/js/session-recorder.js), jamais envoyée au serveur. Format vertical
    | 9:16, MP4 si le navigateur sait le faire, sinon WebM. Mention discrète en bas : l'adresse du site (APP_URL).
    | Lu par App\Support\SessionRecording.
    */
    'recording' => [
        'enabled' => true,
        // Évaluation gratuite et séance du jour seulement.
        'modes' => ['assessment', 'lesson'],
        'width' => 540,
        'height' => 960,
        'fps' => 24,
        // Débits modestes : la vidéo reste dans la mémoire du téléphone jusqu'au téléchargement
        // (environ 7 mégaoctets par minute).
        'video_bits_per_second' => 900_000,
        'audio_bits_per_second' => 96_000,
        // Écran partagé : le tableau de la séance en haut (part de la hauteur), Teacher Joe en dessous. Sans
        // tableau (évaluation avant le verdict) : Teacher Joe sur toute la hauteur, transition douce entre les deux.
        'board_share' => 0.45,
        'transition_ms' => 700,
        // Taille du texte anglais du tableau, en pixels : la plus grande où tout tient (de max à min), sinon les
        // éléments autour de celui qui est expliqué, à la taille « window ».
        'board_sizes' => ['max' => 34, 'min' => 24, 'window' => 28],
        // Caméra de la VIDÉO seulement (l'écran de la séance garde config/avatar.php → camera) : point visé,
        // demi-hauteur et demi-largeur visibles, en mètres. Plein cadre : du bureau à l'air au-dessus de la tête ;
        // écran partagé : tête et épaules.
        'camera_full' => ['target' => [0, 0.52, 0], 'half_height' => 0.4, 'half_width' => 0.26],
        'camera_split' => ['target' => [0, 0.62, 0], 'half_height' => 0.3, 'half_width' => 0.32],
        // Protection de la séance : au-delà de ce coût moyen par image (millisecondes), l'image de Teacher Joe dans
        // la vidéo perd de la définition, puis une image sur deux (session-recorder.js → adaptQuality).
        'frame_budget_ms' => 14,
        // Professeur 3D encore en chargement au début de l'enregistrement : la vidéo attend sa première vraie image
        // (couverture). Au-delà (3D jamais arrivée, au-delà du délai de chargement de 30 secondes de teacher-view.js),
        // elle commence avec le visage simple.
        'teacher_wait_ms' => 35_000,
        // Évaluation gratuite : la vidéo garde l'écran partagé du début à la fin. Avant le verdict, un tableau NEUTRE
        // (jamais un niveau, une question ni un score) avec le temps de l'écran ; au verdict (même instant que l'écran,
        // live-session.js → showAssessmentResult), le niveau trouvé pendant reveal_ms, puis le tableau « Découverte ».
        // Marqueurs : :teacher (config('avatar.model.name')), :minutes (assessment.max_minutes).
        'assessment_board' => [
            'phase' => 'Évaluation',
            'title' => "Évaluation gratuite d'anglais",
            'text' => 'Parlez anglais avec :teacher. Découvrez votre niveau en :minutes minutes.',
            'result_phase' => 'Résultat',
            'level_heading' => 'Votre niveau',
            'level_text' => 'Bravo ! Votre parcours commence ici.',
            'reveal_ms' => 4_000,
        ],
    ],

    /*
    | Messages vocaux préparés (bienvenue, résultat de l'évaluation) : crête de l'audio, en décibels (0 = plein
    | volume). Chaque fichier y est amené à l'enregistrement (App\Support\Speech\Loudness).
    */
    'speech' => [
        'peak_db' => -1.0,
        // Message pas encore prêt quand l'élève touche « Écouter » : texte affiché, léger geste de Teacher Joe,
        // lecture automatique dès qu'il est prêt. Jamais « Préparation du message… ».
        'wait_poll_seconds' => 3,
        'late_after_seconds' => 30,
        'give_up_after_seconds' => 180,
        'messages' => [
            'instant' => 'Un instant…',
            'late' => 'Le message arrive. Vous pouvez le lire juste en dessous : il démarrera tout seul.',
            'unavailable' => 'Le message vocal n\'est pas disponible pour le moment. Vous pouvez le lire juste en dessous.',
        ],
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
        'email' => 'contact@talkami.net',
        'whatsapp' => '+228 72 24 02 31',
        // Lien d'invitation du GROUPE WhatsApp (https://chat.whatsapp.com/…), À COMPLÉTER quand il existe.
        // Vide : le bouton WhatsApp de la page 404 ouvre une conversation avec le numéro ci-dessus.
        'whatsapp_group' => env('WHATSAPP_GROUP_URL'),
        // Réseaux de la plateforme (liens publics, sans secret) : pied de page des pages publiques
        // (social_routes, jamais l'accueil, le paiement, l'évaluation ni l'administration) et page Contact.
        // Vide : le lien n'est pas affiché.
        'tiktok' => 'https://www.tiktok.com/@talkami8',
        'whatsapp_channel' => 'https://whatsapp.com/channel/0029VbDtfS2HbFV796odSi0n',
        'social_routes' => ['plans', 'about', 'how', 'courses-help', 'interview', 'guides.*', 'country', 'contact', 'register', 'privacy', 'install'],
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
    | « story » : l'histoire vraie de la plateforme, racontée par le fondateur (page « À propos », un
    | paragraphe par ligne) ; marqueurs :platform, :founder (nom) et :price_from (formule la moins chère,
    | « 2 000 FCFA par semaine », lue dans les formules). Aucun chiffre ajouté à ceux qu'il a donnés.
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
        'story' => [
            ':founder voulait apprendre à parler anglais. Un cours privé lui coûtait 350 000 FCFA pour six mois.',
            'Il a donc conçu :platform : un professeur d\'anglais disponible chaque jour sur le téléphone, qui fait parler anglais à l\'oral, du débutant au niveau avancé, avec des formules à partir de :price_from.',
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
