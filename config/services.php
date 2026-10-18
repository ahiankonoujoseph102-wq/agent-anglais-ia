<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Intelligence et voix du professeur : API Gemini Live.
    | La clé ne quitte jamais le serveur : le navigateur reçoit seulement un
    | jeton éphémère, à usage unique et à durée limitée.
    | Les consignes du professeur sont dans config/teacher.php.
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        // Modèle audio natif de l'API Live (voir ai.google.dev/gemini-api/docs/models).
        'live_model' => env('GEMINI_LIVE_MODEL', 'gemini-3.8-live'),
        // Version de l'API pour les jetons éphémères et la connexion « Constrained ».
        'api_version' => env('GEMINI_API_VERSION', 'v1alpha'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        'websocket_url' => env('GEMINI_WEBSOCKET_URL', 'wss://generativelanguage.googleapis.com'),
        // Voix du professeur, figée dans le jeton (speechConfig.voiceConfig.prebuiltVoiceConfig.voiceName).
        // Achird : voix masculine « Friendly », calme et chaleureuse, pour Teacher Joe.
        // Liste des voix : ai.google.dev/gemini-api/docs/speech-generation#voices ; voix masculines
        // (d'après docs.cloud.google.com/text-to-speech/docs/chirp3-hd) : Achird, Iapetus, Schedar,
        // Algieba, Umbriel, Charon, Orus… (relevé du 17 octobre 2026).
        'voice' => env('GEMINI_VOICE_NAME') ?: 'Achird',
        // Voix masculines proposées sur la page d'administration « Essai des voix » (nom => description
        // officielle de ai.google.dev/gemini-api/docs/speech-generation ; genre d'après
        // docs.cloud.google.com/text-to-speech/docs/chirp3-hd, relevé du 17 octobre 2026).
        // L'administrateur en choisit une : elle remplace GEMINI_VOICE_NAME pour toutes les conversations.
        'voice_trials' => [
            'Charon' => 'Informative',
            'Orus' => 'Firm',
            'Alnilam' => 'Firm',
            'Algenib' => 'Gravelly',
            'Rasalgethi' => 'Informative',
            'Achird' => 'Friendly',
        ],
        // Modèle de synthèse vocale pour les extraits de la page « Essai des voix » (API Interactions).
        'tts_model' => env('GEMINI_TTS_MODEL', 'gemini-3.8-flash-tts'),
        // Extraits nouveaux (non gardés) qu'un administrateur peut demander par heure.
        'voice_trial_per_hour' => 12,
        // Synthèse vocale (VoiceSampler::synthesize) : délai d'attente d'une demande, en secondes ; plus court
        // pendant l'écoute (seule la salutation avec le prénom est alors générée, la page attend).
        'tts_timeout' => (int) env('GEMINI_TTS_TIMEOUT', 60),
        'tts_listen_timeout' => 20,
        // Préparation des messages communs (App\Support\Gemini\SpeechPreparer) : pause entre deux demandes
        // (limite de demandes par minute du mode gratuit), délais croissants avant un nouvel essai après une
        // erreur 429, et temps total accordé (commande de déploiement, bouton de « Diagnostic de la voix »).
        'tts_spacing_seconds' => 8,
        'tts_retry_delays' => [15, 30, 60],
        'tts_prepare_budget' => 150,
        'tts_web_budget' => 25,
        // Durée de vie maximale d'un jeton (et donc d'une connexion), en minutes.
        // Tout le temps de validité d'un jeton est décompté, même si le navigateur
        // disparaît : une valeur courte limite la perte en cas de coupure.
        // La connexion Live dure de toute façon environ 10 min : le navigateur
        // en redemande une, en reprenant la conversation, tant qu'il reste du temps.
        'token_minutes' => (int) env('GEMINI_TOKEN_MINUTES', 5),
        // Délai laissé au navigateur pour ouvrir la connexion avec le jeton, en secondes.
        'connect_window_seconds' => 60,
        // Nombre maximal de connexions (jetons) par séance ou par évaluation.
        'max_connections' => (int) env('GEMINI_MAX_CONNECTIONS', 8),
        // Détection de parole par Google (realtimeInputConfig.automaticActivityDetection,
        // figée dans le jeton). Sensibilités basses : le bruit ne coupe pas le professeur
        // et une courte hésitation ne termine pas la phrase de l'apprenant.
        // Source : ai.google.dev/api/live (relevé du 17 octobre 2026).
        'activity_detection' => [
            'start_sensitivity' => 'START_SENSITIVITY_LOW',
            'end_sensitivity' => 'END_SENSITIVITY_LOW',
            // Son gardé avant le début détecté de la parole, en millisecondes.
            'prefix_padding_ms' => (int) env('GEMINI_PREFIX_PADDING_MS', 300),
            // Silence nécessaire pour considérer que l'apprenant a fini de parler.
            'silence_duration_ms' => (int) env('GEMINI_SILENCE_DURATION_MS', 1200),
        ],
        // Tâches en texte (rédaction de messages, présentation du programme) :
        // modèle léger et économique, appelé depuis le serveur (API Interactions).
        // Source : ai.google.dev/gemini-api/docs/models (relevé d'octobre 2026).
        'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-3.5-flash-lite'),
        'text_api_version' => env('GEMINI_TEXT_API_VERSION', 'v1beta'),
        // Tarifs payants en dollars US par million de jetons, pour estimer le coût
        // dans l'espace administrateur (tarif « audio », le plus cher).
        // Source : ai.google.dev/gemini-api/docs/pricing (relevé d'octobre 2026).
        'pricing' => [
            'input_per_million' => (float) env('GEMINI_PRICE_INPUT', 3.00),
            'output_per_million' => (float) env('GEMINI_PRICE_OUTPUT', 12.00),
        ],
    ],

];
