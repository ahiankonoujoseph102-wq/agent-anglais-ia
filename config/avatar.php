<?php

/*
|--------------------------------------------------------------------------
| Professeur animé en 3D
|--------------------------------------------------------------------------
|
| L'écran de conversation affiche le professeur en 3D quand le téléphone en
| est capable ; sinon (ou si le chargement échoue) il garde le visage simple
| en SVG. Tous les fichiers sont statiques, dans public/teacher3d/.
|
| Changer de personnage : déposer un autre fichier GLB dans
| public/teacher3d/models/, puis modifier « model » ci-dessous. Le modèle doit
| suivre les conventions des avatars parlants : squelette aux noms Mixamo
| (Spine, Neck, Head, LeftArm…), formes de bouche Oculus (viseme_aa…) et
| expressions ARKit (mouthSmileLeft, eyeBlinkLeft, browInnerUp…). Les gestes
| des bras ne sont activés que pour le profil « teacher-joe » ; un autre
| squelette (profil « generic ») garde la tête, le regard et le visage animés.
|
*/

return [

    // Interrupteur général (AVATAR_3D_ENABLED=false : visage simple partout).
    'enabled' => (bool) env('AVATAR_3D_ENABLED', true),

    'model' => [
        'name' => 'Teacher Joe',
        // Chemin dans public/.
        'file' => 'teacher3d/models/teacher-joe.glb',
        // « teacher-joe » (gestes des bras) ou « generic » (tête et visage seulement).
        'profile' => 'teacher-joe',
        // Cadrage, en mètres dans le repère du modèle : point visé, demi-hauteur
        // et demi-largeur minimales visibles, hauteur des yeux.
        'camera' => [
            'target' => [0, 0.57, 0],
            'half_height' => 0.33,
            'half_width' => 0.30,
            'eye_height' => 0.68,
        ],
        // Hauteur du dessus du bureau (les avant-bras du personnage y reposent).
        'desk_height' => 0.175,
        // Sourire permanent, de 0 à 1.
        'resting_smile' => 0.3,
        // Hauteur moyenne de la voix du professeur, en Hz (aide la lecture labiale).
        'voice_hz' => 130,
    ],

    // Teacher Rose (config/teachers.php) : personnage original créé par le même script (TEACHER=rose node
    // tools/teacher3d/build-teacher-joe.mjs), même squelette et mêmes visèmes. Sans fichier, l'écran de séance montre
    // son visage simple (SVG). Son choix n'est montré aux apprenants que quand son visage est validé
    // (teachers.list.rose.ready).
    'rose' => [
        'name' => 'Teacher Rose',
        'file' => 'teacher3d/models/teacher-rose.glb',
        'profile' => 'teacher-joe',
        'camera' => [
            'target' => [0, 0.57, 0],
            'half_height' => 0.33,
            'half_width' => 0.30,
            'eye_height' => 0.68,
        ],
        'desk_height' => 0.175,
        'resting_smile' => 0.45,
        // Voix plus aiguë que celle de Teacher Joe (lecture labiale).
        'voice_hz' => 210,
    ],

    // Expressions du professeur 3D, déclenchées dans le navigateur d'après ses paroles (public/js/teacher-cues.js).
    'expressions' => [
        'welcome' => 'once, at the very beginning, with your greeting (smile and wave)',
        'happy' => 'each time the learner answers well or makes progress: big smile',
        'encouraging' => 'when the learner hesitates, struggles, doubts or is shy: kind encouraging smile',
        'attentive' => 'just before you correct a mistake, or when the learner tells you something personal: attentive and kind',
        'thinking' => 'when you take a moment before a harder question or think about the learner\'s answer',
        'goodbye' => 'once, at the very end, with your goodbye (smile and wave)',
        'neutral' => 'back to a calm face after a long explanation',
    ],

];
