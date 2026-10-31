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
                // Voix des vidéos et des messages choisie le 29 octobre 2026 : proposée aussi en séance, à écouter d'abord
                // avec « Écouter en séance » (le modèle des séances n'est pas celui de la synthèse vocale).
                'Zephyr' => 'Bright',
                // Voix décrites comme plus posées (30 octobre 2026, « elle parle trop vite ») : leur débit réel est
                // mesuré par « Écouter en séance » (même phrase pour toutes les voix).
                'Achernar' => 'Soft',
                'Gacrux' => 'Mature',
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
            // Seuls changent le prénom et le féminin : la méthode, le débit, la patience et l'écoute sont ceux de Teacher
            // Joe (mêmes consignes au caractère près). Ajout du 30 octobre 2026 (« elle parle trop vite ») : sa voix sonne
            // plus vive que celle de Teacher Joe, d'où le rappel de suivre le débit à la lettre et de laisser parler.
            'personality' => <<<'TXT'
                YOUR PERSONALITY: you are :teacher, a woman. In French, always use the feminine for yourself (« je suis ravie », « je suis prête », « votre professeure »). You teach EXACTLY with the method, the pace, the patience and the listening described above: never more talkative, never quicker. Your voice naturally sounds a little lively: follow the PACE rules strictly, speak a little more slowly than feels natural to you, with a short pause after each key word and each model sentence, keep your turns short and let the learner speak.
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
            // Fiche du site (site-guide.md) : phrases où le professeur est « il », accordées au féminin.
            'site_guide_texts' => [
                'Il ne fait pas les devoirs à la place de l\'élève : il l\'aide' => 'Elle ne fait pas les devoirs à la place de l\'élève : elle l\'aide',
                'Il explique en français simple' => 'Elle explique en français simple',
                'de la leçon : il explique la consigne' => 'de la leçon : elle explique la consigne',
                'Il ne fait PAS les devoirs' => 'Elle ne fait PAS les devoirs',
                'quand il ne trouve pas un passage' => 'quand elle ne trouve pas un passage',
                'Il pose les questions une par une' => 'Elle pose les questions une par une',
                '« Interrompre le professeur » (pendant qu\'il parle)' => '« Interrompre la professeure » (pendant qu\'elle parle)',
            ],
            'welcome_paragraphs' => [
                // Le bouton de son écran dit « Interrompre la professeure » (live/session.blade.php, Teacher::theTeacher()).
                'lesson_rules' => 'Dans « Mon espace », vous voyez toujours le temps qu\'il vous reste aujourd\'hui. Quand il est écoulé, la séance s\'arrête toute seule, et votre temps revient chaque jour à minuit. Pendant que je parle, vous pouvez m\'interrompre avec le bouton « Interrompre la professeure ». Vous pouvez aussi faire une pause, mais le temps continue de s\'écouler, ou couper votre micro. Si la connexion est coupée, reprenez votre séance dès que possible : le temps de la coupure est mis en pause, vous ne perdez pas de minutes.',
                'teacher' => "Je suis :teacher, votre professeure d'anglais. Vous avez fait un très bon choix pour parler anglais avec confiance, et je suis heureuse de vous accompagner, jour après jour.",
            ],
        ],
    ],

];
