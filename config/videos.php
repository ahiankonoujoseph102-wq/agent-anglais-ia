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

    'per_day' => 3,

    // Durée parlée visée d'une vidéo, en secondes (le script est écrit pour cette durée, la phrase de fin en plus).
    'duration_seconds' => (int) env('VIDEO_DURATION_SECONDS', 120),

    // Mots parlés par seconde pour viser cette durée (français expliqué calmement, anglais lent).
    'words_per_second' => 2.1,

    // Niveaux visés, dans l'ordre du programme des sujets.
    'levels' => ['A1', 'A2', 'B1'],

    // Fin obligatoire, dite et affichée. :site = adresse du site tirée de APP_URL (talkami.laravel.cloud, puis
    // talkami.net au changement de domaine, sans rien toucher ici).
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
        'timeout' => 60,
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

    // Temps accordé à une préparation (videos:prepare), en secondes : la suite reprend au passage suivant.
    'prepare_budget' => 50,

    // Après tant d'échecs de préparation, la vidéo est marquée « en échec » (« Réessayer » dans l'administration).
    'max_attempts' => 4,
    'retry_minutes' => 10,

    // Fabrication (GitHub Actions) : une vidéo prise mais non rendue après ce temps peut être reprise.
    'render_timeout_minutes' => 60,

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
            'keys' => ['evaluation', 'talkami', 'questions'],
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
        'audio' => ['target_lufs' => -14, 'limit' => 0.8, 'limiter_makeup_db' => 0.8],
        // Garde-fou des lèvres (tools/video/lip-check.mjs) : sinon, la vidéo n'est pas déposée.
        'lips' => ['min_correlation' => 0.5, 'min_open' => 0.08],
    ],
];
