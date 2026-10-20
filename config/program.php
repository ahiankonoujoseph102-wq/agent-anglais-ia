<?php

/*
|--------------------------------------------------------------------------
| Programme personnel
|--------------------------------------------------------------------------
|
| Le contenu des niveaux (A1 à C2) est dans resources/curriculum/*.php.
| Ce fichier règle la façon dont il est adapté à chaque apprenant.
|
*/

return [

    // Durée maximale du bilan oral de fin de niveau, en minutes.
    'level_test_minutes' => 10,

    // Durée minimale de bilan (en secondes) avant qu'un résultat soit accepté.
    'level_test_min_seconds' => 120,

    // Nombre maximal d'erreurs rappelées au professeur à chaque séance.
    'errors_per_lesson' => 6,

    // Tableau de la séance pas encore prêt à l'ouverture de « Ma séance du jour » : délai accordé à chaque
    // fournisseur du modèle texte (secondes), puis tableau de secours tiré du programme. Jamais à la connexion.
    'board_prepare_timeout' => 6,

    // « Modifier mes objectifs » : modifications enregistrées au plus par jour et par compte (minuit, heure de Lomé).
    'answer_edits_per_day' => 3,

    // Un apprenant abonné sans séance depuis ce nombre de jours est « inactif » (administration).
    'inactive_after_days' => 3,

    /*
    | Mots du métier ou des centres d'intérêt (en minuscules, sans accents)
    | qui mettent en avant les situations d'un thème.
    */
    'occupation_keywords' => [
        'business' => ['commer', 'vend', 'import', 'export', 'boutique', 'entrepren', 'marchand', 'negoc', 'grossiste', 'revend'],
        'market' => ['marche', 'boutique', 'vendeu', 'revend'],
        'health' => ['infirm', 'medec', 'sante', 'pharma', 'sage-femme', 'docteur', 'hopital', 'clinique', 'aide-soign'],
        'studies' => ['etudiant', 'eleve', 'universit', 'lycee', 'college', 'bac', 'master', 'licence'],
        'education' => ['enseign', 'professeur', 'instituteur', 'formateur', 'ecole', 'educat'],
        'transport' => ['chauffeur', 'transport', 'taxi', 'moto', 'zemidjan', 'logisti', 'transit', 'douane'],
        'tech' => ['informati', 'developpeur', 'reseau', 'telecom', 'technicien', 'numerique', 'web'],
        'admin' => ['administra', 'fonctionnaire', 'secretaire', 'comptab', 'banque', 'assistant', 'ressources humaines', 'mairie'],
        'travel' => ['hotel', 'touris', 'guide', 'aerien', 'voyage', 'agence'],
        'phone' => ['telephon', 'whatsapp', 'call', 'service client'],
        'family' => ['menag', 'maman', 'papa', 'famille', 'parent'],
    ],

];
