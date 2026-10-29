<?php

/*
|--------------------------------------------------------------------------
| Vidéos de leçon de Teacher Joe (TikTok, chaîne WhatsApp)
|--------------------------------------------------------------------------
|
| Chaque jour, « per_day » vidéos verticales : Laravel choisit le sujet (resources/videos/topics.php, jamais deux
| fois), fait écrire le script (modèle texte : Gemini, puis OpenAI) et prépare la voix (synthèse vocale : un seul
| fournisseur et une seule voix par vidéo), le tout gardé sur le disque privé. La fabrication de l'image (Teacher
| Joe filmé image par image, sous-titres, MP4) se fait HORS de Laravel Cloud, par GitHub Actions
| (.github/workflows/videos.yml, tools/video/), qui dépose la vidéo dans le bucket privé avec le jeton secret
| VIDEO_UPLOAD_TOKEN. Aucune clé d'intelligence artificielle ne sort de Laravel Cloud.
|
*/

return [

    // Vidéos de PROMOTION (leçons pour TikTok et la chaîne WhatsApp) par jour, par défaut. La page « Vidéos » peut
    // le changer sans redéploiement (réglage « videos.per_day », 1 à 3) : 1 quand le dépôt est privé, pour tenir dans
    // les minutes gratuites de GitHub Actions.
    'per_day' => 2,
    'per_day_choices' => [1, 2, 3],

    // Durée parlée visée d'une vidéo, en secondes (le script est écrit pour cette durée, la phrase de fin en plus).
    'duration_seconds' => (int) env('VIDEO_DURATION_SECONDS', 120),

    // Mots parlés par seconde pour viser cette durée (français expliqué calmement, anglais lent).
    'words_per_second' => 2.1,

    // Niveaux visés, dans l'ordre du programme des sujets.
    'levels' => ['A1', 'A2', 'B1'],

    // Fin obligatoire, dite et affichée. :site = adresse du site réglée dans la page « Vidéos » (talkami.net).
    'closing' => 'Et pour aller plus loin, évaluez gratuitement votre niveau d\'anglais sur :site',

    // Fin de CHAQUE vidéo, dans cet ordre, dite et sous-titrée (environ 10 à 12 secondes) : la question du jour
    // (écrite avec le script, réponse en une phrase en commentaire), cette phrase pour s'abonner, puis « closing »
    // avec l'adresse du site (réglage de la page « Vidéos » : sans elle, la fabrication est bloquée).
    'ending' => [
        'subscribe' => 'Si ça vous a aidé, abonnez-vous, aimez et partagez !',
    ],

    // Rappel affiché au-dessus du texte de publication (obligation de TikTok pour un contenu généré par IA).
    'ai_label_reminder' => 'Avant de publier sur TikTok, activez « Contenu généré par IA » (Plus d\'options, puis « Étiquette de contenu IA »).',

    /*
    | Voix des vidéos. Ordre des fournisseurs : VIDEO_TTS_PROVIDERS (absente : le même ordre que les messages
    | vocaux, TTS_PROVIDERS). Voix : VIDEO_GEMINI_VOICE (absente : la voix du professeur choisie dans « Essai des
    | voix ») et VIDEO_OPENAI_VOICE (absente : OPENAI_TTS_VOICE, cedar). Changer une voix ou l'ordre ne touche que
    | les vidéos préparées ensuite.
    */
    'voice' => [
        'providers' => array_values(array_filter(array_map('trim', explode(',', (string) (env('VIDEO_TTS_PROVIDERS') ?: env('TTS_PROVIDERS', 'gemini,openai')))))),
        'gemini_voice' => env('VIDEO_GEMINI_VOICE') ?: null,
        'openai_voice' => env('VIDEO_OPENAI_VOICE') ?: null,
        'style' => 'Lis comme un professeur d\'anglais chaleureux et patient qui parle à des débutants francophones d\'Afrique. Le français : clair, calme, jamais rapide. Chaque mot ou phrase en anglais : prononciation AMÉRICAINE, lente et très articulée, avec une courte pause avant et après.',
        // Silence ajouté entre deux parties du script, en millisecondes.
        'gap_ms' => 450,
        // Délai d'une demande, en secondes.
        'timeout' => 90,
        // Parties du script dites dans UNE demande (au plus tant de caractères, environ 1 minute 30 de voix), puis
        // recoupées : 2 demandes environ par vidéo au lieu d'une par partie (limite gratuite de Gemini : 10 par jour).
        'group_characters' => 1500,
    ],

    // Essai comparatif des voix (page « Vidéos ») : le même passage, en français avec des phrases en anglais, dit par
    // chaque voix ; la voix choisie est gardée dans les réglages (table settings), sans redéploiement.
    'voice_trial' => [
        'voices' => [
            'gemini' => ['Achird', 'Charon', 'Orus', 'Iapetus'],
            'openai' => ['cedar', 'marin', 'ash'],
        ],
        'text' => 'Bonjour ! Aujourd\'hui, on apprend à se présenter en anglais. Pour dire votre nom, on dit : My name is Afi. Répétez après moi : My name is Afi. Attention : en français, on dit « j\'ai vingt-cinq ans ». Mais en anglais, on dit : I\'m twenty-five. Très bien ! Nice to meet you!',
        // Nouveaux extraits par heure et par administrateur.
        'per_hour' => 20,
    ],

    /*
    | Vidéo du jour du CLUB DES ABONNÉS (App\Support\Videos\ClubVideos) : une par jour, en plus des vidéos de
    | promotion, fabriquée en priorité. Thème selon le jour (1 = lundi … 7 = dimanche). Teacher Joe parle à ses
    | abonnés ; aucune fin publicitaire. Rien n'est publié par le site : bloc « Club des abonnés » de la page
    | « Vidéos » (aperçu, « Télécharger pour WhatsApp », texte signé à copier), visible aussi par un assistant.
    */
    'club' => [
        // Durée visée : 3 minutes, jamais moins (« min_seconds »). La parole seule vise « speech_seconds » (le reste :
        // silences pour répéter et entre les parties) ; le script est refusé hors de 90 % à 125 % de ces mots. Une
        // voix un peu courte reçoit des silences de répétition plus longs (« max_pad_seconds » en tout au plus,
        // VideoVoice) ; plus courte encore : nouveau script.
        'duration_seconds' => 180,
        'speech_seconds' => 150,
        'min_seconds' => 180,
        'max_pad_seconds' => 30,
        // Silence laissé après « Répétez après moi : … » pour que l'abonné répète à voix haute (millisecondes).
        'repeat_pause_ms' => 2500,
        // Fabrication plus légère que les vidéos de promotion (mesuré le 29 octobre 2026 : environ 60 % du temps) :
        // Teacher Joe rendu en 396 × 704 puis agrandi, 20 images par seconde. Remplace ces réglages de « render »
        // pour le Club seulement. 3 minutes sur une machine lente de GitHub (environ 0,9 seconde par image) :
        // environ 55 minutes, sous le temps accordé (72).
        'render' => ['avatar_width' => 396, 'avatar_height' => 704, 'fps' => 20],
        'level' => 'A2',
        // Un élément (expression, son, situation, erreur, piège) n'est pas repris avant tant de semaines.
        'repeat_weeks' => 8,
        // Lundi : erreurs des abonnés payants sur tant de jours ; une erreur n'est retenue que si au moins
        // « min_people » personnes différentes l'ont faite (même seuil pour donner les chiffres du samedi).
        'errors_days' => 14,
        'min_people' => 3,
        // Samedi : chiffres globaux sur tant de jours (séances d'au moins « min_session_seconds »).
        'stats_days' => 7,
        'min_session_seconds' => 60,
        'progress_sentence' => 'Ces :days derniers jours, les membres du Club ont parlé anglais :minutes minutes avec moi, en :sessions séances. Bravo à vous !',
        'themes' => [
            1 => ['key' => 'errors', 'label' => 'Ce que j\'ai remarqué chez vous'],
            2 => ['key' => 'expression', 'label' => 'L\'expression du jour'],
            3 => ['key' => 'sound', 'label' => 'Le son difficile'],
            4 => ['key' => 'dialogue', 'label' => 'Mini-dialogue de situation'],
            5 => ['key' => 'challenge', 'label' => 'Le défi de la semaine'],
            6 => ['key' => 'progress', 'label' => 'Les progrès du Club'],
            7 => ['key' => 'revision', 'label' => 'La révision de la semaine'],
        ],
        // Lundi sans assez d'erreurs communes : un piège des francophones, présenté comme tel.
        'pitfall_label' => 'Les pièges des francophones',
        // Dite et sous-titrée à la fin de chaque vidéo du Club ; signature du texte à copier.
        'closing' => 'Répondez-moi dans le groupe du Club. À demain !',
        'signature' => '— :teacher',
    ],

    // Temps accordé à une préparation (videos:prepare), en secondes : la suite reprend au passage suivant.
    'prepare_budget' => 50,

    // Après tant d'échecs de préparation, la vidéo est marquée « en échec » (« Réessayer » dans l'administration).
    'max_attempts' => 4,
    'retry_minutes' => 10,

    // Fabrication (GitHub Actions) : une vidéo prise mais non rendue après ce temps peut être reprise.
    'render_timeout_minutes' => 90,

    // Jeton de GitHub Actions (php artisan videos:generate-token, variable VIDEO_UPLOAD_TOKEN). Absent : l'API refuse tout.
    'token' => env('VIDEO_UPLOAD_TOKEN') ?: null,

    // Adresses signées (dépôt de la vidéo dans le bucket, aperçu, téléchargement), en minutes.
    'upload_url_minutes' => 60,
    'preview_url_minutes' => 30,

    // Taille maximale d'une vidéo reçue, en mégaoctets.
    'max_megabytes' => 60,

    /*
    | Vidéos de PRÉSENTATION du site (resources/videos/presentations.php, App\Support\Videos\PresentationVideos) :
    | une grande vidéo et six courtes. Voix préparée par Laravel (même fournisseur et même voix que les vidéos de leçon,
    | mais un ton de présentation, plus naturel), fabrication par GitHub Actions (.github/workflows/presentations.yml,
    | lancé à la main) : écran du site enregistré sur un site LOCAL de démonstration, jamais en production.
    */
    'presentations' => [
        // Versions dites par un autre professeur proposé (clé « rose-talkami »…) : mêmes scripts accordés au féminin
        // (teachers.list.rose.presentation_texts), son modèle 3D, sa voix, comptes de démonstration avec elle.
        'teacher_versions' => [
            'rose' => ['talkami', 'compte', 'evaluation', 'formules', 'paiement', 'seance', 'mes-cours'],
        ],
        // Site de démonstration (tools/presentation) : professeur choisi par les comptes fictifs (PRESENTATION_TEACHER,
        // donné par le travail de fabrication ; absente : Teacher Joe).
        'demo_teacher' => env('PRESENTATION_TEACHER'),
        'voice_style' => 'Lis en français comme un professeur chaleureux qui présente son site à un ami : clair, souriant, à un rythme naturel, sans traîner. Les mots anglais avec une prononciation américaine.',
        'gap_ms' => 300,
        // Version légère (WhatsApp) : taille au plus, en mégaoctets ; « long » pour la grande vidéo.
        'light_megabytes' => ['short' => 16, 'long' => 100],
        // Au-delà de cette durée (secondes), c'est la grande vidéo (limite de la version légère « long »).
        'long_after_seconds' => 120,
        'max_megabytes' => 400,
        // Mise en page (lue par tools/presentation) : image 1080 × 1920, 30 images par seconde ; zone sûre de TikTok
        // (rien d'important dans les 10 % du haut, les 20 % du bas, les 12 % de droite).
        'render' => [
            'width' => 1080,
            'height' => 1920,
            'fps' => 30,
            'viewport' => ['width' => 390, 'height' => 844],
            'phone' => ['x' => 40, 'y' => 200, 'width' => 530],
            'medallion' => ['x' => 625, 'y' => 1080, 'size' => 300],
            // Partie de l'image de Teacher Joe (540 × 960) montrée dans le médaillon : son visage.
            'medallion_crop' => ['x' => 118, 'y' => 285, 'size' => 300],
            'chapter' => ['x' => 625, 'y' => 330, 'width' => 310, 'size' => 44],
            'subtitles' => ['top' => 1408, 'size' => 48, 'chars' => 56, 'side_margin' => 40, 'right_margin' => 140],
            'video_bitrate' => '5000k',
            'audio_bitrate' => '128k',
            // Son : même chaîne que les vidéos de leçon, limiteur un peu plus bas (crête sous 0 dB après l'AAC).
            'audio' => ['target_lufs' => -14, 'limit' => 0.7, 'limiter_makeup_db' => 2.5],
        ],
        // Page publique « Comment ça marche » (App\Support\Videos\SiteVideos) : ces vidéos, dans cet ordre, en version
        // « site » (480 × 854, sous-titres déjà dans l'image), fabriquée par GitHub Actions à partir de la version
        // WhatsApp (tools/presentation/site-version.mjs). Rien n'est chargé avant le toucher sur « Regarder ».
        'site' => [
            'keys' => ['evaluation', 'rose-evaluation', 'talkami', 'questions'],
            'width' => 480,
            'height' => 854,
            'video_kbps' => 380,
            'audio_kbps' => 64,
            // Seconde de la vidéo prise pour l'image d'aperçu (JPEG, environ 30 kilooctets).
            'poster_second' => 4,
            // Durée de l'adresse signée donnée au toucher sur « Regarder » (la lecture et l'avance rapide en dépendent).
            'url_minutes' => 180,
            'max_megabytes' => 60,
            // Phrase sous le titre de chaque vidéo.
            'intros' => [
                'evaluation' => 'Comment se passe votre évaluation orale gratuite avec :teacher, du début à la fin.',
                // Version d'un autre professeur (« rose-… ») : :teacher est SON nom ; montrée seulement s'il est proposé.
                'rose-evaluation' => 'La même évaluation orale gratuite, avec :teacher : vous choisissez votre professeur avant de commencer.',
                'talkami' => 'Tout :platform en quelques minutes : le compte, l\'évaluation, les formules, le paiement et une séance.',
                'questions' => ':teacher répond aux questions qu\'on se pose avant de commencer : oser parler, le temps, le prix, le paiement, l\'application, « Mes cours »…',
            ],
            // Page des formules : une ligne de texte vers cette vidéo (seulement si sa version « site » est prête).
            'plans_link' => 'questions',
        ],
        // Publicités (« layout » « ad » d'un script) : remplace ces réglages de « render », pour ces vidéos seulement.
        // Zone libre des boutons de Meta (14 % en haut, 35 % en bas) et de TikTok (240 pixels en haut, 660 en bas,
        // colonne de 180 pixels à droite à partir de 840) : texte entre 300 et 1 240 pixels de haut, sous-titres à
        // gauche de 760 pixels, médaillon en haut à droite.
        'ad' => [
            'subtitles' => ['top' => 1100, 'size' => 52, 'chars' => 34, 'side_margin' => 120, 'right_margin' => 320],
            // Bandeau en haut, au-dessus de la colonne de boutons : toute la largeur moins 120 pixels de chaque côté.
            'card' => ['top' => 300, 'margin_left' => 120, 'margin_right' => 120],
            'medallion' => ['x' => 700, 'y' => 380, 'size' => 260],
            'chapter' => ['x' => 625, 'y' => 760, 'width' => 310, 'size' => 44],
            // Bruitages (ffmpeg, aucune licence), sous la voix : décibels et heure de départ avant la phrase (secondes).
            'sfx' => ['volume_db' => -14, 'lead' => 0.05],
        ],
    ],

    // Mise en page (lue par tools/video/render.mjs) : gros plan de Teacher Joe dans la salle de classe des séances,
    // rendu plus petit puis agrandi (plus rapide sur GitHub), texte en pleine définition. Coordonnées du décor en
    // mètres de la scène 3D (le visage est vers x = 0, y = 0,75 ; le mur à z = −0,6).
    'render' => [
        'width' => 1080,
        'height' => 1920,
        'fps' => 25,
        'scene' => 'classroom',
        'avatar_width' => 540,
        'avatar_height' => 960,
        'avatar_top' => 0,
        // Lissage des bords : coûteux sans carte graphique ; l'agrandissement adoucit déjà l'image.
        'antialias' => false,
        // Ouverture de la mâchoire selon le volume de la voix (0,3 en séance).
        'jaw' => 0.45,
        // Caméra : visage et épaules, regard vers la caméra (hauteur des yeux).
        'camera' => ['target' => [0, 0.61, 0], 'half_height' => 0.40, 'half_width' => 0.32, 'eye_height' => 0.68],
        // Tableau au-dessus de l'épaule gauche, assez large pour être lu sur téléphone ; fenêtre entière à droite.
        'board' => ['left' => -0.37, 'right' => 0.09, 'bottom' => 0.945, 'top' => 1.08, 'lines' => ["Let's talk!"]],
        'window' => ['x' => 0.31, 'y' => 0.99, 'w' => 0.14, 'h' => 0.28],
        'background' => '#0E2A5C',
        'accent' => '#FF7A1A',
        // Nom du professeur en filigrane, en petit (VIDEO_WATERMARK ; absente : config('avatar.model.name')).
        'watermark' => env('VIDEO_WATERMARK') ?: null,
        // Textes, en pixels de l'image finale : titre discret sous les onglets de TikTok, sous-titres sous le menton,
        // marges qui évitent les icônes de droite, rien dans le bas de l'image (légende de TikTok).
        'text' => [
            'title_top' => 175,
            'title_size' => 46,
            'subtitle_top' => 1180,
            'subtitle_size' => 66,
            'closing_size' => 60,
            'site_size' => 84,
            'side_margin' => 120,
            'watermark_top' => 700,
            'watermark_right' => 140,
            'watermark_size' => 36,
        ],
        'video_bitrate' => '900k',
        'audio_bitrate' => '128k',
        // Son : compresseur léger, gain calculé d'après la sonie (visée : −14 LUFS), limiteur (crête sous −1 dB
        // après la compression AAC). La sonie finale est mesurée et montrée dans la page « Vidéos ».
        // Le limiteur travaille à 48 kHz (avant : à la fréquence de la voix, 24 kHz, puis passage à 44,1 kHz et
        // compression AAC, qui faisaient dépasser 0 dB entre deux échantillons). Après l'encodage, sonie et crête
        // vraie sont mesurées ; hors de « tolerance_lu » ou au-dessus de « max_peak_db », le son seul est refait.
        'audio' => ['target_lufs' => -14, 'limit' => 0.708, 'limiter_makeup_db' => 1.5, 'max_peak_db' => -1, 'tolerance_lu' => 1, 'rate' => 48000],
        // Garde-fou des lèvres (tools/video/lip-check.mjs) : sinon, la vidéo n'est pas déposée.
        'lips' => ['min_correlation' => 0.5, 'min_open' => 0.08],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalogue des écrans (pour un montage)
    |--------------------------------------------------------------------------
    | UNE vidéo muette (1080 × 1920, 30 images par seconde) de tous les écrans du site de DÉMONSTRATION, bout à bout,
    | séparés par une seconde de noir, et la voix de Teacher Joe du passage où il parle, dans un fichier à part
    | (tools/presentation/catalogue.mjs, workflow « Catalogue des écrans » lancé à la main). Gardée sur le disque
    | privé, téléchargée depuis la page « Vidéos » de l'administration (App\Support\Videos\ScreenCatalogue).
    */
    'catalogue' => [
        // Voix de Teacher Joe : le début (plans où il parle seul) de cette vidéo de présentation, déjà préparée.
        'voice_key' => 'questions',
        'voice_max_seconds' => 15.5,
        'max_megabytes' => 600,
        'download_url_minutes' => 60,
    ],
];
