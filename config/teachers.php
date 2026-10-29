<?php

/*
|--------------------------------------------------------------------------
| Professeurs
|--------------------------------------------------------------------------
|
| Liste des professeurs avec qui l'apprenant peut parler (App\Support\Teachers\Teachers). Tout le reste est commun à
| tous les professeurs : niveau, programme, progression, erreurs à revoir, historique, temps du jour, consignes
| pédagogiques (config/teacher.php, marqueur :teacher).
|
| Pour chaque professeur :
|   avatar            bloc de config/avatar.php (nom affiché, modèle 3D, cadrage) ; le NOM vient de là
|   ready             le choix de ce professeur est montré aux apprenants (voix ET visage prêts, validés par le
|                     fondateur) ; faux : seul l'administrateur l'entend (« Essai des voix », essai de 30 secondes)
|   voice_setting     réglage (table settings) de la voix Gemini choisie dans « Essai des voix »
|   gemini_voice      voix Gemini Live par défaut ; null : services.gemini.voice
|   gemini_trials     voix Gemini proposées dans « Essai des voix » (nom => description officielle de Google) ;
|                     null : services.gemini.voice_trials
|   realtime_setting  réglage de la voix OpenAI choisie dans « Essai des voix » (null : aucun choix proposé)
|   realtime_voice    voix OpenAI Realtime par défaut ; null : services.openai.realtime_voice
|   openai_trials     voix OpenAI proposées dans « Essai des voix » (nom => notre description, OpenAI n'en publie pas)
|   virtual_teacher   ce que le professeur dit être quand on le lui demande (marqueur :virtual_teacher)
|   pronoun, the_teacher  « il » / « elle », « le professeur » / « la professeure » (textes de l'écran de séance)
|   personality       court bloc de personnalité ajouté après le personnage commun (vide : rien)
|   sample_style      ton des extraits de « Essai des voix » ; null : teacher.voice_trial.style
|   face              visage simple (SVG) de l'écran de séance : joe ou rose
|   portrait          portrait fixe (public/) des pages publiques
|   public_line       une phrase qui le présente sur les pages publiques (jamais une promesse)
|   speech_style      remplacements dans le ton des messages vocaux (écrit pour Teacher Joe : « male teacher »)
|   welcome_paragraphs  paragraphes du message de bienvenue propres au professeur (accords au féminin)
|
| RETOUR À TEACHER JOE SEUL : mettre « ready » de Teacher Rose à false (ou retirer son bloc), puis déployer. Le choix
| disparaît ; un apprenant qui l'avait choisie retrouve Teacher Joe dès sa prochaine séance (Teachers::forUser).
| Une clé publiée n'est jamais renommée : elle est gardée sur le compte (users.teacher) et dans chaque séance.
|
*/

return [

    // Professeur par défaut : celui d'un apprenant qui n'a rien choisi, de l'évaluation gratuite et des séances d'avant.
    'default' => 'joe',

    'list' => [
        'joe' => [
            'avatar' => 'model',
            'ready' => true,
            'voice_setting' => 'teacher_voice',
            'gemini_voice' => null,
            'gemini_trials' => null,
            'realtime_setting' => null,
            'realtime_voice' => null,
            'openai_trials' => [],
            'virtual_teacher' => 'un professeur virtuel',
            'pronoun' => 'il',
            'the_teacher' => 'le professeur',
            'personality' => '',
            'sample_style' => null,
            'face' => 'joe',
            'portrait' => 'images/teacher-joe.webp',
            'public_line' => 'Calme et patient, il vous fait parler pas à pas.',
        ],

        'rose' => [
            'avatar' => 'rose',
            // Visage (3D et visage simple) validé par le fondateur le 29 octobre 2026. Retour à Teacher Joe seul : false.
            'ready' => true,
            'voice_setting' => 'teacher_voice.rose',
            'gemini_voice' => 'Sulafat',
            // Descriptions officielles de Google (documentation de la synthèse vocale de Gemini).
            'gemini_trials' => [
                'Sulafat' => 'Warm',
                'Kore' => 'Firm',
                'Aoede' => 'Breezy',
                'Despina' => 'Smooth',
                'Erinome' => 'Clear',
                'Vindemiatrix' => 'Gentle',
            ],
            'realtime_setting' => 'teacher_realtime_voice.rose',
            'realtime_voice' => 'marin',
            'openai_trials' => [
                'marin' => 'recommandée par OpenAI, la plus naturelle',
                'shimmer' => 'claire et douce',
                'coral' => 'chaleureuse et vive',
                'sage' => 'posée',
            ],
            // Voix des VIDÉOS et des MESSAGES vocaux (synthèse vocale), choisie à part de sa voix de séance avec le
            // test en français et en anglais de « Essai des voix » : toutes les voix féminines de Gemini (descriptions
            // officielles de Google ; le genre n'est pas publié, voix féminines à l'écoute) et d'OpenAI (synthèse
            // vocale ; « nova » n'existe pas en séance, seulement ici). Rien de choisi : sa voix de séance, comme avant.
            'speech_setting' => 'teacher_speech_voice.rose',
            'speech_trials' => [
                'gemini' => [
                    'Sulafat' => 'Warm',
                    'Kore' => 'Firm',
                    'Zephyr' => 'Bright',
                    'Leda' => 'Youthful',
                    'Aoede' => 'Breezy',
                    'Callirrhoe' => 'Easy-going',
                    'Autonoe' => 'Bright',
                    'Despina' => 'Smooth',
                    'Erinome' => 'Clear',
                    'Laomedeia' => 'Upbeat',
                    'Achernar' => 'Soft',
                    'Gacrux' => 'Mature',
                    'Pulcherrima' => 'Forward',
                    'Vindemiatrix' => 'Gentle',
                ],
                'openai' => [
                    'marin' => 'recommandée par OpenAI, la plus naturelle',
                    'coral' => 'chaleureuse et vive',
                    'shimmer' => 'claire et douce',
                    'sage' => 'posée',
                    'nova' => 'vive et nette (synthèse vocale seulement)',
                    'alloy' => 'neutre',
                ],
            ],
            // Ajouté au ton de ses vidéos et messages une fois cette voix choisie (jamais avant : aucun fichier refait).
            'speech_extra' => 'Le français : prononciation française standard, naturelle et soignée ; chaque mot dit en entier, sans syllabe avalée ni ajoutée ; les nombres et les prix dits en français. L\'anglais : prononciation américaine.',
            'virtual_teacher' => 'une professeure virtuelle',
            'pronoun' => 'elle',
            'the_teacher' => 'la professeure',
            'personality' => <<<'TXT'
                YOUR PERSONALITY: you are :teacher, a woman. In French, always use the feminine for yourself (« je suis ravie », « je suis prête », « votre professeure »). Warm, calm and encouraging, with a gentle sense of humour. Same method and same rules as above.
                TXT,
            'sample_style' => 'a calm, warm and confident female teacher, speaking clearly and a little slowly to a beginner',
            'face' => 'rose',
            'portrait' => 'images/teacher-rose.webp',
            'public_line' => 'Chaleureuse et encourageante, elle vous met à l\'aise dès la première phrase.',
            // Messages vocaux (bienvenue, résultat) : voix de femme, accords au féminin.
            // Vidéos de leçon et du Club (config videos.voice.style, en français) : au féminin aussi.
            'speech_style' => [
                'male teacher' => 'female teacher',
                'un professeur d\'anglais chaleureux et patient' => 'une professeure d\'anglais chaleureuse et patiente',
                'comme un professeur chaleureux' => 'comme une professeure chaleureuse',
                'comme un professeur très chaleureux et enthousiaste' => 'comme une professeure très chaleureuse et enthousiaste',
            ],
            // Vidéos de présentation dites par elle (config videos.presentations.teacher_versions).
            'presentation_texts' => [
                'le professeur d\'anglais' => 'la professeure d\'anglais',
                'J\'ai été conçu et programmé' => 'J\'ai été conçue et programmée',
            ],
            'welcome_paragraphs' => [
                'teacher' => "Je suis :teacher, votre professeure d'anglais. Vous avez fait un très bon choix pour parler anglais avec confiance, et je suis heureuse de vous accompagner, jour après jour.",
            ],
        ],
    ],

];
