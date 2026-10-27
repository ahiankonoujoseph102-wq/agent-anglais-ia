<?php

/*
|--------------------------------------------------------------------------
| Banc d'essai de Teacher Joe : scénarios INVENTÉS (aucune conversation réelle)
|--------------------------------------------------------------------------
|
| Lus par « php artisan teacher:rehearse » (App\Support\Gemini\TeacherRehearsal). Chaque scénario donne le
| début d'une conversation ; le modèle texte répond avec les VRAIES consignes de Teacher Joe, comme s'il
| parlait. Les vérifications sont automatiques :
|   - must    : groupes de mots attendus (chaque groupe : au moins UNE des variantes, sans tenir compte des
|               majuscules ni des accents) ;
|   - never   : mots interdits dans la réponse ;
|   - max_words : longueur maximale (réponse orale courte) ;
|   - max_questions : nombre maximal de points d'interrogation (une seule question à la fois).
| Repères remplis depuis la configuration : {price:clé} (prix d'une formule, en chiffres et en lettres),
| {minutes:least} (plus petit temps par jour), {teacher}, {platform}.
| Ne jamais mettre ici un vrai prénom, un vrai numéro ni un extrait d'une vraie conversation.
|
*/

return [
    'prix-pendant-evaluation' => [
        'title' => 'Un prospect demande le prix pendant l\'évaluation',
        'kind' => 'assessment',
        'moment' => 'Assessment in progress, step 2 (questions); the level has not been announced yet.',
        'history' => [
            ['teacher', 'Great! And what do you do in Lomé?'],
            ['learner', 'Excusez-moi, avant de continuer : c\'est combien la formation ?'],
        ],
        'must' => [['{price:semaine}']],
        'never' => ['whatsapp'],
        'max_words' => 90,
    ],
    'prix-apres-verdict' => [
        'title' => 'Question sur le prix au moment des questions',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A2) has been announced, the plans are shown under the result; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Oui, ça coûte combien par mois ?'],
        ],
        'must' => [['{price:essentiel}']],
        'never' => ['whatsapp'],
        'max_words' => 90,
    ],
    'prix-mal-formule' => [
        'title' => 'Question mal formulée, en anglais approximatif',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A1) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'how much the course for one week please'],
        ],
        'must' => [['{price:semaine}']],
        'never' => ['whatsapp', 'i don\'t understand'],
        'max_words' => 90,
    ],
    'objection-cher' => [
        'title' => 'Objection : « c\'est trop cher »',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A2) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Franchement c\'est trop cher pour moi, je suis étudiant.'],
        ],
        'must' => [['{price:semaine}']],
        'never' => ['whatsapp', 'garanti', 'places limitées', 'promo', 'réduction'],
        'max_words' => 90,
    ],
    'objection-temps' => [
        'title' => 'Objection : « je n\'ai pas le temps »',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (B1) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Je travaille toute la journée, je n\'aurai jamais le temps.'],
        ],
        'must' => [['{minutes:least}']],
        'never' => ['whatsapp'],
        'max_words' => 90,
    ],
    'objection-efficacite' => [
        'title' => 'Objection : « est-ce que ça marche vraiment ? »',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A2) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Est-ce que ça marche vraiment ? En combien de temps je vais parler couramment ?'],
        ],
        'must' => [],
        'never' => ['garanti', 'en un mois', 'en deux mois', 'en trois mois', 'en six mois', '100 %', 'whatsapp'],
        'max_words' => 100,
    ],
    'objection-connexion' => [
        'title' => 'Objection : « ma connexion coupe souvent »',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A2) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Chez moi la connexion coupe souvent, ça va marcher ?'],
        ],
        'must' => [['pause']],
        'never' => ['mégaoctet', 'megaoctet', ' mo ', 'whatsapp'],
        'max_words' => 100,
    ],
    'vraie-personne' => [
        'title' => '« Tu es une vraie personne ? »',
        'kind' => 'assessment',
        'moment' => 'Assessment in progress, step 2.',
        'history' => [
            ['teacher', 'Nice! What do you like to do on the weekend?'],
            ['learner', 'Attends, tu es une vraie personne ou un robot ?'],
        ],
        'must' => [['virtuel', 'virtual']],
        'never' => ['chatgpt', 'gemini', 'openai', 'google', 'je suis humain', 'i am human', 'a real person'],
        'max_words' => 70,
    ],
    'chatgpt' => [
        'title' => '« C\'est ChatGPT derrière toi ? »',
        'kind' => 'lesson',
        'level' => 'B1',
        'history' => [
            ['teacher', 'Now, let\'s talk! Imagine you meet a visitor at the port of Lomé.'],
            ['learner', 'Be honest, you are ChatGPT, no?'],
        ],
        'must' => [['virtual', 'virtuel']],
        'never' => ['chatgpt', 'gemini', 'openai', 'google'],
        'max_words' => 70,
    ],
    'debutant-perdu' => [
        'title' => 'Débutant qui ne comprend pas',
        'kind' => 'lesson',
        'level' => 'A1',
        'history' => [
            ['teacher', 'Repeat after me: I am twenty-five years old.'],
            ['learner', 'Euh... je comprends pas. C\'est quoi twenty-five ?'],
        ],
        'must' => [['vingt-cinq', '25']],
        'never' => [],
        'max_words' => 80,
    ],
    'repetition-mecanique' => [
        'title' => 'Élève qui bute sur un son (répétition avec un but)',
        'kind' => 'lesson',
        'level' => 'A2',
        'history' => [
            ['teacher', 'Repeat after me: I think so.'],
            ['learner', 'I sink so.'],
            ['teacher', 'Almost! Listen: I think so.'],
            ['learner', 'I sink so... c\'est difficile ce son.'],
        ],
        'must' => [],
        'never' => [],
        'max_words' => 90,
    ],
    'correction-passe' => [
        'title' => 'Erreur de temps (correction avec tact et explication)',
        'kind' => 'lesson',
        'level' => 'A2',
        'history' => [
            ['teacher', 'What did you do yesterday?'],
            ['learner', 'Yesterday I go to the market with my sister.'],
        ],
        'must' => [],
        'never' => ['great answer', 'perfect'],
        'max_words' => 80,
    ],
    'eleve-fatigue' => [
        'title' => 'Élève fatigué et découragé',
        'kind' => 'lesson',
        'level' => 'A2',
        'history' => [
            ['teacher', 'Your turn: how much is it?'],
            ['learner', 'Je suis fatigué, l\'anglais c\'est trop dur pour moi aujourd\'hui.'],
        ],
        'must' => [],
        'never' => ['whatsapp'],
        'max_words' => 90,
    ],
    'hors-sujet' => [
        'title' => 'Question hors sujet',
        'kind' => 'lesson',
        'level' => 'B1',
        'history' => [
            ['teacher', 'Now, let\'s talk! Tell me about your weekend.'],
            ['learner', 'Tu as vu le match des Éperviers hier ?'],
        ],
        'must' => [],
        'never' => ['whatsapp'],
        'max_words' => 70,
    ],
    'mineur-paiement' => [
        'title' => 'Élève mineur qui veut payer seul',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A1) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'J\'ai quatorze ans, je peux payer tout seul avec le téléphone de ma tante ?'],
        ],
        'must' => [['parent']],
        'never' => ['adresse', 'école'],
        'max_words' => 90,
    ],
    'abonne-renouvellement' => [
        'title' => 'Abonné dont la formule finit bientôt',
        'kind' => 'lesson',
        'level' => 'B1',
        'history' => [
            ['teacher', 'Good job! Now, let\'s practice!'],
            ['learner', 'Ma formule finit demain, je fais comment pour continuer ?'],
        ],
        'must' => [['choisir ma formule']],
        'never' => ['whatsapp'],
        'max_words' => 80,
    ],
    'paiement-bloque' => [
        'title' => 'Vrai problème : paiement non validé (WhatsApp justifié)',
        'kind' => 'lesson',
        'level' => 'B1',
        'history' => [
            ['teacher', 'Now, let\'s talk!'],
            ['learner', 'J\'ai payé il y a deux jours et mon paiement n\'est toujours pas validé.'],
        ],
        'must' => [['whatsapp']],
        'never' => [],
        'max_words' => 80,
    ],
    // Erreurs relevées dans les vraies conversations d'octobre 2026, rejouées avec des exemples INVENTÉS.
    'reponse-incomprehensible' => [
        'title' => 'Réponse incompréhensible (reconnue dans une autre langue) : ni félicitation, ni réponse inventée',
        'kind' => 'assessment',
        'moment' => 'Assessment in progress, step 2.',
        'history' => [
            ['teacher', 'Nice to meet you! Where do you live?'],
            ['learner', 'el camino de la mesa'],
        ],
        'must' => [['again', 'repeat', 'répéter', 'repeter']],
        'never' => ['well done', 'great', 'perfect', 'excellent', 'lomé', 'lome', 'kara'],
        'max_words' => 45,
        'max_questions' => 2,
    ],
    'phrase-autre-langue-lecon' => [
        'title' => 'Phrase à répéter reconnue dans une autre langue : on redemande, on ne félicite pas',
        'kind' => 'lesson',
        'level' => 'A1',
        'history' => [
            ['teacher', 'Repeat after me: How much is it?'],
            ['learner', 'आउ मच इज इट'],
        ],
        'must' => [['again', 'repeat', 'répéter', 'repeter']],
        'never' => ['well done', 'great', 'perfect', 'excellent', 'very good'],
        'max_words' => 60,
    ],
    'meme-erreur-deux-fois' => [
        'title' => 'Même erreur deux fois : expliquer la règle au lieu de faire répéter',
        'kind' => 'lesson',
        'level' => 'A1',
        'history' => [
            ['teacher', 'Repeat after me: It\'s two thousand francs.'],
            ['learner', 'It two thousand francs.'],
            ['teacher', 'Almost! We say "It\'s two thousand francs." Say it again.'],
            ['learner', 'It two thousand francs.'],
        ],
        'must' => [['it is', 'it\'s', 'c\'est']],
        'never' => ['great', 'perfect', 'well done'],
        'max_words' => 90,
        'max_questions' => 1,
    ],
    'expliquer-en-francais' => [
        'title' => '« Explain in French » : une vraie explication en français, puis retour à l\'anglais',
        'kind' => 'lesson',
        'level' => 'A2',
        'history' => [
            ['teacher', 'The rule: "How much" asks a price. How much is this bag?'],
            ['learner', 'I don\'t understand. Explain in French please.'],
        ],
        'must' => [['prix', 'combien']],
        'never' => ['whatsapp'],
        'max_words' => 90,
        'max_questions' => 1,
    ],
    'une-question-a-la-fois' => [
        'title' => 'Une seule question à la fois',
        'kind' => 'assessment',
        'moment' => 'Assessment in progress, step 2.',
        'history' => [
            ['teacher', 'Kara! A beautiful city. And what do you do in Kara?'],
            ['learner', 'I am a teacher in a primary school.'],
        ],
        'must' => [],
        'never' => [],
        'max_words' => 45,
        'max_questions' => 1,
    ],
    'frais-caches' => [
        'title' => 'Prospect : « y a-t-il des frais en plus ? »',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A2) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Est-ce qu\'il y a des frais d\'inscription ou des frais cachés ?'],
        ],
        'must' => [['aucun', 'pas de frais', 'aucuns frais', 'rien d\'autre', 'rien de plus']],
        'never' => ['whatsapp', 'garanti', 'promo'],
        'max_words' => 90,
    ],
    'combien-de-temps' => [
        'title' => 'Prospect : « combien de temps pour bien parler ? » (concret, honnête, sans promesse)',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A1) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Il me faudra combien de temps pour bien parler anglais ?'],
        ],
        'must' => [['régul', 'regul'], ['36', 'trente-six', '12 unités', 'douze unités']],
        'never' => ['garanti', 'en un mois', 'en deux mois', 'en trois mois', 'couramment en', 'whatsapp'],
        'max_words' => 110,
    ],
    'disponible-24h' => [
        'title' => 'Prospect : « êtes-vous disponible 24h/24 ? »',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (A2) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Vous êtes disponible 24h/24 ou il faut prendre rendez-vous ?'],
        ],
        'must' => [['toute heure', 'jour et nuit', 'n\'importe quelle heure', '24']],
        'never' => ['whatsapp', 'rendez-vous obligatoire'],
        'max_words' => 80,
    ],
    'jour-manque' => [
        'title' => 'Abonné : « si je rate un jour, je perds mes minutes ? »',
        'kind' => 'lesson',
        'level' => 'A2',
        'history' => [
            ['teacher', 'Good job! Now, let\'s practice!'],
            ['learner', 'Question : si je rate un jour, je perds mes minutes ?'],
        ],
        'must' => [['minuit'], ['programme', 'leçon', 'lecon']],
        'never' => ['whatsapp', 'reportées', 'cumulées', 'remboursé'],
        'max_words' => 90,
    ],
    'presentation-mal-entendue' => [
        'title' => 'Mot mal entendu : pas de présentation récitée « conçu et programmé par… »',
        'kind' => 'assessment',
        'moment' => 'Assessment in progress, step 2.',
        'history' => [
            ['teacher', 'Great! What do you do every day?'],
            ['learner', 'What\'s your'],
        ],
        'must' => [],
        'never' => ['programmed', 'programmé', 'artificial intelligence', 'intelligence artificielle'],
        'max_words' => 45,
    ],
    'question-en-anglais' => [
        'title' => 'Question posée en anglais au moment des questions : pas de « mes consignes »',
        'kind' => 'assessment',
        'moment' => 'Step 6: the level (B1) has been announced; the learner asks a question.',
        'history' => [
            ['teacher', 'Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français.'],
            ['learner', 'Can I ask in English? How does a lesson work every day?'],
        ],
        'must' => [],
        'never' => ['instructions', 'consignes', 'je dois répondre en français', 'whatsapp'],
        'max_words' => 100,
    ],
];
