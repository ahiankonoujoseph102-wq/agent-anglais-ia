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
        ],

        'rose' => [
            'avatar' => 'rose',
            // Étape 3 : passer à true quand son visage est prêt et validé par le fondateur sur des images.
            'ready' => false,
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
            'virtual_teacher' => 'une professeure virtuelle',
            'pronoun' => 'elle',
            'the_teacher' => 'la professeure',
            'personality' => <<<'TXT'
                YOUR PERSONALITY: you are :teacher, a woman. In French, always use the feminine for yourself (« je suis ravie », « je suis prête », « votre professeure »). Warm, calm and encouraging, with a gentle sense of humour. Same method and same rules as above.
                TXT,
            'sample_style' => 'a calm, warm and confident female teacher, speaking clearly and a little slowly to a beginner',
            'face' => 'rose',
        ],
    ],

];
