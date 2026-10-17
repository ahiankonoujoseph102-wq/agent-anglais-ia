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
            'target' => [0, 0.56, 0],
            'half_height' => 0.31,
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

    // Expressions que le professeur peut demander (fonction set_expression de Gemini).
    'expressions' => [
        'welcome' => 'warm welcome at the beginning (smile and wave)',
        'happy' => 'the learner succeeded: big smile, congratulations',
        'encouraging' => 'the learner is struggling or shy: kind encouragement',
        'attentive' => 'just before you correct a mistake: attentive and kind',
        'thinking' => 'you are thinking about a question',
        'goodbye' => 'saying goodbye at the end (smile and wave)',
        'neutral' => 'back to a calm neutral face',
    ],

    // Crédits affichés sur la page /credits (professeur 3D, police, bibliothèques).
    'credits' => [
        [
            'name' => 'Personnage 3D « Teacher Joe »',
            'author' => 'Créé pour la plateforme (modèle généré par tools/teacher3d/build-teacher-joe.mjs)',
            'license' => 'CC0 1.0 (domaine public)',
            'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/deed.fr',
            'url' => null,
        ],
        [
            'name' => 'Police Sora (tout le texte du site, graisses 400 à 700)',
            'author' => 'The Sora Project Authors (Jonathan Barnbrook, Julián Moncada)',
            'license' => 'SIL Open Font License 1.1',
            'license_url' => 'https://openfontlicense.org',
            'url' => 'https://github.com/sora-xor/sora-font',
        ],
        [
            'name' => 'three.js (affichage 3D, chargement glTF, décompression meshopt)',
            'author' => 'three.js authors',
            'license' => 'MIT',
            'license_url' => 'https://github.com/mrdoob/three.js/blob/dev/LICENSE',
            'url' => 'https://threejs.org',
        ],
        [
            'name' => 'HeadAudio (lecture labiale à partir du son)',
            'author' => 'Mika Suominen',
            'license' => 'MIT',
            'license_url' => 'https://github.com/met4citizen/HeadAudio/blob/main/LICENSE',
            'url' => 'https://github.com/met4citizen/HeadAudio',
        ],
    ],

];
