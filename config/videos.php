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
    'closing' => 'Pour aller plus loin, évaluez gratuitement votre niveau d\'anglais sur :site',

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

    // Mise en page (lue par tools/video/render.mjs) : image finale, zone de Teacher Joe et rendu.
    'render' => [
        'width' => 1080,
        'height' => 1920,
        'fps' => 25,
        // Teacher Joe est rendu plus petit puis agrandi (plus rapide sur GitHub) ; texte en pleine définition.
        'avatar_width' => 720,
        'avatar_height' => 640,
        'avatar_top' => 340,
        'background' => '#0E2A5C',
        'accent' => '#FF7A1A',
        // Débit vidéo visé : moins de 16 mégaoctets pour 2 minutes (WhatsApp).
        'video_bitrate' => '850k',
        'audio_bitrate' => '96k',
    ],
];
