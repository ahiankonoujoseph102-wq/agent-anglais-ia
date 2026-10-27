<?php

/*
|--------------------------------------------------------------------------
| Guides gratuits (/guides), lus par App\Support\Guides
|--------------------------------------------------------------------------
|
| Dans l'ordre d'affichage. Texte de chaque guide : resources/views/guides/articles/{slug}.blade.php.
| Titre : 50 caractères au plus (« · :platform » est ajouté) ; description : 155 au plus ; uniques.
| Marqueurs : :platform, :teacher. Les exemples sont en anglais américain ; aucun chiffre inventé, aucune
| promesse de résultat, aucun autre pays que ceux du paiement, aucune mention de l'intelligence artificielle.
| « published » : date de publication (données structurées Article).
|
*/

return [

    'se-presenter-en-anglais' => [
        'title' => 'Comment se présenter en anglais : exemples',
        'description' => 'Se présenter en anglais à l\'oral : les phrases simples pour dire son nom, son âge, son métier et ses goûts, avec des exemples et les erreurs à éviter.',
        'heading' => 'Comment se présenter en anglais',
        'lead' => 'Les phrases simples pour vous présenter à l\'oral, de la plus courte à la plus complète, avec des exemples à répéter à voix haute.',
        'icon' => 'user',
        'published' => '2026-10-27',
        'related' => ['phrases-anglaises-du-quotidien', 'anglais-pour-debutants', 'entretien-en-anglais-questions'],
    ],

    'phrases-anglaises-du-quotidien' => [
        'title' => '50 phrases anglaises pour le quotidien',
        'description' => '50 phrases anglaises utiles chaque jour, avec leur traduction : saluer, demander, acheter, se déplacer, téléphoner, s\'excuser et remercier.',
        'heading' => '50 phrases anglaises pour le quotidien',
        'lead' => 'Cinquante phrases courtes, classées par situation, avec leur traduction. Dites-les à voix haute : c\'est ainsi qu\'elles restent.',
        'icon' => 'messages',
        'published' => '2026-10-27',
        'related' => ['se-presenter-en-anglais', 'erreurs-anglais-francophones', 'anglais-au-travail-accueillir-un-client'],
    ],

    'anglais-pour-debutants' => [
        'title' => 'Anglais pour débutants : par où commencer',
        'description' => 'Vous partez de zéro en anglais ? Par où commencer, quoi apprendre en premier, combien de temps par jour et comment oser parler dès le début.',
        'heading' => 'Anglais pour débutants : par où commencer',
        'lead' => 'Vous ne parlez pas du tout anglais, ou presque ? Voici un chemin simple pour démarrer, sans vous noyer dans la grammaire.',
        'icon' => 'level',
        'published' => '2026-10-27',
        'related' => ['phrases-anglaises-du-quotidien', 'ameliorer-son-anglais-oral', 'parler-anglais-sur-telephone'],
    ],

    'ameliorer-son-anglais-oral' => [
        'title' => 'Améliorer son anglais oral, un peu chaque jour',
        'description' => 'Comment améliorer son anglais oral : parler chaque jour, écouter, répéter, se corriger et revoir ses phrases. Une méthode simple, sans promesse magique.',
        'heading' => 'Comment améliorer son anglais oral, un peu chaque jour',
        'lead' => 'Comprendre l\'anglais ne suffit pas pour le parler. Voici ce qui aide vraiment à prendre la parole, et comment l\'organiser dans vos journées.',
        'icon' => 'mic',
        'published' => '2026-10-27',
        'related' => ['parler-anglais-couramment', 'erreurs-anglais-francophones', 'parler-anglais-sur-telephone'],
    ],

    'entretien-en-anglais-questions' => [
        'title' => 'Entretien en anglais : 10 questions et réponses',
        'description' => 'Entretien d\'embauche, de stage, de bourse ou d\'admission en anglais : 10 questions fréquentes, des exemples de réponses et la façon de vous entraîner.',
        'heading' => 'Entretien en anglais : 10 questions et comment y répondre',
        'lead' => 'Les questions qui reviennent souvent en entretien, avec des exemples de réponses simples en anglais américain, à adapter à votre histoire.',
        'icon' => 'work',
        'published' => '2026-10-27',
        'related' => ['se-presenter-en-anglais', 'parler-anglais-couramment', 'anglais-au-travail-accueillir-un-client'],
    ],

    'parler-anglais-sur-telephone' => [
        'title' => 'Apprendre à parler anglais sur son téléphone',
        'description' => 'Apprendre l\'anglais sur téléphone, à l\'oral : ce qu\'il faut, combien de données, comment organiser vos séances et éviter les pièges des applications.',
        'heading' => 'Apprendre à parler anglais sur son téléphone',
        'lead' => 'Votre téléphone peut devenir votre salle de classe. Encore faut-il qu\'il vous fasse parler, et pas seulement toucher l\'écran.',
        'icon' => 'mobile',
        'published' => '2026-10-27',
        'related' => ['anglais-pour-debutants', 'ameliorer-son-anglais-oral', 'phrases-anglaises-du-quotidien'],
    ],

    'erreurs-anglais-francophones' => [
        'title' => '20 erreurs d\'anglais fréquentes des francophones',
        'description' => '20 erreurs d\'anglais courantes chez les francophones (faux amis, « I have 20 years »…), avec la bonne forme et une astuce pour s\'en souvenir.',
        'heading' => 'Les 20 erreurs d\'anglais fréquentes chez les francophones',
        'lead' => 'Le français et l\'anglais se ressemblent, et c\'est justement ce qui piège. Voici vingt erreurs courantes, la bonne forme et une astuce pour chacune.',
        'icon' => 'check-circle',
        'published' => '2026-10-27',
        'related' => ['ameliorer-son-anglais-oral', 'anglais-americain-ou-britannique', 'se-presenter-en-anglais'],
    ],

    'parler-anglais-couramment' => [
        'title' => 'Parler anglais plus couramment, sans peur',
        'description' => 'Comment parler anglais plus couramment quand on a peur de se tromper : phrases de secours, gagner du temps, oser parler et progresser par petites étapes.',
        'heading' => 'Parler anglais plus couramment sans avoir peur de se tromper',
        'lead' => 'Vous connaissez des mots, mais vous bloquez quand il faut parler ? La peur de l\'erreur se travaille, comme la grammaire.',
        'icon' => 'bubble',
        'published' => '2026-10-27',
        'related' => ['ameliorer-son-anglais-oral', 'erreurs-anglais-francophones', 'entretien-en-anglais-questions'],
    ],

    'anglais-au-travail-accueillir-un-client' => [
        'title' => 'Anglais au travail : accueillir un client étranger',
        'description' => 'Accueillir un client anglophone au travail : saluer, proposer de l\'aide, donner un prix, expliquer un délai et dire au revoir, avec des phrases prêtes.',
        'heading' => 'Parler anglais au travail : accueillir un client étranger',
        'lead' => 'Boutique, hôtel, bureau ou port : les phrases pour accueillir un client anglophone avec politesse, du bonjour jusqu\'au au revoir.',
        'icon' => 'users',
        'published' => '2026-10-27',
        'related' => ['phrases-anglaises-du-quotidien', 'entretien-en-anglais-questions', 'parler-anglais-couramment'],
    ],

    'anglais-americain-ou-britannique' => [
        'title' => 'Anglais américain ou britannique : différences',
        'description' => 'Anglais américain ou britannique : différences de vocabulaire, d\'orthographe et de prononciation, et lequel choisir pour apprendre à parler.',
        'heading' => 'Anglais américain ou britannique : quelles différences ?',
        'lead' => 'Apartment ou flat, color ou colour : deux façons de parler la même langue. Ce qui change vraiment, et ce qui compte pour vous.',
        'icon' => 'globe',
        'published' => '2026-10-27',
        'related' => ['erreurs-anglais-francophones', 'anglais-pour-debutants', 'phrases-anglaises-du-quotidien'],
    ],

];
