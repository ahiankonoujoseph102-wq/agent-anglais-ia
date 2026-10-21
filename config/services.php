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
    // Google Search Console : code de la balise « google-site-verification » (méthode « Balise HTML »).
    // Vide : aucune balise. Ce n'est pas un secret (il est visible dans la page).
    'google' => [
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

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
        // Coût estimé d'une minute de voix de synthèse, en dollars US (page « Consommation ») : 0 tant que
        // le mode gratuit de Google est utilisé.
        'tts_cost_per_minute' => (float) env('GEMINI_TTS_COST_PER_MINUTE', 0),
        // Durée de vie maximale d'un jeton (et donc d'une connexion), en minutes.
        // Une séance jamais reprise (navigateur disparu) est décomptée jusqu'à
        // l'expiration de son dernier jeton : une valeur courte limite cette perte.
        // Si la connexion est coupée puis revient, le temps de séance est mis en
        // pause (IsLiveConversation) : aucune minute perdue.
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
            // Fin de parole : HIGH, la valeur par défaut de Google (« ends speech more often »). Avec LOW, un bruit de
            // fond continu pouvait empêcher la fin du tour ; les hésitations restent protégées par silence_duration_ms.
            'end_sensitivity' => env('GEMINI_END_OF_SPEECH_SENSITIVITY', 'END_SENSITIVITY_HIGH'),
            // Son gardé avant le début détecté de la parole, en millisecondes.
            'prefix_padding_ms' => (int) env('GEMINI_PREFIX_PADDING_MS', 300),
            // Silence nécessaire pour considérer que l'apprenant a fini de parler : voir services.live.silence_ms
            // (réglé selon le niveau, commun à Gemini et à OpenAI).
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

    // Synthèse vocale des messages (bienvenue, résultat de l'évaluation) : ordre des fournisseurs, le premier
    // d'abord, le suivant en secours (« gemini,openai », ou « openai » seul). Un fournisseur sans clé est
    // ignoré. Les séances orales en direct ne passent pas par ici (toujours Gemini Live).
    'tts' => [
        'providers' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('TTS_PROVIDERS', 'gemini,openai')))))),
    ],

    // Modèle texte (captures de paiement, « Mes cours », estimation du niveau, rédactions) : ordre des
    // fournisseurs, le premier d'abord, le suivant en secours pour LA MÊME demande (« gemini,openai », ou
    // « openai » seul). Un fournisseur sans clé est ignoré.
    'text' => [
        'providers' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('TEXT_PROVIDERS', 'gemini,openai')))))),
        // Après une limite (429) ou une clé refusée, le fournisseur est mis de côté pendant ce temps.
        'pause_seconds' => 120,
        // Tarifs en dollars US par million de jetons, pour l'estimation de la page « Consommation ».
        // Gemini : 0 tant que le palier gratuit est utilisé. OpenAI : tarif publié de gpt-6-luna
        // (developers.openai.com/api/docs/pricing, relevé du 18 octobre 2026).
        'pricing' => [
            'gemini' => [
                'input' => (float) env('GEMINI_TEXT_INPUT_PRICE', 0),
                'output' => (float) env('GEMINI_TEXT_OUTPUT_PRICE', 0),
            ],
            'openai' => [
                'input' => (float) env('OPENAI_TEXT_INPUT_PRICE', 0.10),
                'output' => (float) env('OPENAI_TEXT_OUTPUT_PRICE', 0.50),
            ],
        ],
    ],

    // Séances orales en direct (évaluation, séance du jour, « Mes cours », bilan de niveau) : ordre des
    // fournisseurs (« gemini,openai », ou « openai » seul). Le fournisseur est choisi au DÉBUT de la séance
    // et ne change jamais ensuite ; un fournisseur sans clé est ignoré.
    'live' => [
        'providers' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('LIVE_PROVIDERS', 'gemini,openai')))))),
        // Après une limite (429, limite du jour) ou une clé refusée, le fournisseur est mis de côté pendant ce temps.
        'pause_seconds' => 300,
        // Une séance peut changer de fournisseur seulement dans ce délai après son début (rien n'a été dit).
        'switch_window_seconds' => 120,
        // Coût estimé par minute de séance, pour la page « Consommation » (Gemini : 0 en mode gratuit).
        'gemini_cost_per_minute' => (float) env('GEMINI_LIVE_COST_PER_MINUTE', 0),
        // Silence attendu avant de considérer que l'élève a fini de parler, en millisecondes, selon le niveau (fixé au
        // début de la séance, chez Gemini et chez OpenAI). Google recommande 500 à 800 (valeur interne d'environ 800 ;
        // au-delà, la réponse arrive plus tard : ai.google.dev/gemini-api/docs/live-guide, lu le 21 octobre 2026).
        // A1 et A2 : un peu plus, les débutants hésitent. « unknown » : évaluation gratuite.
        'silence_ms' => [
            'A1' => (int) env('LIVE_BEGINNER_SILENCE_MS', 1000),
            'A2' => (int) env('LIVE_BEGINNER_SILENCE_MS', 1000),
            'default' => (int) env('LIVE_SILENCE_MS', 800),
        ],
    ],

    // OpenAI : secours de la synthèse vocale (API Audio, « speech »), du modèle texte (API Responses) et des
    // séances en direct (API Realtime, WebRTC).
    // Source : platform.openai.com/docs/guides/text-to-speech et /docs/models/gpt-4o-mini-tts (relevé d'octobre 2026).
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // Modèle qui suit des consignes de ton (« instructions »).
        'tts_model' => env('OPENAI_TTS_MODEL') ?: 'gpt-4o-mini-tts',
        // Cedar : voix masculine, posée et chaleureuse, recommandée par OpenAI pour la meilleure qualité.
        'tts_voice' => env('OPENAI_TTS_VOICE') ?: 'cedar',
        // Vitesse des messages vocaux (bienvenue, résultat de l'évaluation) : paramètre speed de /audio/speech
        // (0,25 à 4, 1 = normal ; documentation lue le 21 octobre 2026). Messages en français : juste un peu plus
        // calme que la normale. Changer cette valeur fait préparer de nouveaux fichiers (empreinte).
        'tts_speed' => (float) (env('OPENAI_TTS_SPEED') ?: 0.95),
        // Pause entre deux demandes pendant la préparation, en secondes (limites bien plus larges que Gemini).
        'tts_spacing_seconds' => 1,
        // Environ 0,015 $ par minute de voix générée (tarif publié pour gpt-4o-mini-tts).
        'tts_cost_per_minute' => (float) env('OPENAI_TTS_COST_PER_MINUTE', 0.015),
        // Modèle texte de secours : le plus économique des modèles actuels qui lisent images et PDF et
        // respectent un schéma JSON. Source : developers.openai.com/api/docs/models/gpt-6-luna (octobre 2026).
        'text_model' => env('OPENAI_TEXT_MODEL') ?: 'gpt-6-luna',
        // Réflexion pour les lectures (captures, leçons) : none, low, medium, high.
        'text_reasoning' => env('OPENAI_TEXT_REASONING') ?: 'low',
        // Séances en direct : modèle de conversation vocale, voix masculine, transcription des deux voix.
        // Source : developers.openai.com/api/docs/models et /api/docs/guides/realtime-webrtc (octobre 2026).
        'realtime_model' => env('OPENAI_REALTIME_MODEL') ?: 'gpt-realtime-2.1-mini',
        'realtime_voice' => env('OPENAI_REALTIME_VOICE') ?: 'cedar',
        // Vitesse de la voix selon le niveau de l'élève (audio.output.speed : 0,25 à 1,5, 1 = normal ; traitement de
        // l'audio après coup, documentation lue le 21 octobre 2026). « unknown » : évaluation gratuite (niveau pas encore
        // connu). Pas en dessous de 0,85 : la voix deviendrait traînante ; le vrai ralentissement vient des consignes
        // (teacher.pace). Gemini Live n'a aucun réglage de vitesse.
        'realtime_speed' => [
            'unknown' => 0.9,
            'A1' => 0.85,
            'A2' => 0.85,
            'B1' => 0.92,
            'B2' => 0.95,
            'C1' => 1.0,
            'C2' => 1.0,
        ],
        'realtime_transcription_model' => env('OPENAI_REALTIME_TRANSCRIPTION_MODEL') ?: 'gpt-4o-mini-transcribe',
        // Transcription de la voix de l'apprenant chez OpenAI (sous-titres « Vous » et texte des conversations) :
        // facturée à part, au tarif du modèle de transcription (gpt-4o-mini-transcribe : 0,003 $ par minute de voix
        // de l'apprenant, le moins cher, developers.openai.com/api/docs/pricing, relevé du 18 octobre 2026). La voix de
        // Teacher Joe est transcrite avec sa réponse, sans modèle en plus. « false », « 0 », « off » ou « no » la coupent.
        'realtime_input_transcription' => ! in_array(env('OPENAI_REALTIME_INPUT_TRANSCRIPTION'), [false, '0', 'off', 'no'], true),
        // Réflexion du modèle (modèles « 2 » et suivants) : minimal, low, medium ; vide : réglage d'OpenAI.
        // « minimal » : la réponse la plus rapide (réflexion du modèle réduite au minimum).
        'realtime_reasoning' => env('OPENAI_REALTIME_REASONING', 'minimal'),
        // Détection de parole peu sensible, comme chez Gemini ; silence attendu : le même que chez Gemini (selon le
        // niveau, services.live.silence_ms, repris de la configuration de la séance).
        'realtime_vad' => [
            'threshold' => (float) env('OPENAI_REALTIME_VAD_THRESHOLD', 0.6),
            'prefix_padding_ms' => (int) env('GEMINI_PREFIX_PADDING_MS', 300),
        ],
        // Environ 0,04 $ par minute de séance avec gpt-realtime-2.1-mini (audio 10 $ / 20 $ le million de
        // jetons, contexte relu en cache, transcription comprise) : estimation pour « Consommation ».
        'realtime_cost_per_minute' => (float) env('OPENAI_REALTIME_COST_PER_MINUTE', 0.04),
        // Tarif par million de jetons (audio) de gpt-realtime-2.1-mini, pour le coût par apprenant et par séance
        // de « Consommation » (developers.openai.com/api/docs/models/gpt-realtime-2.1-mini, relevé du 18 octobre
        // 2026 : audio 10 $ en entrée, 20 $ en sortie ; texte 0,60 $ / 2,40 $). Les jetons rapportés mêlant audio
        // et texte, le tarif audio donne une estimation prudente (un peu haute).
        'realtime_pricing' => [
            'input_per_million' => (float) env('OPENAI_REALTIME_INPUT_PRICE', 10.00),
            'output_per_million' => (float) env('OPENAI_REALTIME_OUTPUT_PRICE', 20.00),
        ],
    ],

];
