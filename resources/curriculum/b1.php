<?php

/*
|--------------------------------------------------------------------------
| Programme officiel — niveau B1 (intermédiaire)
|--------------------------------------------------------------------------
|
| Contenu original, aligné sur les descripteurs B1 du CECRL : comprendre les
| points essentiels d'une discussion sur des sujets familiers, se débrouiller
| dans la plupart des situations, raconter un événement, une expérience,
| exposer brièvement des raisons ou des explications pour un projet ou une idée.
|
*/

return [

    'title' => "B1 — Intermédiaire : être autonome à l'oral",

    'units' => [

        // 1
        [
            'title' => 'Mon parcours et mes expériences',
            'objectives' => [
                'parler de ce que vous avez déjà fait (ou jamais fait) dans votre vie',
                "distinguer une expérience de vie d'un événement daté",
                "relancer une conversation en posant des questions sur l'expérience des autres",
            ],
            'vocabulary' => [
                'ever / never' => 'déjà (dans une question) / jamais',
                'already / yet' => 'déjà / encore (pas encore)',
                'just' => 'venir de',
                'experience' => 'expérience',
                'achievement' => 'réussite, accomplissement',
                'to manage to' => 'réussir à',
                'to give up' => 'abandonner',
                'to take part in' => 'participer à',
                'to run a business' => 'diriger une affaire',
                'to be promoted' => 'être promu',
                'to graduate' => 'obtenir son diplôme',
                'challenge' => 'défi',
                'proud of' => 'fier de',
                'so far' => "jusqu'à présent",
                'over the years' => 'au fil des années',
                'since then' => 'depuis',
                'a turning point' => 'un tournant',
                'to look back' => 'regarder en arrière',
                'lesson learned' => 'leçon tirée',
            ],
            'grammar' => [
                "Present perfect pour l'expérience sans date : « I've worked in three countries », « Have you ever been to Abuja? ».",
                "Prétérit dès qu'il y a un moment précis : « I went there in 2019 » (pas « I have been there in 2019 »).",
                "Already, yet, just avec le present perfect : « I've just finished », « I haven't paid yet ».",
            ],
            'functions' => [
                "Raconter son parcours : « I've been a trader for ten years. I started with a small table at the market. »",
                'Interroger : « Have you ever thought about working abroad? — Yes, I applied once, in 2021. »',
                "Mettre en valeur une réussite : « What I'm most proud of is that I've trained five young people. »",
            ],
            'pronunciation' => [
                "Les formes faibles : I've [aɪv], she's (= she has) [ʃiːz], haven't [ˈhævnt].",
                'Le « h » de have dans les réponses courtes accentuées : « Yes, I have! ».',
            ],
            'spoken' => [
                "« Been there, done that » = je connais, je l'ai déjà fait.",
                "« I've never done it before, but I'm up for it » = je ne l'ai jamais fait, mais je suis partant.",
            ],
            'pitfalls' => [
                "Le passé composé français se traduit souvent par le prétérit : « Hier, j'ai vu… » = « Yesterday I saw… ».",
                '« Since » + point de départ (since 2020), « for » + durée (for five years) : « depuis » a deux traductions.',
            ],
            'situations' => [
                ['title' => 'Présentation en formation', 'description' => "Au premier jour d'une formation professionnelle, vous racontez votre parcours et vos expériences au groupe.", 'tags' => ['work', 'education']],
                ['title' => "Rencontre d'entrepreneurs", 'description' => 'À un événement de la chambre de commerce, échangez sur vos expériences de commerce avec un entrepreneur ghanéen.', 'tags' => ['business', 'conversation']],
                ['title' => 'Candidature à une bourse', 'description' => 'Un jury vous demande ce que vous avez déjà accompli et ce que vous avez appris de vos difficultés.', 'tags' => ['studies', 'exam']],
            ],
        ],

        // 2
        [
            'title' => 'Raconter un incident',
            'objectives' => [
                "raconter un incident avec l'arrière-plan et l'action principale",
                'exprimer la surprise, la peur ou le soulagement',
                "réagir naturellement au récit de quelqu'un",
            ],
            'vocabulary' => [
                'accident' => 'accident',
                'to happen' => 'arriver, se produire',
                'suddenly' => 'soudain',
                'luckily / unfortunately' => 'heureusement / malheureusement',
                'to steal / stolen' => 'voler / volé',
                'thief' => 'voleur',
                'to break down' => 'tomber en panne',
                'flat tyre' => 'pneu crevé',
                'to crash into' => 'percuter',
                'injured' => 'blessé',
                'witness' => 'témoin',
                'to report' => 'signaler, déclarer',
                'police station' => 'commissariat',
                'scared' => 'effrayé',
                'relieved' => 'soulagé',
                'in the end' => 'finalement',
                'meanwhile' => 'pendant ce temps',
                'it turned out that' => "il s'est avéré que",
            ],
            'grammar' => [
                'Prétérit progressif (past continuous) pour le décor : « I was driving home when it started to rain ».',
                'When / while : « While I was waiting, someone stole my bag », « When the police arrived, he ran away ».',
                'Le plus-que-parfait pour revenir en arrière : « I realised I had left my phone in the taxi ».',
            ],
            'functions' => [
                'Planter le décor : « It was raining heavily and I was riding my motorbike near the stadium. »',
                'Faire vivre le récit : « And then, suddenly, a goat crossed the road! »',
                'Réagir : « Oh no! Were you hurt? », « Thank God nobody was injured. »',
            ],
            'pronunciation' => [
                'Was dans « I was driving » est faible [wəz] ; ne le prononcez pas comme dans une réponse courte.',
                'Les groupes de consonnes : crashed [kræʃt], stopped [stɒpt] — pas de voyelle ajoutée.',
            ],
            'spoken' => [
                "« You won't believe what happened to me! » = tu ne vas pas croire ce qui m'est arrivé !",
                "« No way! » / « You're kidding! » = pas possible ! / tu plaisantes !",
            ],
            'pitfalls' => [
                "N'utilisez pas le past continuous pour tout : l'action principale est au prétérit simple.",
                'Faux ami : « an injury » = une blessure ; « une injure » = an insult.',
            ],
            'situations' => [
                ['title' => 'Déclaration de vol', 'description' => "On vous a volé votre téléphone dans un bus à Accra : racontez l'incident au policier.", 'tags' => ['travel', 'admin']],
                ['title' => 'Retard de livraison', 'description' => "Votre camion est tombé en panne sur la route de Cotonou : expliquez au client ce qui s'est passé.", 'tags' => ['business', 'transport', 'phone']],
                ['title' => 'Histoire entre amis', 'description' => 'Racontez à un ami une aventure drôle ou effrayante vécue en voyage, il réagit et pose des questions.', 'tags' => ['conversation', 'family']],
            ],
        ],

        // 3
        [
            'title' => "L'entretien d'embauche",
            'objectives' => [
                'présenter vos points forts avec des exemples concrets',
                "parler d'une faiblesse de façon positive",
                'poser des questions pertinentes au recruteur',
            ],
            'vocabulary' => [
                'strength / weakness' => 'point fort / point faible',
                'to be good at' => 'être doué pour',
                'teamwork' => "travail d'équipe",
                'deadline' => 'date limite, échéance',
                'to meet a deadline' => 'respecter un délai',
                'to handle pressure' => 'gérer la pression',
                'to solve a problem' => 'résoudre un problème',
                'customer service' => 'service client',
                'responsibility' => 'responsabilité',
                'to improve' => 'améliorer, progresser',
                'motivated' => 'motivé',
                'organised' => 'organisé',
                'salary expectations' => 'prétentions salariales',
                'notice period' => 'préavis',
                'probation period' => "période d'essai",
                'to hire' => 'embaucher',
                'career' => 'carrière',
                'opportunity' => 'opportunité',
            ],
            'grammar' => [
                "Verbe + -ing après certains verbes et prépositions : « I enjoy working with people », « I'm good at solving problems ».",
                'Le modèle STAR au prétérit : situation, tâche, action, résultat.',
            ],
            'functions' => [
                'Donner un exemple : « For example, last year our supplier was late, so I found another one in two days. »',
                'Présenter une faiblesse : « I sometimes take on too much work, so now I use a planning app. »',
                'Questionner : « What would a typical day look like in this position? »',
            ],
            'pronunciation' => [
                "L'accent tonique des mots en -tion : responsiBILity, opporTUNity, motiVAtion.",
                'Weakness [ˈwiːknəs] : voyelle longue [iː].',
            ],
            'spoken' => [
                "« That's a good question — let me think for a second. » : gagner du temps poliment.",
                "« I'm a people person » = j'aime le contact humain.",
            ],
            'pitfalls' => [
                '« I am agree » ✗ → « I agree » ✓ ; de même « I am interested in », pas « I am interesting by ».',
                'Faux ami : « a formation » ≠ une formation professionnelle → training.',
            ],
            'situations' => [
                ['title' => 'Entretien dans une banque', 'description' => 'Entretien pour un poste de chargé de clientèle : questions sur vos points forts et faibles.', 'tags' => ['work', 'admin']],
                ['title' => 'Entretien en ligne', 'description' => 'Entretien vidéo avec une entreprise de logistique basée à Lagos : expliquez comment vous gérez la pression.', 'tags' => ['work', 'transport', 'tech']],
                ['title' => "Simulation d'oral d'admission", 'description' => "Préparez l'oral d'admission d'un master anglophone : motivations et projet professionnel.", 'tags' => ['studies', 'exam']],
            ],
        ],

        // 4
        [
            'title' => 'Commerce et importation',
            'objectives' => [
                'passer une commande et négocier des conditions',
                'parler de délais, de livraison et de paiement',
                'poser des conditions avec si',
            ],
            'vocabulary' => [
                'supplier' => 'fournisseur',
                'order' => 'commande',
                'quantity' => 'quantité',
                'in bulk' => 'en gros',
                'unit price' => 'prix unitaire',
                'quote' => 'devis',
                'invoice' => 'facture',
                'down payment / deposit' => 'acompte',
                'shipping' => 'expédition, transport',
                'container' => 'conteneur',
                'port' => 'port',
                'customs clearance' => 'dédouanement',
                'delivery time' => 'délai de livraison',
                'in stock / out of stock' => 'en stock / en rupture',
                'sample' => 'échantillon',
                'to negotiate' => 'négocier',
                'terms' => 'conditions',
                'warehouse' => 'entrepôt',
                'profit margin' => 'marge',
            ],
            'grammar' => [
                'Premier conditionnel : « If you order 200 boxes, we will give you a 5% discount ».',
                "Unless = sauf si : « We won't ship unless we receive the deposit ».",
                'Les nombres : thousands, millions, percentages (« five per cent »), « 2,500 » se lit « two thousand five hundred ».',
            ],
            'functions' => [
                'Demander un devis : « Could you send me a quote for 500 bags of rice, delivered to Lomé port? »',
                'Négocier : « If we pay 50% upfront, can you lower the unit price? »',
                'Suivre une livraison : « When will the container arrive? Has it cleared customs yet? »',
            ],
            'pronunciation' => [
                "Invoice [ˈɪnvɔɪs], supplier [səˈplaɪə] : déplacez l'accent par rapport au français.",
                'Per cent [pə ˈsent] : deux mots, accent sur cent.',
            ],
            'spoken' => [
                "« That's my best price » = c'est mon dernier prix.",
                "« Let's meet halfway » = coupons la poire en deux.",
            ],
            'pitfalls' => [
                'Faux ami : « commander » = to order ; « to command » = donner un ordre militaire.',
                'Faux ami : « a delay » = un retard ; « un délai » de livraison = delivery time / lead time.',
            ],
            'situations' => [
                ['title' => 'Commande à un fournisseur nigérian', 'description' => 'Vous commandez des pagnes à un fournisseur de Kano : quantité, prix, acompte, livraison.', 'tags' => ['business', 'phone']],
                ['title' => 'Au port de Lomé', 'description' => "Vous suivez le dédouanement d'un conteneur avec un transitaire anglophone.", 'tags' => ['business', 'transport', 'admin']],
                ['title' => 'Négociation avec un grossiste', 'description' => 'Un grossiste ghanéen veut acheter vos produits en gros : négociez prix et conditions de paiement.', 'tags' => ['business', 'market']],
            ],
        ],

        // 5
        [
            'title' => 'Donner son avis',
            'objectives' => [
                'exprimer et justifier une opinion',
                "être d'accord ou pas, poliment",
                'nuancer votre point de vue',
            ],
            'vocabulary' => [
                'opinion' => 'opinion, avis',
                'in my opinion' => 'à mon avis',
                'I think / I believe' => 'je pense / je crois',
                'to agree / disagree' => "être d'accord / pas d'accord",
                'point of view' => 'point de vue',
                'advantage / disadvantage' => 'avantage / inconvénient',
                'on the one hand' => "d'un côté",
                'on the other hand' => "d'un autre côté",
                'however' => 'cependant',
                'to be against / in favour of' => 'être contre / pour',
                'issue' => 'question, problème',
                'society' => 'société',
                'youth' => 'les jeunes',
                'unemployment' => 'chômage',
                'to make a point' => 'faire valoir un argument',
                'to some extent' => 'dans une certaine mesure',
                'fair / unfair' => 'juste / injuste',
                'reason' => 'raison',
            ],
            'grammar' => [
                "Les connecteurs : because, so, although, however, that's why.",
                "Les verbes d'opinion + that : « I don't think that's fair » (on met la négation sur think).",
            ],
            'functions' => [
                'Donner son avis : « In my opinion, young people should learn a trade as well as studying. »',
                "Être en désaccord poliment : « I see your point, but I'm not sure I agree. »",
                "Nuancer : « It's true to some extent, but it depends on the region. »",
            ],
            'pronunciation' => [
                'Intonation du désaccord poli : montée sur « but », chute à la fin.',
                'Issue [ˈɪʃuː] ou [ˈɪsjuː] : jamais « issue » à la française.',
            ],
            'spoken' => [
                "« Fair enough » = d'accord, c'est juste (pour accepter un argument).",
                "« I couldn't agree more » = je suis entièrement d'accord.",
            ],
            'pitfalls' => [
                '« I am agree » ✗ → « I agree » ✓ (agree est un verbe, pas un adjectif).',
                'Faux ami : « eventually » ≠ éventuellement ; « actually » ≠ actuellement.',
            ],
            'situations' => [
                ['title' => 'Débat à la radio', 'description' => 'Une radio anglophone vous interroge sur le chômage des jeunes : donnez et défendez votre avis.', 'tags' => ['conversation', 'exam']],
                ['title' => "Réunion d'équipe", 'description' => "Votre équipe discute d'un nouvel horaire de travail : donnez votre avis et réagissez à celui des autres.", 'tags' => ['work']],
                ['title' => 'Discussion en classe', 'description' => "En cours, on débat de l'usage du téléphone à l'école : avantages et inconvénients.", 'tags' => ['education', 'studies']],
            ],
        ],

        // 6
        [
            'title' => 'Problèmes et réclamations',
            'objectives' => [
                'présenter un problème clairement et calmement',
                'proposer ou exiger une solution',
                "gérer la réclamation d'un client mécontent",
            ],
            'vocabulary' => [
                'complaint' => 'réclamation',
                'faulty' => 'défectueux',
                'damaged' => 'endommagé',
                'refund' => 'remboursement',
                'replacement' => 'remplacement',
                'to exchange' => 'échanger',
                'to apologise' => "s'excuser",
                'inconvenience' => 'désagrément',
                'to sort out' => 'régler, arranger',
                'manager' => 'responsable',
                'to look into it' => 'examiner la question',
                'as soon as possible' => 'dès que possible',
                'disappointed' => 'déçu',
                'unacceptable' => 'inacceptable',
                'misunderstanding' => 'malentendu',
                'compensation' => 'dédommagement',
                'policy' => "règlement, politique (d'entreprise)",
            ],
            'grammar' => [
                'Formules de politesse indirectes : « I was wondering if you could… », « Would you mind + -ing ».',
                "Le passif simple pour éviter d'accuser : « The goods were damaged during delivery ».",
            ],
            'functions' => [
                "Se plaindre : « I'm calling about the fridge I bought last week. It's faulty. »",
                "Demander une solution : « I'd like a refund or a replacement, please. »",
                "Gérer un client : « I'm sorry for the inconvenience. Let me sort this out for you right away. »",
            ],
            'pronunciation' => [
                'Ton de la voix : calme et descendant ; une montée constante sonne agressive en anglais.',
                'Refund : nom [ˈriːfʌnd], verbe [rɪˈfʌnd].',
            ],
            'spoken' => [
                "« Leave it with me » = je m'en occupe.",
                "« I do apologise » : le do d'insistance rend l'excuse plus sincère.",
            ],
            'pitfalls' => [
                'Faux ami : « to demand » = exiger avec force ; « demander » = to ask (for).',
                'Faux ami : « deceived » = trompé ; « déçu » = disappointed.',
            ],
            'situations' => [
                ['title' => 'Marchandise abîmée', 'description' => "Les cartons reçus d'un fournisseur ghanéen sont endommagés : faites une réclamation au téléphone.", 'tags' => ['business', 'phone']],
                ['title' => 'Client mécontent', 'description' => "Vous êtes au service client : un client anglophone se plaint d'une facture erronée.", 'tags' => ['work', 'business']],
                ['title' => "Chambre d'hôtel", 'description' => "Votre chambre réservée à Lagos a été donnée à quelqu'un d'autre : obtenez une solution.", 'tags' => ['travel']],
            ],
        ],

        // 7
        [
            'title' => 'Santé et mode de vie',
            'objectives' => [
                'parler de vos habitudes de vie et de santé',
                'donner et recevoir des conseils',
                "expliquer les causes et les conséquences d'un problème de santé",
            ],
            'vocabulary' => [
                'healthy / unhealthy' => 'sain / malsain',
                'diet' => 'alimentation, régime',
                'to put on weight' => 'prendre du poids',
                'to lose weight' => 'perdre du poids',
                'exercise' => 'activité physique',
                'to work out' => 'faire du sport',
                'stress' => 'stress',
                'to sleep well' => 'bien dormir',
                'sugar / salt' => 'sucre / sel',
                'high blood pressure' => 'hypertension',
                'diabetes' => 'diabète',
                'check-up' => 'bilan de santé',
                'to prevent' => 'prévenir',
                'habit' => 'habitude',
                'to cut down on' => 'réduire (consommation)',
                'to quit smoking' => 'arrêter de fumer',
                'balanced' => 'équilibré',
                'clean water' => 'eau potable',
            ],
            'grammar' => [
                "Conseils : should, ought to, had better (« You'd better see a doctor »).",
                'Used to pour une habitude passée : « I used to drink a lot of soda, but now I drink water ».',
            ],
            'functions' => [
                "Conseiller : « If I were you, I'd cut down on sugar. You should walk more. »",
                "Parler d'un changement : « I used to sleep five hours a night. Now I try to sleep seven. »",
                'Expliquer une cause : « Too much salt can lead to high blood pressure. »',
            ],
            'pronunciation' => [
                "Used to [ˈjuːstə] : le « d » ne s'entend pas et « to » est faible.",
                'Diabetes [ˌdaɪəˈbiːtiːz] : accent sur « be ».',
            ],
            'spoken' => [
                "« I'm trying to eat healthier » = j'essaie de mieux manger.",
                '« Everything in moderation » = tout avec modération.',
            ],
            'pitfalls' => [
                "Faux ami : « a diet » = l'alimentation en général, pas seulement un régime amaigrissant.",
                "« I use to » ✗ au présent → au présent on dit « I usually… » ; used to n'existe qu'au passé.",
            ],
            'situations' => [
                ['title' => 'Sensibilisation au centre de santé', 'description' => 'Vous êtes agent de santé : conseillez un patient anglophone sur son alimentation et sa tension.', 'tags' => ['health', 'work']],
                ['title' => 'Bonnes résolutions', 'description' => 'Avec un ami, comparez vos anciennes et nouvelles habitudes de vie.', 'tags' => ['conversation', 'family']],
                ['title' => 'Santé au travail', 'description' => 'Votre entreprise organise une journée santé : discutez du stress au travail avec un collègue.', 'tags' => ['work', 'health']],
            ],
        ],

        // 8
        [
            'title' => 'Médias, réseaux sociaux et technologie',
            'objectives' => [
                "parler de votre usage du téléphone, d'internet et des réseaux sociaux",
                "rapporter ce que quelqu'un a dit ou écrit",
                'discuter des avantages et des dangers du numérique',
            ],
            'vocabulary' => [
                'social media' => 'réseaux sociaux',
                'to post' => 'publier',
                'to share' => 'partager',
                'to follow' => 'suivre (un compte)',
                'group chat' => 'groupe de discussion',
                'fake news' => 'fausses informations',
                'scam' => 'arnaque',
                'password' => 'mot de passe',
                'to download / upload' => 'télécharger / mettre en ligne',
                'app' => 'application',
                'online / offline' => 'en ligne / hors ligne',
                'to go viral' => 'faire le buzz',
                'screen time' => "temps d'écran",
                'privacy' => 'vie privée',
                'to hack' => 'pirater',
                'reliable source' => 'source fiable',
                'influencer' => 'influenceur',
                'to scroll' => 'faire défiler',
            ],
            'grammar' => [
                'Le discours indirect : « He said (that) he was busy », « She told me she would call ».',
                'Say vs tell : say something / tell someone something.',
            ],
            'functions' => [
                'Rapporter : « My cousin told me that the message was a scam. »',
                "Mettre en garde : « Don't share that video before you check the source. »",
                'Parler de ses habitudes : « I spend about three hours a day on my phone, mostly on WhatsApp. »',
            ],
            'pronunciation' => [
                'Le son [ʃ] de social et share (comme « ch ») ; privacy se dit [ˈprɪvəsi] (UK) ou [ˈpraɪvəsi] (US).',
                'Les sigles : on épelle (« an S-M-S », « a P-D-F »).',
            ],
            'spoken' => [
                "« Did you see what she posted? » = t'as vu ce qu'elle a posté ?",
                "« Don't fall for it » = ne tombe pas dans le piège.",
            ],
            'pitfalls' => [
                '« He said me » ✗ → « He told me » ou « He said to me » ✓.',
                'Faux ami : « a reportage » est rare ; « un reportage » = a report / a documentary.',
            ],
            'situations' => [
                ['title' => 'Arnaque sur WhatsApp', 'description' => "Un proche a reçu un message suspect : expliquez-lui ce qu'on vous a dit et comment vérifier.", 'tags' => ['phone', 'family', 'tech']],
                ['title' => 'Promouvoir sa boutique', 'description' => 'Vous discutez avec un ami nigérian de la meilleure façon de vendre vos produits sur les réseaux sociaux.', 'tags' => ['business', 'tech']],
                ['title' => 'Exposé sur le numérique', 'description' => 'En classe, vous présentez les dangers et avantages des réseaux sociaux pour les jeunes.', 'tags' => ['studies', 'education']],
            ],
        ],

        // 9
        [
            'title' => "Voyage d'affaires au Nigeria",
            'objectives' => [
                'organiser un voyage : vol, hôtel, rendez-vous',
                "parler de rendez-vous fixés et d'horaires",
                'gérer un imprévu pendant le voyage',
            ],
            'vocabulary' => [
                'flight' => 'vol',
                'to book' => 'réserver',
                'boarding pass' => "carte d'embarquement",
                'gate' => "porte d'embarquement",
                'layover / stopover' => 'escale',
                'to be delayed' => 'être retardé',
                'to be cancelled' => 'être annulé',
                'itinerary' => 'itinéraire, programme du voyage',
                'schedule' => 'emploi du temps, planning',
                'to confirm' => 'confirmer',
                'to postpone' => 'reporter',
                'venue' => "lieu (d'un événement)",
                'trade fair' => 'foire commerciale',
                'accommodation' => 'hébergement',
                'per diem' => 'indemnité journalière',
                'visa on arrival' => "visa à l'arrivée",
                'jet lag' => 'décalage horaire',
                'to pick someone up' => "aller chercher quelqu'un",
            ],
            'grammar' => [
                "Présent continu pour un rendez-vous fixé : « I'm meeting the distributor on Tuesday at 10 ».",
                'Présent simple pour les horaires officiels : « The flight leaves at 7:45 ».',
            ],
            'functions' => [
                'Fixer un rendez-vous : « Are you free on Wednesday afternoon? Shall we say 3 pm at your office? »',
                "Reporter : « I'm afraid my flight has been delayed. Could we push the meeting to Thursday? »",
                "Confirmer : « Just to confirm: we're meeting at the Lagos trade fair, stand B12. »",
            ],
            'pronunciation' => [
                'Schedule : [ˈʃedjuːl] (UK) ou [ˈskedʒuːl] (US) — les deux sont corrects.',
                'Les heures avec am / pm : « three pm » [ˌθriː piː ˈem].',
            ],
            'spoken' => [
                "« Let's touch base when you land » = on fait le point quand tu atterris.",
                "« I'm running a bit late, traffic is crazy » = j'ai un peu de retard, la circulation est folle (classique à Lagos !).",
            ],
            'pitfalls' => [
                'Faux ami : « to assist » = aider ; « assister à une réunion » = to attend a meeting.',
                'Faux ami : « a location » = un emplacement ; « une location » = a rental.',
            ],
            'situations' => [
                ['title' => 'Préparer le voyage', 'description' => 'Vous organisez au téléphone avec votre contact nigérian le programme de votre visite à Lagos.', 'tags' => ['business', 'travel', 'phone']],
                ['title' => 'Vol annulé', 'description' => 'Votre vol pour Abuja est annulé : trouvez une solution au comptoir de la compagnie.', 'tags' => ['travel', 'transport']],
                ['title' => 'Foire commerciale', 'description' => 'À une foire commerciale à Lagos, prenez rendez-vous avec un distributeur intéressé.', 'tags' => ['business', 'work']],
            ],
        ],

        // 10
        [
            'title' => 'Et si… ? Rêves et hypothèses',
            'objectives' => [
                'imaginer une situation irréelle et ses conséquences',
                'exprimer un souhait ou un regret simple',
                'donner un conseil avec « à ta place »',
            ],
            'vocabulary' => [
                'if I were you' => 'à ta / votre place',
                'to win the lottery' => 'gagner à la loterie',
                'to wish' => 'souhaiter',
                'to imagine' => 'imaginer',
                'rich' => 'riche',
                'to build' => 'construire',
                'to invest' => 'investir',
                'charity' => 'œuvre caritative',
                'community' => 'communauté',
                'to help out' => 'aider',
                'to travel the world' => 'faire le tour du monde',
                'dream job' => 'emploi de rêve',
                'instead' => 'à la place',
                'realistic' => 'réaliste',
                'to afford' => 'avoir les moyens de',
                'in that case' => 'dans ce cas',
                'would rather' => 'préférer',
            ],
            'grammar' => [
                'Deuxième conditionnel : If + prétérit, would + verbe : « If I had more money, I would open a school ».',
                'If I were (avec toutes les personnes) : « If I were the president… ».',
                'I wish + prétérit : « I wish I spoke English fluently ».',
            ],
            'functions' => [
                "Imaginer : « If I won ten million francs, I'd build a house for my parents. »",
                "Conseiller : « If I were you, I'd talk to your boss first. »",
                'Exprimer un souhait : « I wish I had more time to study. »',
            ],
            'pronunciation' => [
                "I'd [aɪd] = I would (ou I had) : la contraction est presque toujours utilisée à l'oral.",
                'Would [wʊd] : le « l » est muet.',
            ],
            'spoken' => [
                "« Wouldn't it be great if…? » = ce serait génial si… !",
                '« Dream on! » = tu peux toujours rêver !',
            ],
            'pitfalls' => [
                'Pas de would après if : « If I would have money » ✗ → « If I had money » ✓.',
                'Faux ami : « to realise » = se rendre compte ; « réaliser un projet » = to carry out / achieve.',
            ],
            'situations' => [
                ['title' => "Si j'étais ministre", 'description' => "Un ami vous demande ce que vous feriez si vous étiez ministre de l'Éducation ou du Commerce.", 'tags' => ['conversation', 'education']],
                ['title' => 'Conseil à un employé', 'description' => 'Un jeune collègue hésite à démissionner : conseillez-le avec « If I were you… ».', 'tags' => ['work']],
                ['title' => 'Projet communautaire', 'description' => "Votre association imagine ce qu'elle ferait avec un financement : présentez vos idées.", 'tags' => ['business', 'conversation']],
            ],
        ],

        // 11
        [
            'title' => "La ville et l'environnement",
            'objectives' => [
                "décrire les problèmes d'une ville (déchets, inondations, circulation)",
                'expliquer ce qui est fait ou devrait être fait',
                'proposer des solutions concrètes',
            ],
            'vocabulary' => [
                'environment' => 'environnement',
                'pollution' => 'pollution',
                'rubbish / waste' => 'ordures, déchets',
                'plastic bags' => 'sacs plastique',
                'to recycle' => 'recycler',
                'flood' => 'inondation',
                'drain / gutter' => 'caniveau',
                'power cut' => 'coupure de courant',
                'water shortage' => "pénurie d'eau",
                'traffic' => 'circulation',
                'solar panel' => 'panneau solaire',
                'to plant trees' => 'planter des arbres',
                'council / town hall' => 'mairie',
                'authorities' => 'autorités',
                'climate change' => 'changement climatique',
                'to protect' => 'protéger',
                'awareness' => 'sensibilisation',
                'erosion' => 'érosion',
            ],
            'grammar' => [
                'Le passif au présent et au passé : « Rubbish is collected twice a week », « The road was built in 2015 ».',
                'Should be + participe : « The drains should be cleaned before the rainy season ».',
            ],
            'functions' => [
                'Décrire un problème : « Every rainy season, our street is flooded because the drains are blocked. »',
                'Proposer : « Plastic bags should be banned, and people should be paid to collect waste. »',
                'Interpeller : « What is the council doing about the power cuts? »',
            ],
            'pronunciation' => [
                'Environment [ɪnˈvaɪrənmənt] : accent sur « vi ».',
                'Flood [flʌd] : « oo » se dit ici comme le « u » de cup.',
            ],
            'spoken' => [
                "« It's a nightmare » = c'est un cauchemar (circulation, inondations…).",
                '« Something needs to be done » = il faut faire quelque chose.',
            ],
            'pitfalls' => [
                "Ne confondez pas le passif (is built) et l'actif (builds) : le sujet subit l'action.",
                "Faux ami : « a coupure » n'existe pas : « coupure de courant » = power cut / blackout.",
            ],
            'situations' => [
                ['title' => 'Réunion de quartier', 'description' => 'Avec des voisins, vous discutez des inondations dans votre quartier et préparez des propositions pour la mairie.', 'tags' => ['conversation', 'admin']],
                ['title' => "Projet d'ONG", 'description' => 'Vous présentez à une ONG anglophone un projet de recyclage des sacs plastique.', 'tags' => ['work', 'business']],
                ['title' => 'Journaliste étranger', 'description' => 'Un journaliste vous interroge sur les défis de votre ville et ce qui a été fait.', 'tags' => ['conversation', 'travel']],
            ],
        ],

        // 12
        [
            'title' => 'Présenter un projet en public',
            'objectives' => [
                'structurer une courte présentation orale (début, développement, fin)',
                "utiliser des expressions pour guider l'auditoire",
                'répondre simplement à des questions',
            ],
            'vocabulary' => [
                'presentation' => 'présentation, exposé',
                'audience' => 'public, auditoire',
                'slide' => 'diapositive',
                'to introduce' => 'présenter (un sujet, une personne)',
                'first of all' => "tout d'abord",
                'secondly' => 'deuxièmement',
                'moving on to' => 'passons à',
                'for instance' => 'par exemple',
                'to sum up' => 'pour résumer',
                'in conclusion' => 'en conclusion',
                'aim / purpose' => 'but, objectif',
                'budget' => 'budget',
                'results' => 'résultats',
                'to highlight' => 'souligner',
                'chart / graph' => 'graphique',
                'feedback' => 'retour, avis',
                'any questions?' => 'avez-vous des questions ?',
            ],
            'grammar' => [
                "Le présent et le futur pour annoncer le plan : « Today I'm going to talk about… », « I'll start with… ».",
                'Les questions de clarification : « Could you repeat the question? », « Do you mean…? ».',
            ],
            'functions' => [
                "Ouvrir : « Good morning, everyone. Thank you for coming. Today I'd like to present our new project. »",
                "Guider : « Now, let's move on to the budget. As you can see on this slide… »",
                'Conclure : « To sum up, this project will create twenty jobs. Thank you. Any questions? »',
            ],
            'pronunciation' => [
                'Pauses et accent de phrase : accentuez les mots importants, faites des pauses entre les idées.',
                "Mots d'accent tonique différent du français : PREsent (nom) / preSENT (verbe), PROject.",
            ],
            'spoken' => [
                "« Bear with me for a second » = un instant, s'il vous plaît.",
                "« That's a great question » : pour accueillir une question avant d'y répondre.",
            ],
            'pitfalls' => [
                "Faux ami : « to present someone » s'emploie peu ; on dit « to introduce someone ».",
                'Faux ami : « an audience » = un public ; « une audience » au tribunal = a hearing.',
            ],
            'situations' => [
                ['title' => 'Pitch devant des investisseurs', 'description' => 'Vous présentez votre projet de commerce en trois minutes à des investisseurs anglophones.', 'tags' => ['business', 'work']],
                ['title' => "Soutenance de fin d'études", 'description' => 'Vous présentez votre mémoire à un jury et répondez à leurs questions.', 'tags' => ['studies', 'exam']],
                ['title' => 'Réunion de service', 'description' => 'Vous présentez les résultats du mois à votre équipe et à votre responsable.', 'tags' => ['work']],
            ],
        ],

    ],

];
