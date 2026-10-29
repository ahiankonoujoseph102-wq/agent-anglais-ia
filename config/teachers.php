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
|   avatar           bloc de config/avatar.php (nom affiché, modèle 3D, cadrage) ; le NOM vient de là
|   gemini_voice     voix Gemini Live ; null : voix choisie dans « Essai des voix », sinon services.gemini.voice
|   realtime_voice   voix OpenAI Realtime ; null : services.openai.realtime_voice
|
| Étape 1 du chantier « Teacher Rose » : seul Teacher Joe existe ; il reçoit exactement ce qu'il recevait avant
| (tests/Feature/Teachers/TeacherJoeUnchangedTest.php). Une clé publiée n'est jamais renommée : elle est gardée
| sur le compte (users.teacher) et dans chaque séance (colonne teacher).
|
*/

return [

    // Professeur par défaut : celui d'un apprenant qui n'a rien choisi et des séances d'avant les professeurs.
    'default' => 'joe',

    'list' => [
        'joe' => [
            'avatar' => 'model',
            'gemini_voice' => null,
            'realtime_voice' => null,
        ],
    ],

];
