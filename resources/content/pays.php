<?php

/*
|--------------------------------------------------------------------------
| Pages « Apprendre l'anglais » par pays (/apprendre-anglais/{pays})
|--------------------------------------------------------------------------
|
| Une page par pays de paiement (config/payment.php), lue par App\Support\CountryPage.
| Chaque texte est propre à son pays (jamais une copie avec le nom changé) et ne décrit
| que ce que fait le site. Marqueurs remplis par CountryPage :
|   :platform   nom de la plateforme         :teacher     nom du professeur
|   :minutes    durée de l'évaluation        :operators   moyens de paiement du pays (configuration)
|   :daily      temps par jour des formules  :dial        indicatif du pays
|   :course_photos, :course_pages  photos et pages de PDF au plus dans « Mes cours »
| Interdits : « certificat », « diplôme », « fait les devoirs » (sauf « ne fait pas les devoirs »),
| « donne les réponses », « tous vos cours », « T-Money », tout chiffre inventé.
| Les situations du programme se passent au Togo ou dans un cadre international (aucun autre pays cité).
| Le site s'adresse aux francophones de tous les pays ; ces quatre pages sont celles du paiement Mobile Money.
| Titre : 50 caractères au plus (« · :platform » est ajouté) ; description : 155 au plus.
|
*/

return [

    'togo' => [
        'code' => 'TG',
        'title' => 'Cours d\'anglais à Lomé : parler anglais au Togo',
        'description' => 'Parlez anglais au Togo avec :teacher, depuis votre téléphone : situations de Lomé, évaluation orale gratuite, paiement par :operators.',
        'heading' => 'Apprendre à parler anglais au Togo',
        'lead' => ':teacher, le professeur d\'anglais de :platform, vous fait parler anglais chaque jour depuis votre téléphone, à Lomé, à Kara ou ailleurs au Togo. Vous commencez par une évaluation orale gratuite de :minutes minutes.',
        'photo' => 'etudiante-parle',
        'sections' => [
            [
                'icon' => 'pin',
                'title' => 'Des leçons qui se passent chez vous',
                'text' => 'Le programme de :platform a été écrit pour le Togo. Vous vous entraînez dans des situations que vous connaissez : accueillir un client étranger dans votre boutique de Bè, marchander au Grand Marché de Lomé, travailler avec des clients au port autonome, prendre un zémidjan ou un taxi, vous inscrire à l\'université de Lomé ou de Kara, faire une démarche à l\'administration.',
            ],
            [
                'icon' => 'globe',
                'title' => 'L\'anglais du monde entier',
                'text' => 'Accueillir des visiteurs anglophones à Lomé, parler à un client étranger au téléphone, voyager, étudier en ligne : le programme vous prépare aussi aux échanges internationaux, en anglais américain, compris partout dans le monde.',
            ],
            [
                'icon' => 'wallet',
                'title' => 'Payer depuis le Togo',
                'text' => 'Sur la page de paiement, le Togo est choisi d\'avance grâce à l\'indicatif +:dial de votre numéro. Vous envoyez le montant exact avec :operators au numéro affiché, puis la capture d\'écran de la confirmation. Si elle est claire, vos séances commencent tout de suite, pendant que l\'équipe vérifie le paiement.',
            ],
        ],
        'faq' => [
            [
                'question' => 'Avez-vous un bureau à Lomé ?',
                'answer' => 'Oui, l\'adresse et les horaires sont sur la page Contact. Les séances avec :teacher, elles, se font depuis votre téléphone : vous n\'avez pas besoin de vous déplacer.',
            ],
            [
                'question' => 'Pourquoi un professeur d\'anglais en ligne plutôt qu\'un cours du soir ?',
                'answer' => ':teacher est disponible chaque jour, à l\'heure qui vous arrange. Vous parlez pendant toute la séance, il vous corrige aussitôt et il garde vos erreurs pour les revoir avec vous.',
            ],
        ],
    ],

    'benin' => [
        'code' => 'BJ',
        'title' => 'Apprendre l\'anglais au Bénin, depuis le téléphone',
        'description' => 'Au Bénin, :teacher fait parler anglais les adultes et aide les élèves en cours d\'anglais. Évaluation gratuite, paiement par :operators.',
        'heading' => 'Apprendre l\'anglais au Bénin',
        'lead' => 'Que vous soyez à Cotonou, à Porto-Novo ou à Parakou, :teacher vous donne chaque jour une séance d\'anglais à l\'oral sur votre téléphone. Pour les élèves et les étudiants, il explique aussi les leçons d\'anglais de l\'école, de l\'université ou de la formation.',
        'photo' => 'parent-enfant-lecture',
        'sections' => [
            [
                'icon' => 'book',
                'title' => 'Aide aux devoirs d\'anglais pour les élèves',
                'text' => 'Avec « Mes cours », l\'élève ajoute jusqu\'à :course_photos photos de son cahier ou de son livre, ou son cours en PDF (jusqu\'à :course_pages pages). :teacher la lit, l\'explique en français et le guide pour faire l\'exercice lui-même : il ne fait pas les devoirs à sa place. Les étudiants (lycée, université, formation) s\'en servent aussi pour leurs cours d\'anglais. C\'est uniquement pour les cours d\'anglais, et c\'est compris dans le temps de la formule.',
            ],
            [
                'icon' => 'clock',
                'title' => 'À l\'heure du Bénin',
                'text' => 'Le Bénin a une heure d\'avance sur Lomé. Dans son message de bienvenue, :teacher vous dit « Bonjour » ou « Bonsoir » à l\'heure de chez vous, d\'après l\'indicatif +:dial de votre numéro.',
            ],
            [
                'icon' => 'pin',
                'title' => 'Des situations de la vie de tous les jours',
                'text' => 'Les leçons mêlent des scènes de la vie de tous les jours (marché, transport, administration) et des situations internationales : travail, voyage, études, appels et rencontres en ligne. Des scènes où l\'anglais sert vraiment.',
            ],
            [
                'icon' => 'wallet',
                'title' => 'Payer depuis le Bénin',
                'text' => 'Le Bénin est choisi d\'avance sur la page de paiement. Vous envoyez le montant avec :operators au numéro béninois affiché (dix chiffres), puis la capture d\'écran de la confirmation.',
            ],
        ],
        'faq' => [
            [
                'question' => 'Mon enfant est au collège : :teacher peut-il l\'aider ?',
                'answer' => 'Oui, pour ses cours d\'anglais, avec « Mes cours » : il explique la leçon en français, vérifie que votre enfant a compris et l\'aide à trouver lui-même la réponse des exercices. Inscrivez-le vous-même ou aidez-le à le faire, et restez à ses côtés pour le paiement.',
            ],
            [
                'question' => 'Faut-il une carte bancaire ?',
                'answer' => 'Non. Le paiement se fait uniquement par Mobile Money, depuis votre téléphone : au Bénin, avec :operators.',
            ],
        ],
    ],

    'burkina-faso' => [
        'code' => 'BF',
        'title' => 'Apprendre l\'anglais au Burkina Faso, à l\'oral',
        'description' => 'Apprendre l\'anglais au Burkina Faso avec :teacher : séances orales courtes, adaptées à votre métier. Évaluation gratuite, paiement :operators.',
        'heading' => 'Apprendre l\'anglais au Burkina Faso',
        'lead' => 'À Ouagadougou, à Bobo-Dioulasso ou ailleurs au Burkina Faso, :teacher vous fait pratiquer l\'anglais à l\'oral un peu chaque jour, depuis votre téléphone, même avec un emploi du temps chargé.',
        'photo' => 'equipe-reunion',
        'sections' => [
            [
                'icon' => 'work',
                'title' => 'Pensé pour ceux qui travaillent',
                'text' => 'Avant votre programme, quatre écrans vous demandent votre objectif, votre métier, vos jours de travail et vos difficultés. Les situations liées à votre métier passent en premier, et une leçon est prévue pour chaque jour que vous avez choisi.',
            ],
            [
                'icon' => 'wifi',
                'title' => 'Léger en données internet',
                'text' => 'Les pages du site sont légères. Pendant une séance, comptez environ 3 à 4 mégaoctets par minute ; le professeur en 3D n\'est téléchargé qu\'une seule fois. Aucune application à installer : ajoutez simplement :platform à l\'écran d\'accueil de votre téléphone.',
            ],
            [
                'icon' => 'globe',
                'title' => 'Pour vos échanges internationaux',
                'text' => 'Le programme comprend des scènes de travail et de voyage à l\'international : appeler un fournisseur étranger, comparer des prix, réserver un billet, participer à une visioconférence.',
            ],
            [
                'icon' => 'wallet',
                'title' => 'Payer depuis le Burkina Faso',
                'text' => 'Le Burkina Faso est choisi d\'avance grâce à l\'indicatif +:dial de votre numéro. Vous payez avec :operators, puis vous envoyez la capture d\'écran de la confirmation.',
            ],
        ],
        'faq' => [
            [
                'question' => 'Je n\'ai pas beaucoup de temps : combien dure une séance ?',
                'answer' => 'Votre formule donne un temps par jour (:daily), à partager entre la séance orale et « Mes cours ». :teacher annonce la durée au début, vous prévient avant la fin, et le site arrête la séance à l\'heure.',
            ],
            [
                'question' => 'Puis-je suivre les séances sur un ordinateur ?',
                'answer' => 'Oui. Il faut un téléphone ou un ordinateur avec un micro, un navigateur récent comme Chrome, et une connexion internet.',
            ],
        ],
    ],

    'cote-d-ivoire' => [
        'code' => 'CI',
        'title' => 'Apprendre l\'anglais en Côte d\'Ivoire, à l\'oral',
        'description' => 'Apprendre l\'anglais en Côte d\'Ivoire avec :teacher : niveau de A1 à C2, programme personnel, attestation de niveau, paiement par Mobile Money.',
        'heading' => 'Apprendre l\'anglais en Côte d\'Ivoire',
        'lead' => 'D\'Abidjan à Bouaké ou à Yamoussoukro, :teacher vous fait progresser en anglais oral, niveau après niveau, depuis votre téléphone.',
        'photo' => 'eleve-ecouteurs',
        'sections' => [
            [
                'icon' => 'chart',
                'title' => 'Un vrai parcours, de A1 à C2',
                'text' => 'L\'évaluation orale gratuite donne votre niveau. Ensuite, chaque niveau compte douze unités de trois leçons. Vos erreurs sont gardées et revues jusqu\'à ce que vous les corrigiez deux fois de suite.',
            ],
            [
                'icon' => 'award',
                'title' => 'Une attestation de niveau à chaque étape',
                'text' => 'À la fin d\'un niveau, vous passez un bilan oral avec :teacher. S\'il est réussi, vous recevez une attestation de niveau en PDF, puis vous passez au niveau suivant avec un nouveau programme.',
            ],
            [
                'icon' => 'globe',
                'title' => 'Un anglais compris partout',
                'text' => ':teacher enseigne l\'anglais américain, compris partout dans le monde. Le programme vous entraîne aux échanges internationaux : voyage, études, appels à des clients et à des fournisseurs étrangers.',
            ],
            [
                'icon' => 'wallet',
                'title' => 'Payer depuis la Côte d\'Ivoire',
                'text' => 'La Côte d\'Ivoire est choisie d\'avance grâce à l\'indicatif +:dial de votre numéro. Vous payez avec :operators au numéro ivoirien affiché (dix chiffres), puis vous envoyez la capture d\'écran de la confirmation.',
            ],
        ],
        'faq' => [
            [
                'question' => 'Comment mon niveau est-il trouvé ?',
                'answer' => 'Pendant une conversation de :minutes minutes au plus, :teacher vous pose des questions adaptées à vos réponses, puis vous annonce votre niveau, de A1 à C2. Il vous montre ensuite comment se passe une leçon avec une première phrase, puis répond à vos questions en français.',
            ],
            [
                'question' => 'Mon attestation peut-elle être vérifiée ?',
                'answer' => 'Oui. Chaque attestation porte un code : toute personne qui a ce code peut vérifier sur le site votre nom, le niveau validé et la date.',
            ],
        ],
    ],

];
