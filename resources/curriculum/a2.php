<?php

/*
|--------------------------------------------------------------------------
| Programme officiel — niveau A2 (élémentaire)
|--------------------------------------------------------------------------
|
| Contenu original, aligné sur les descripteurs A2 du CECRL : communiquer
| lors de tâches simples et habituelles, décrire avec des moyens simples sa
| formation, son environnement, évoquer des besoins immédiats, raconter un
| événement passé simple.
|
*/

return [

    'title' => 'A2 — Élémentaire : se débrouiller au quotidien',

    'units' => [

        // 1
        [
            'title' => 'Hier : raconter sa journée passée',
            'objectives' => [
                "raconter ce que vous avez fait hier, dans l'ordre",
                'utiliser les verbes réguliers et irréguliers les plus courants au passé',
                "poser des questions sur la journée de quelqu'un",
            ],
            'vocabulary' => [
                'yesterday' => 'hier',
                'last night' => 'hier soir',
                'this morning' => 'ce matin',
                'ago' => 'il y a (durée)',
                'first' => "d'abord",
                'then / after that' => 'ensuite / après ça',
                'finally' => 'finalement',
                'went (go)' => 'suis allé (aller)',
                'had (have)' => 'ai eu, ai pris (avoir)',
                'got up (get up)' => 'me suis levé',
                'came back (come back)' => 'suis revenu',
                'saw (see)' => 'ai vu',
                'bought (buy)' => 'ai acheté',
                'met (meet)' => 'ai rencontré',
                'worked' => 'ai travaillé',
                'stayed' => 'suis resté',
                'busy' => 'occupé',
                'tired' => 'fatigué',
            ],
            'grammar' => [
                'Le prétérit (past simple) : verbes réguliers en -ed (worked, stayed), irréguliers à apprendre (went, had, saw, bought).',
                "Questions et négation avec did : « Did you go to work? — No, I didn't. », « What did you do? » (le verbe reste à la base).",
                'Ago après la durée : « two days ago » = il y a deux jours.',
            ],
            'functions' => [
                "Raconter dans l'ordre : « First I went to the market, then I cooked, and after that I watched the match. »",
                'Interroger : « What did you do last night? Did you watch the news? »',
                'Réagir : « Really? That sounds tiring! »',
            ],
            'pronunciation' => [
                "Les trois sons de -ed : worked [t], stayed [d], wanted [ɪd] — on n'ajoute une syllabe qu'après t ou d.",
                'Bought, brought, thought se prononcent [ɔːt] : le « gh » est muet.',
            ],
            'spoken' => [
                "« What were you up to yesterday? » = qu'est-ce que tu as fait hier ? (familier).",
                '« Nothing much » = pas grand-chose.',
            ],
            'pitfalls' => [
                'Après did, le verbe reste à la base : « Did you went? » ✗ → « Did you go? » ✓.',
                'Le passé composé français ne se traduit pas par have + participe quand le moment est précis : « I have seen him yesterday » ✗ → « I saw him yesterday » ✓.',
            ],
            'situations' => [
                ['title' => 'Rapport au chef', 'description' => 'Votre responsable anglophone vous demande ce que vous avez fait hier pendant votre journée au port de Lomé.', 'tags' => ['work']],
                ['title' => 'Retour de voyage', 'description' => 'Vous racontez à un ami votre journée d\'achats dans un grand marché, pendant un voyage à l\'étranger.', 'tags' => ['travel', 'market', 'conversation']],
                ['title' => 'Absent au cours', 'description' => 'Un camarade de l\'université de Lomé vous demande pourquoi vous étiez absent hier ; expliquez ce qui s\'est passé.', 'tags' => ['studies', 'education']],
            ],
        ],

        // 2
        [
            'title' => 'Fêtes et événements',
            'objectives' => [
                'raconter une fête ou un événement familial passé',
                'décrire une fête traditionnelle ou religieuse de votre pays',
                "souhaiter et féliciter selon l'occasion",
            ],
            'vocabulary' => [
                'wedding' => 'mariage (cérémonie)',
                'funeral' => 'funérailles',
                'birthday' => 'anniversaire',
                'naming ceremony' => 'baptême traditionnel (cérémonie de nom)',
                'festival' => 'fête, festival',
                'Christmas' => 'Noël',
                'Eid / Tabaski' => 'Aïd / Tabaski',
                'guest' => 'invité',
                'to celebrate' => 'fêter, célébrer',
                'to dance' => 'danser',
                'drums' => 'tambours',
                'traditional clothes' => 'tenue traditionnelle',
                'crowd' => 'foule',
                'gift / present' => 'cadeau',
                'congratulations!' => 'félicitations !',
                'happy birthday!' => 'joyeux anniversaire !',
                'my condolences' => 'mes condoléances',
                'it was fun' => "c'était amusant",
            ],
            'grammar' => [
                'Prétérit du verbe be : was / were (« It was great », « There were a lot of guests »).',
                'Les mots de liaison du récit : when, while, because, so.',
            ],
            'functions' => [
                'Décrire une fête : « There were hundreds of guests. People danced all night. »',
                'Expliquer une tradition : « During Tabaski, families share meat with their neighbors. »',
                "Féliciter et compatir : « Congratulations on your wedding! », « I'm so sorry for your loss. »",
            ],
            'pronunciation' => [
                'Was et were sont faibles dans la phrase ([wəz], [wɚ]) mais forts en réponse courte : « Yes, it was! ».',
                'Accent tonique : CELebrate, celeBRAtion, traDItional (et non « TRAditional » à la française).',
            ],
            'spoken' => [
                "« It was a blast! » = c'était génial.",
                "« The place was packed » = c'était bondé.",
            ],
            'pitfalls' => [
                "Faux ami : « une cérémonie de mariage » = a wedding ; marriage désigne plutôt l'union, la vie de couple.",
                '« I was very amused » ✗ (amusé par une blague) → « I had a lot of fun » ✓.',
            ],
            'situations' => [
                ['title' => 'Raconter un mariage traditionnel', 'description' => 'Vous racontez à une collègue anglophone de votre entreprise internationale le mariage traditionnel auquel vous êtes allé le week-end dernier à Kpalimé.', 'tags' => ['family', 'conversation', 'work']],
                ['title' => 'Présenter une fête togolaise', 'description' => 'Un visiteur anglophone vous demande comment on fête l\'Indépendance du 27 avril ou une fête religieuse au Togo.', 'tags' => ['conversation', 'travel']],
                ['title' => 'Message de félicitations', 'description' => 'Vous laissez un message vocal WhatsApp à un partenaire d\'affaires étranger anglophone pour féliciter sa famille d\'une naissance.', 'tags' => ['phone', 'business']],
            ],
        ],

        // 3
        [
            'title' => 'Comparer et choisir',
            'objectives' => [
                'comparer deux produits, deux lieux ou deux personnes',
                'dire ce qui est le meilleur ou le moins cher',
                "justifier simplement un choix d'achat",
            ],
            'vocabulary' => [
                'cheaper' => 'moins cher',
                'more expensive' => 'plus cher',
                'better / the best' => 'meilleur / le meilleur',
                'worse / the worst' => 'pire / le pire',
                'quality' => 'qualité',
                'brand' => 'marque',
                'second-hand' => "d'occasion",
                'brand new' => 'neuf',
                'guarantee' => 'garantie',
                'battery' => 'batterie, pile',
                'screen' => 'écran',
                'strong' => 'solide',
                'light / heavy' => 'léger / lourd',
                'big / small' => 'grand / petit',
                'choice' => 'choix',
                'to prefer' => 'préférer',
                'it depends' => 'ça dépend',
            ],
            'grammar' => [
                'Le comparatif : adjectif court + -er + than (cheaper than), long avec more (more expensive than).',
                'Le superlatif : the + -est (the cheapest), the most (the most reliable).',
                'Irréguliers : good → better → the best ; bad → worse → the worst ; far → further.',
            ],
            'functions' => [
                'Comparer : « This phone is cheaper than that one, but the battery is worse. »',
                "Choisir : « I'll take the Samsung. It's the best for the price. »",
                'Demander conseil : « Which one is better for taking photos? »',
            ],
            'pronunciation' => [
                "Than est faible [ðən] : « cheaper than » se dit presque « cheaper-th'n ».",
                "Better [ˈbɛɾɚ] et cheaper [ˈtʃiːpɚ] à l'américaine : le « r » final se prononce légèrement, sans « r » roulé, et le « t » de better devient un « r » français très bref.",
            ],
            'spoken' => [
                "« It's a bit pricey » = c'est un peu cher.",
                '« Which one would you go for? » = lequel tu prendrais ?',
            ],
            'pitfalls' => [
                'Pas de double comparatif : « more cheaper » ✗ → « cheaper » ✓.',
                '« Than » et non « that » après un comparatif : « bigger than », pas « bigger that ».',
            ],
            'situations' => [
                ['title' => 'Acheter un téléphone', 'description' => 'Dans une boutique de téléphones du Grand Marché, comparez deux modèles avec le vendeur et choisissez.', 'tags' => ['market', 'phone', 'tech']],
                ['title' => 'Comparer deux fournisseurs', 'description' => 'Vous comparez au téléphone les prix et la qualité de deux fournisseurs de tissu, l\'un à Lomé, l\'autre à l\'étranger.', 'tags' => ['business', 'phone']],
                ['title' => 'Choisir son université', 'description' => 'Avec un conseiller, comparez l\'université de Lomé et celle de Kara pour un cursus en anglais : coût, distance, cours.', 'tags' => ['studies', 'education']],
            ],
        ],

        // 4
        [
            'title' => 'Au téléphone et sur WhatsApp',
            'objectives' => [
                'commencer et terminer un appel poliment',
                'laisser et prendre un message simple',
                'comprendre et envoyer un message vocal court',
            ],
            'vocabulary' => [
                'to call / to phone' => 'appeler',
                'to call back' => 'rappeler',
                'missed call' => 'appel manqué',
                'voice message' => 'message vocal',
                'to text' => 'envoyer un SMS / un message',
                'to hang up' => 'raccrocher',
                'to hold on' => 'patienter (au téléphone)',
                'the line is busy' => 'la ligne est occupée',
                'network' => 'réseau',
                'credit / airtime' => 'crédit (téléphone)',
                'data bundle' => 'forfait internet',
                'message' => 'message',
                'speaking' => "c'est moi (au téléphone)",
                "who's calling?" => "qui est à l'appareil ?",
                'can I speak to…?' => 'puis-je parler à… ?',
                "I'll put you through" => 'je vous passe la personne',
                'battery is low' => 'la batterie est faible',
            ],
            'grammar' => [
                "Will pour une décision immédiate : « I'll call you back », « I'll tell her ».",
                'Could / can pour demander poliment : « Could you speak louder, please? ».',
            ],
            'functions' => [
                'Ouvrir un appel : « Hello, this is Mawuli from Lomé Transit. Can I speak to Mr. Johnson? »',
                "Prendre un message : « He's not here. Can I take a message? »",
                "Gérer la mauvaise connexion : « Sorry, the network is bad. I'll call you back. »",
            ],
            'pronunciation' => [
                'Intonation montante pour les questions oui/non au téléphone : « Is that Ama? ↗ ».',
                'Épeler les e-mails : at = @, dot = ., underscore = _.',
            ],
            'spoken' => [
                '« Hang on a sec! » = attends une seconde.',
                "« You're breaking up » = ta voix coupe (mauvais réseau).",
            ],
            'pitfalls' => [
                'Au téléphone on dit « This is Kossi », pas « I am Kossi ».',
                'Faux ami : « un portable » = a cell phone (en anglais britannique, on dit aussi a mobile phone) ; « a portable » est un adjectif (transportable).',
            ],
            'situations' => [
                ['title' => 'Appeler un client à l\'étranger', 'description' => 'Vous appelez un client étranger anglophone pour confirmer une livraison partie du port autonome de Lomé ; sa secrétaire prend un message.', 'tags' => ['business', 'phone', 'work']],
                ['title' => 'Message vocal WhatsApp', 'description' => 'Vous laissez un message vocal à un ami anglophone rencontré en ligne, de passage à Lomé, pour fixer un rendez-vous.', 'tags' => ['phone', 'conversation']],
                ['title' => 'Service client', 'description' => 'Votre forfait internet ne marche pas pendant un séjour à l\'étranger : vous expliquez le problème à un conseiller du service client.', 'tags' => ['phone', 'tech']],
            ],
        ],

        // 5
        [
            'title' => "Voyager à l'étranger",
            'objectives' => [
                'acheter un billet et comprendre les horaires de départ',
                'répondre aux questions simples au contrôle des passeports',
                "changer de l'argent et demander des informations pendant un voyage",
            ],
            'vocabulary' => [
                'ticket' => 'billet',
                'one-way / round-trip' => 'aller simple / aller-retour',
                'flight' => 'vol',
                'boarding pass' => "carte d'embarquement",
                'gate' => "porte d'embarquement",
                'departure' => 'départ',
                'arrival' => 'arrivée',
                'to leave' => 'partir',
                'to arrive' => 'arriver',
                'passport control' => 'contrôle des passeports',
                'passport' => 'passeport',
                'ID card' => "carte d'identité",
                'customs' => 'douane',
                'luggage / bags' => 'bagages',
                'to declare' => 'déclarer',
                'purpose of visit' => 'motif du séjour',
                'on business' => 'pour affaires',
                'on vacation' => 'en vacances',
                'delay' => 'retard',
                'to exchange money' => "changer de l'argent",
                'exchange rate' => 'taux de change',
            ],
            'grammar' => [
                "Prépositions de temps : at 6 o'clock, on Monday, in the morning, in March.",
                'Questions sur les horaires : « What time does the bus leave? », « How long does it take? ».',
            ],
            'functions' => [
                'Acheter un billet : « A round-trip ticket, please. What time is the next flight? »',
                "Au contrôle des passeports : « I'm here on business. I'm staying for three days at a hotel downtown. »",
                "Changer de l'argent : « What's the exchange rate today for CFA francs? »",
            ],
            'pronunciation' => [
                "Passport [ˈpæspɔːrt] à l'américaine : « a » court comme dans cat, et le « r » se prononce.",
                'Le « s » sonore de business [ˈbɪznəs] et de visit.',
            ],
            'spoken' => [
                "« How long's the trip? » = combien de temps dure le trajet ?",
                '« Where are you headed? » = où allez-vous ? (question fréquente des agents et des autres voyageurs).',
            ],
            'pitfalls' => [
                'Luggage est indénombrable : « my luggage is heavy », jamais « luggages ».',
                'Faux ami : « journey » = trajet, voyage ; « journée » = day.',
            ],
            'situations' => [
                ['title' => 'Au guichet de la compagnie aérienne', 'description' => 'À l\'aéroport de Lomé, achetez un billet pour un voyage à l\'étranger : demandez l\'heure de départ, la durée du vol et le prix des bagages.', 'tags' => ['travel', 'transport']],
                ['title' => 'Contrôle des passeports', 'description' => 'En arrivant à l\'aéroport, à l\'étranger, l\'agent anglophone du contrôle des passeports vous demande le motif de votre séjour, votre adresse et ce que vous transportez.', 'tags' => ['travel', 'admin', 'business']],
                ['title' => 'Bureau de change', 'description' => 'À l\'aéroport, en arrivant à l\'étranger, vous changez des francs CFA dans la monnaie locale et vérifiez le taux et la somme reçue.', 'tags' => ['travel', 'business']],
            ],
        ],

        // 6
        [
            'title' => "À l'hôtel et au restaurant",
            'objectives' => [
                'réserver une chambre et faire le check-in',
                'faire une demande ou une réclamation polie',
                'commander un repas et réagir à un problème',
            ],
            'vocabulary' => [
                'reservation' => 'réservation (en anglais britannique, on dit aussi « booking »)',
                'single room / double room' => 'chambre simple / double',
                'check-in / check-out' => 'arrivée / départ (hôtel)',
                'reception' => 'réception',
                'key' => 'clé',
                'air conditioning' => 'climatisation',
                'fan' => 'ventilateur',
                'towel' => 'serviette',
                'breakfast included' => 'petit-déjeuner compris',
                'Wi-Fi password' => 'code Wi-Fi',
                'to complain' => 'se plaindre',
                "it doesn't work" => 'ça ne marche pas',
                'noisy' => 'bruyant',
                'waiter / waitress' => 'serveur / serveuse',
                'appetizer / main course' => 'entrée / plat principal',
                'dessert' => 'dessert',
                'receipt' => 'reçu',
            ],
            'grammar' => [
                'Could I / Could you pour les demandes polies : « Could I have another towel? », « Could you change the room? ».',
                "I'd like + nom ou to + verbe : « I'd like to check out », « I'd like a room for two nights ».",
            ],
            'functions' => [
                "Réserver : « I'd like to book a double room for two nights from Friday. »",
                "Se plaindre poliment : « Sorry, the air conditioning doesn't work. Could someone fix it? »",
                'Au restaurant : « Excuse me, I ordered the chicken, not the fish. »',
            ],
            'pronunciation' => [
                "Could you [kʊdʒu] : les deux mots se fondent à l'oral.",
                'Receipt [rɪˈsiːt] : le « p » est muet.',
            ],
            'spoken' => [
                '« Sorry to bother you, but… » = désolé de vous déranger, mais…',
                "« Is breakfast included? » est la question la plus utile à l'hôtel.",
            ],
            'pitfalls' => [
                "Faux ami : « la réception » d'un hôtel = reception, mais « recevoir un client » = to welcome / to receive a guest.",
                "Faux ami : en anglais américain, « an entrée » est le plat principal ; l'entrée se dit an appetizer.",
            ],
            'situations' => [
                ['title' => 'Arrivée à l\'hôtel à l\'étranger', 'description' => 'Vous faites le check-in dans un hôtel à l\'étranger pour un séminaire international et demandez le code Wi-Fi et l\'heure du petit-déjeuner.', 'tags' => ['travel', 'work']],
                ['title' => 'Réclamation d\'un client à Lomé', 'description' => 'Vous êtes réceptionniste à Lomé : un client anglophone se plaint que la climatisation ne marche pas.', 'tags' => ['work', 'travel']],
                ['title' => 'Dîner d\'affaires', 'description' => 'Dans un restaurant de Lomé avec un partenaire étranger anglophone, commandez, réglez une erreur de commande et demandez l\'addition.', 'tags' => ['business']],
            ],
        ],

        // 7
        [
            'title' => 'Chez le médecin',
            'objectives' => [
                'décrire des symptômes et leur évolution',
                'comprendre des consignes de traitement',
                'prendre ou déplacer un rendez-vous',
            ],
            'vocabulary' => [
                'symptom' => 'symptôme',
                'to feel dizzy' => 'avoir des vertiges',
                'to vomit / to throw up' => 'vomir',
                'diarrhea' => 'diarrhée',
                'rash' => 'éruption, boutons',
                'blood pressure' => 'tension artérielle',
                'blood test' => 'prise de sang',
                'injection' => 'piqûre',
                'prescription' => 'ordonnance',
                'side effects' => 'effets secondaires',
                'allergic to' => 'allergique à',
                'pregnant' => 'enceinte',
                'mosquito net' => 'moustiquaire',
                'to get better' => 'aller mieux',
                'to get worse' => "s'aggraver",
                'health insurance' => 'assurance maladie',
                'emergency' => 'urgence',
            ],
            'grammar' => [
                "Must / have to pour l'obligation : « You must take this twice a day », « I have to stay in bed ».",
                "Mustn't = interdiction ; don't have to = pas obligatoire (différence importante).",
                "Present perfect avec for/since pour une durée qui continue : « I've had this cough since Monday ».",
            ],
            'functions' => [
                "Décrire : « I've been tired for a week and I feel dizzy in the morning. »",
                'Vérifier une consigne : « So I take one tablet after each meal, right? »',
                "Informer : « I'm allergic to penicillin. »",
            ],
            'pronunciation' => [
                'Le « h » de health et de have (à bien souffler).',
                "Diarrhea [ˌdaɪəˈriːə], allergic [əˈlɝdʒɪk] : l'accent tonique n'est pas sur la même syllabe qu'en français.",
            ],
            'spoken' => [
                "« I'm feeling under the weather » = je ne suis pas en forme.",
                '« Take it easy » = repose-toi, ménage-toi.',
            ],
            'pitfalls' => [
                "« You mustn't come » (interdit) ≠ « You don't have to come » (pas obligé) — les francophones confondent souvent.",
                'Faux ami : « une ordonnance » = a prescription ; « an ordinance » est un règlement municipal.',
            ],
            'situations' => [
                ['title' => 'Consultation à l\'étranger', 'description' => 'En voyage à l\'étranger, vous expliquez vos symptômes de paludisme à un médecin et comprenez son traitement.', 'tags' => ['health', 'travel']],
                ['title' => 'Infirmier et patient', 'description' => 'Vous êtes infirmier au CHU Sylvanus Olympio : interrogez un patient anglophone sur ses symptômes et ses allergies.', 'tags' => ['health', 'work']],
                ['title' => 'Déplacer un rendez-vous', 'description' => 'Appelez une clinique de Lomé pour reporter le rendez-vous de votre mère avec un médecin anglophone.', 'tags' => ['health', 'phone', 'family']],
            ],
        ],

        // 8
        [
            'title' => "L'école et l'université",
            'objectives' => [
                'parler de vos études ou de celles de vos enfants',
                'comprendre et expliquer des règles',
                "demander des informations administratives à l'école",
            ],
            'vocabulary' => [
                'primary school' => 'école primaire',
                'secondary school / high school' => 'collège-lycée',
                'university' => 'université',
                'degree' => 'diplôme universitaire (licence…)',
                'subject' => 'matière',
                'math / science' => 'maths / sciences',
                'schedule' => 'emploi du temps',
                'lecture' => 'cours magistral',
                'exam / test' => 'examen / contrôle',
                'to pass / to fail' => 'réussir / échouer (examen)',
                'to review / to study' => 'réviser',
                'fees' => 'frais de scolarité',
                'uniform' => 'uniforme',
                'principal' => "directeur d'école",
                'professor' => "enseignant à l'université",
                'assignment' => 'devoir, travail à rendre',
                'to enroll' => "s'inscrire",
                'scholarship' => "bourse d'études",
            ],
            'grammar' => [
                "Have to / don't have to pour les règles : « Students have to wear a uniform ».",
                "Can / can't pour les permissions : « You can't use your phone in class ».",
            ],
            'functions' => [
                "Parler de ses études : « I'm studying accounting at the University of Lomé. I'm in my second year. »",
                'Expliquer les règles : « You have to pay the fees before the exams. »',
                'Demander : « When is the deadline to enroll? »',
            ],
            'pronunciation' => [
                'Subject : nom [ˈsʌbdʒɪkt] (accent au début).',
                'University [ˌjuːnɪˈvɝsəɾi] commence par le son « you ».',
            ],
            'spoken' => [
                "« I'm majoring in law » = je fais des études de droit (ma matière principale).",
                "« I have exams coming up » = j'ai bientôt des examens.",
            ],
            'pitfalls' => [
                'Faux ami : « passer un examen » = to take an exam (en anglais britannique, on dit aussi to sit an exam) ; « to pass an exam » = le réussir !',
                'Faux ami : « a lecture » est un cours magistral ; « la lecture » = reading.',
            ],
            'situations' => [
                ['title' => 'Inscription à l\'école bilingue', 'description' => 'Dans une école bilingue de Lomé, demandez les frais, l\'uniforme et l\'emploi du temps pour votre enfant.', 'tags' => ['education', 'family', 'admin']],
                ['title' => 'Entretien de bourse', 'description' => 'Vous présentez vos études à l\'université de Lomé à un jury de bourse anglophone.', 'tags' => ['studies', 'exam']],
                ['title' => 'Premier cours', 'description' => 'Vous êtes enseignant à Kara et expliquez les règles de la classe à de nouveaux élèves anglophones.', 'tags' => ['education', 'work']],
            ],
        ],

        // 9
        [
            'title' => 'Chercher un emploi',
            'objectives' => [
                "comprendre une petite annonce d'emploi",
                'présenter votre parcours et votre expérience simplement',
                "répondre aux questions courantes d'un premier entretien",
            ],
            'vocabulary' => [
                'job ad / job posting' => "offre d'emploi",
                'position / opening' => 'poste',
                'to apply for' => 'postuler à',
                'application' => 'candidature',
                'résumé' => 'CV, curriculum vitae (en anglais britannique, on dit aussi « CV »)',
                'experience' => 'expérience',
                'skills' => 'compétences',
                'qualifications' => 'diplômes, qualifications',
                'full-time / part-time' => 'temps plein / temps partiel',
                'internship' => 'stage',
                'interview' => 'entretien',
                'employer' => 'employeur',
                'reliable' => 'fiable',
                'hard-working' => 'travailleur',
                'to be in charge of' => 'être responsable de',
                'references' => 'recommandations',
                'available' => 'disponible',
            ],
            'grammar' => [
                'Prétérit pour le parcours terminé : « I worked at Ecobank for two years (de 2019 à 2021) ».',
                "Present perfect pour l'expérience jusqu'à aujourd'hui : « I have worked in sales since 2020 ».",
            ],
            'functions' => [
                'Se présenter : « I have a degree in marketing and three years of experience in sales. »',
                "Parler de ses qualités : « I'm reliable and I work well in a team. »",
                'Poser une question au recruteur : « What are the working hours? »',
            ],
            'pronunciation' => [
                'Experience [ɪkˈspɪriəns] : accent sur « pe ».',
                'Le son [dʒ] de job et de manager : « dj », pas « j » français.',
            ],
            'spoken' => [
                '« Tell me about yourself » est presque toujours la première question.',
                "« I'm a fast learner » = j'apprends vite.",
            ],
            'pitfalls' => [
                'Faux ami : « un stage » = an internship ; « a stage » = une scène de théâtre ou une étape.',
                'Faux ami : « actually » = en fait ; « actuellement » = currently.',
            ],
            'situations' => [
                ['title' => 'Premier entretien', 'description' => 'Le recruteur d\'une ONG installée à Lomé vous pose les questions classiques d\'un premier entretien.', 'tags' => ['work', 'exam']],
                ['title' => 'Appeler pour une annonce', 'description' => 'Vous appelez pour vous renseigner sur une offre de chauffeur-livreur à temps partiel dans une entreprise de Lomé.', 'tags' => ['work', 'phone', 'transport']],
                ['title' => 'Stage dans une entreprise internationale', 'description' => 'Vous présentez votre parcours d\'étudiant pour obtenir un stage dans une entreprise internationale installée à Lomé.', 'tags' => ['studies', 'work']],
            ],
        ],

        // 10
        [
            'title' => 'Décrire les personnes',
            'objectives' => [
                "décrire l'apparence physique de quelqu'un",
                "parler du caractère d'une personne",
                'comparer deux personnes que vous connaissez',
            ],
            'vocabulary' => [
                'tall / short' => 'grand / petit',
                'slim / thin' => 'mince',
                'fat / overweight' => 'gros / en surpoids',
                'beard' => 'barbe',
                'braids' => 'tresses',
                'bald' => 'chauve',
                'in his thirties' => 'dans la trentaine',
                'friendly' => 'aimable',
                'kind' => 'gentil',
                'funny' => 'drôle',
                'shy' => 'timide',
                'honest' => 'honnête',
                'lazy' => 'paresseux',
                'patient' => 'patient',
                'generous' => 'généreux',
                'strict' => 'sévère',
                'to look like' => 'ressembler à',
            ],
            'grammar' => [
                'What does he look like? (apparence) ≠ What is he like? (caractère).',
                'Ordre des adjectifs : opinion, taille, âge, couleur — « a nice tall young man ».',
            ],
            'functions' => [
                "Décrire : « She's tall and slim, in her twenties, with long braids. »",
                "Parler du caractère : « He's very friendly but a bit lazy. »",
                "Identifier quelqu'un : « Look for a short man with a gray beard and a blue shirt. »",
            ],
            'pronunciation' => [
                "Le « h » d'honest est muet [ˈɑːnɪst], comme en français, mais celui de happy se prononce.",
                'Les mots en -y final (funny, lazy) : [i] bref, pas de « aï ».',
            ],
            'spoken' => [
                "« He's a nice guy » = c'est un type sympa.",
                '« She takes after her mother » = elle tient de sa mère.',
            ],
            'pitfalls' => [
                'Faux ami : « sympathetic » = compatissant ; « sympathique » = nice / friendly.',
                '« He is like his father » (caractère) vs « He looks like his father » (physique).',
            ],
            'situations' => [
                ['title' => 'Accueillir à l\'aéroport de Lomé', 'description' => 'Vous devez accueillir à l\'aéroport un collègue étranger que vous n\'avez jamais vu : décrivez-vous au téléphone pour vous reconnaître.', 'tags' => ['travel', 'work', 'phone']],
                ['title' => 'Recommander un employé', 'description' => 'Votre partenaire étranger, qui ouvre une boutique à Lomé, cherche un vendeur : décrivez le caractère d\'une personne que vous recommandez.', 'tags' => ['business', 'work']],
                ['title' => 'Parler de ses proches', 'description' => 'Vous décrivez vos frères et sœurs, restés à Kara, à un ami anglophone : physique et caractère, en les comparant.', 'tags' => ['family', 'conversation']],
            ],
        ],

        // 11
        [
            'title' => 'Banque, transferts et administration',
            'objectives' => [
                "ouvrir un compte ou faire un transfert d'argent",
                "remplir un formulaire à l'oral (questions-réponses)",
                'demander des documents et des explications à un guichet',
            ],
            'vocabulary' => [
                'bank account' => 'compte bancaire',
                'to deposit' => 'déposer',
                'to withdraw' => 'retirer',
                'ATM' => 'distributeur',
                'transfer' => 'virement, transfert',
                'mobile money' => 'mobile money',
                'fee / charge' => 'frais',
                'balance' => 'solde',
                'form' => 'formulaire',
                'to fill out' => 'remplir',
                'to sign' => 'signer',
                'signature' => 'signature',
                'date of birth' => 'date de naissance',
                'proof of address' => 'justificatif de domicile',
                'copy' => 'photocopie',
                'line' => "file d'attente (en anglais britannique, on dit aussi « queue »)",
                'counter' => 'guichet',
                'deadline' => 'date limite',
            ],
            'grammar' => [
                "Questions indirectes polies : « Can you tell me how much the fee is? » (pas d'inversion après tell me).",
                "Need to / don't need to : « You need to bring a copy of your ID ».",
            ],
            'functions' => [
                "Au guichet : « I'd like to send 50,000 francs to my brother in Kara. What's the fee? »",
                "Remplir un formulaire : « What's your date of birth? — March twelfth, 1990. »",
                'Vérifier : « Do I need to sign here? »',
            ],
            'pronunciation' => [
                'Les dates à l\'américaine : « March twelfth » [twelfθ] ; th final à bien marquer.',
                'Withdraw [wɪðˈdrɑː] : « th » doux.',
            ],
            'spoken' => [
                '« Hold on, let me check » = attendez, je vérifie.',
                '« Next, please! » = au suivant !',
            ],
            'pitfalls' => [
                "Faux ami : « la monnaie d'un pays » = currency ; change = monnaie rendue.",
                "Faux ami : « a formulary » n'existe pas au sens de formulaire : dites a form.",
            ],
            'situations' => [
                ['title' => 'Envoyer de l\'argent', 'description' => 'Dans une agence de transfert de Lomé, envoyez de l\'argent à un proche qui étudie à l\'étranger et demandez les frais.', 'tags' => ['admin', 'family', 'business']],
                ['title' => 'Ouvrir un compte professionnel', 'description' => 'Vous ouvrez un compte pour votre entreprise dans une banque de Lomé qui travaille en anglais : documents à fournir.', 'tags' => ['business', 'admin']],
                ['title' => 'Permis de séjour d\'un collègue', 'description' => 'Vous aidez un collègue étranger anglophone à comprendre les documents à fournir pour sa carte de séjour au Togo.', 'tags' => ['admin', 'work']],
            ],
        ],

        // 12
        [
            'title' => 'Projets et prévisions',
            'objectives' => [
                'parler de vos projets proches et lointains',
                'faire des prévisions simples',
                'faire des promesses et des offres',
            ],
            'vocabulary' => [
                'future' => 'avenir',
                'plan' => 'projet, plan',
                'goal' => 'objectif',
                'dream' => 'rêve',
                'to hope' => 'espérer',
                'to save money' => 'économiser',
                'to open a store' => 'ouvrir une boutique, un magasin',
                'to get married' => 'se marier',
                'to move' => 'déménager',
                'abroad' => "à l'étranger",
                'next year' => "l'année prochaine",
                'in five years' => 'dans cinq ans',
                'probably' => 'probablement',
                'definitely' => 'certainement',
                'I promise' => 'je promets',
                'soon' => 'bientôt',
                'to expand' => 'développer, agrandir',
            ],
            'grammar' => [
                'Be going to (intention déjà décidée) vs will (décision sur le moment, promesse, prévision).',
                "Hope + présent ou will : « I hope I'll pass », « I hope it doesn't rain ».",
            ],
            'functions' => [
                "Parler d'un projet : « I'm going to open a second store in Kara next year. »",
                'Prévoir : « Prices will probably go up next month. »',
                "Promettre / proposer : « I'll send you the documents tonight, I promise. »",
            ],
            'pronunciation' => [
                "La contraction I'll [aɪl] : « I will » sonne insistant ou solennel.",
                "Won't [woʊnt] ≠ want [wɑːnt] : attention, sens opposés !",
            ],
            'spoken' => [
                "« I'm planning to… » = je compte…",
                '« Fingers crossed! » = croisons les doigts !',
            ],
            'pitfalls' => [
                "Ne confondez pas won't (ne fera pas) et want (vouloir).",
                'Faux ami : « eventually » = finalement, à terme ; « éventuellement » = possibly.',
            ],
            'situations' => [
                ['title' => 'Projet d\'entreprise', 'description' => 'Vous présentez à un investisseur anglophone vos projets pour votre commerce à Lomé dans les cinq prochaines années.', 'tags' => ['business', 'work']],
                ['title' => 'Après la licence à Kara', 'description' => 'Un ami vous demande ce que vous allez faire après vos études à l\'université de Kara : travail, voyage, master.', 'tags' => ['studies', 'conversation']],
                ['title' => 'Promesse à un client', 'description' => 'Un client étranger attend sa commande, expédiée du port autonome de Lomé : rassurez-le et promettez une date de livraison.', 'tags' => ['business', 'phone']],
            ],
        ],

    ],

];
