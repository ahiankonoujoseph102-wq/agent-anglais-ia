<?php

/*
|--------------------------------------------------------------------------
| Programme officiel — niveau B2 (intermédiaire avancé)
|--------------------------------------------------------------------------
|
| Contenu original, aligné sur les descripteurs B2 du CECRL : communiquer avec
| spontanéité et aisance avec un locuteur natif, s'exprimer de façon claire et
| détaillée sur une grande gamme de sujets, défendre un point de vue en
| développant les avantages et les inconvénients de différentes options.
|
*/

return [

    'title' => 'B2 — Intermédiaire avancé : convaincre et travailler en anglais',

    'units' => [

        // 1
        [
            'title' => 'Participer à une réunion',
            'objectives' => [
                'prendre la parole et interrompre poliment en réunion',
                'demander des précisions et reformuler',
                'résumer les décisions et les prochaines étapes',
            ],
            'vocabulary' => [
                'agenda' => 'ordre du jour',
                'item' => "point (de l'ordre du jour)",
                'minutes' => 'compte rendu de réunion',
                'to chair a meeting' => 'présider une réunion',
                'to run over time' => "dépasser l'horaire",
                'to bring up' => 'soulever (un sujet)',
                'to go off topic' => "s'écarter du sujet",
                'to clarify' => 'clarifier',
                'to follow up' => 'faire le suivi',
                'action points' => 'actions à mener',
                'stakeholders' => 'parties prenantes',
                'consensus' => 'consensus',
                'to vote' => 'voter',
                'to postpone' => 'reporter',
                'to take over' => 'prendre le relais',
                'to be on the same page' => "être sur la même longueur d'onde",
                'to wrap up' => 'conclure',
                'attendee' => 'participant',
                'conference call' => 'téléconférence',
            ],
            'grammar' => [
                'Formes de politesse pour interrompre : « Sorry to interrupt, but… », « Could I just add something? ».',
                "Questions indirectes pour clarifier : « Could you explain what you mean by…? », « I'm not sure I follow. ».",
                "Le futur pour les actions : « Kofi will send the report by Friday », « We're going to review the budget. ».",
            ],
            'functions' => [
                'Interrompre : « Sorry to cut in, but I think we need to look at the costs first. »',
                "Reformuler : « So, if I understand correctly, you're suggesting we delay the launch? »",
                "Résumer : « To wrap up: Ama will contact the supplier, and we'll meet again next Monday. »",
            ],
            'pronunciation' => [
                "L'accent de phrase sert à mettre en valeur l'information nouvelle : « I didn't say MONDAY, I said FRIDAY ».",
                'Les réductions naturelles : « What do you think? » → [ˈwʌdʒə θɪŋk].',
            ],
            'spoken' => [
                "« Let's circle back to that later » = on y reviendra plus tard.",
                "« Can we park that for now? » = on met ce point de côté pour l'instant ?",
            ],
            'pitfalls' => [
                'Faux ami : « an agenda » = un ordre du jour ; « un agenda » (carnet) = a planner (en anglais britannique, on dit aussi a diary).',
                'Faux ami : « to resume » = reprendre ; « résumer » = to sum up / summarize.',
            ],
            'situations' => [
                ['title' => 'Réunion de projet', 'description' => 'Réunion à Lomé avec des partenaires étrangers anglophones sur un projet commun : prenez la parole, interrompez et faites le résumé.', 'tags' => ['work', 'business']],
                ['title' => 'Visioconférence internationale', 'description' => 'Visioconférence entre vos bureaux de Lomé et une filiale à l\'étranger : la connexion est mauvaise, gérez les échanges et clarifiez.', 'tags' => ['work', 'phone', 'tech']],
                ['title' => 'Conseil d\'association', 'description' => 'Vous présidez la réunion d\'une association d\'anciens élèves de Kara et devez faire respecter l\'ordre du jour.', 'tags' => ['conversation', 'admin']],
            ],
        ],

        // 2
        [
            'title' => 'Négocier un accord',
            'objectives' => [
                'défendre vos intérêts tout en restant courtois',
                'faire des concessions conditionnelles',
                'conclure un accord et vérifier les termes',
            ],
            'vocabulary' => [
                'deal' => 'accord, affaire',
                'agreement / contract' => 'accord / contrat',
                'to make an offer' => 'faire une offre',
                'counter-offer' => 'contre-proposition',
                'concession' => 'concession',
                'bottom line' => 'limite (au-delà de laquelle on refuse)',
                'to compromise' => 'faire un compromis',
                'exclusive rights' => 'exclusivité',
                'payment terms' => 'conditions de paiement',
                'in installments' => 'en plusieurs versements',
                'penalty' => 'pénalité',
                'to commit to' => "s'engager à",
                'long-term partnership' => 'partenariat à long terme',
                'win-win' => 'gagnant-gagnant',
                'leverage' => 'moyen de pression',
                'to reach an agreement' => 'parvenir à un accord',
                'to break off talks' => 'rompre les négociations',
                'to sign' => 'signer',
            ],
            'grammar' => [
                'Conditionnels pour les concessions : « If you increased the volume, we could lower the price ».',
                "Provided that / as long as / on condition that : « We'll agree, provided that delivery is guaranteed ».",
                'Le conditionnel pour adoucir : « We would need a 30-day payment period », « That might be difficult ».',
            ],
            'functions' => [
                'Proposer : « What if we split the transport costs fifty-fifty? »',
                "Refuser poliment : « I'm afraid that's not something we can accept at this stage. »",
                "Conclure : « So, just to make sure we're clear: 2,000 units, delivered in March, paid in three installments. »",
            ],
            'pronunciation' => [
                'Le ton : phrases descendantes et pauses courtes inspirent confiance ; évitez de monter en fin de phrase affirmative.',
                'Contract : nom [ˈkɑːntrækt], verbe [kənˈtrækt].',
            ],
            'spoken' => [
                "« That's a deal-breaker for us » = c'est rédhibitoire pour nous.",
                '« Meet me halfway » = faites un pas vers moi.',
            ],
            'pitfalls' => [
                'Faux ami : « a convention » = un congrès, une norme ; « une convention » commerciale = an agreement.',
                '« We are agree on » ✗ → « We agree on » / « We have agreed on » ✓.',
            ],
            'situations' => [
                ['title' => 'Contrat de distribution', 'description' => 'Vous négociez avec une entreprise internationale l\'exclusivité de la distribution de ses produits au Togo.', 'tags' => ['business']],
                ['title' => 'Salaire à l\'embauche', 'description' => 'Une entreprise du port autonome de Lomé vous propose un poste : négociez votre salaire et vos avantages en anglais.', 'tags' => ['work']],
                ['title' => 'Location d\'entrepôt', 'description' => 'Vous négociez le loyer et la durée du bail d\'un entrepôt près du port autonome de Lomé avec un investisseur étranger.', 'tags' => ['business', 'admin']],
            ],
        ],

        // 3
        [
            'title' => 'Carrière et développement professionnel',
            'objectives' => [
                'parler de votre carrière avec précision (fonctions, résultats chiffrés)',
                'répondre à des questions comportementales difficiles',
                'parler de vos ambitions et de votre formation continue',
            ],
            'vocabulary' => [
                'career path' => 'parcours professionnel',
                'to be responsible for' => 'être responsable de',
                'to oversee' => 'superviser',
                'to increase sales by' => 'augmenter les ventes de',
                'key performance indicator (KPI)' => 'indicateur de performance',
                'to meet targets' => 'atteindre les objectifs',
                'to delegate' => 'déléguer',
                'skill set' => 'ensemble de compétences',
                'soft skills' => 'compétences relationnelles',
                'to upskill' => 'se perfectionner',
                'mentor' => 'mentor',
                'performance review' => "entretien d'évaluation",
                'promotion' => 'promotion',
                'to resign' => 'démissionner',
                'layoff' => 'licenciement économique',
                'career break' => 'pause de carrière',
                'to network' => 'développer son réseau',
                'track record' => 'antécédents, résultats passés',
            ],
            'grammar' => [
                "Present perfect continuous pour une activité qui dure : « I've been managing a team of six since 2021 ».",
                'Les nominalisations pour un style professionnel : « the implementation of », « an improvement in ».',
            ],
            'functions' => [
                'Quantifier un résultat : « I increased our customer base by 40% in eighteen months. »',
                "Parler d'un échec : « One project didn't go as planned. What I learned was to involve the team earlier. »",
                'Ambitions : « In five years, I see myself leading the regional office. »',
            ],
            'pronunciation' => [
                'Les nombres et pourcentages à voix haute : « forty percent », « one point five million ».',
                "Mots longs : reSPONsible, implemenTAtion, aCHIEVEment — l'accent ne tombe pas sur la dernière syllabe comme en français.",
            ],
            'spoken' => [
                "« I'm eager to take on more responsibility » = je souhaite prendre plus de responsabilités.",
                "« It was a steep learning curve » = j'ai dû apprendre très vite.",
            ],
            'pitfalls' => [
                "Ne dites pas « I am working here since 2020 » : dites « I've been working here since 2020 ».",
                "Faux ami : « a formation » ≠ formation → training ; « to form » ≠ former quelqu'un → to train.",
            ],
            'situations' => [
                ['title' => 'Entretien de promotion', 'description' => 'Vous défendez votre candidature à un poste de responsable régional devant la direction anglophone de votre entreprise à Lomé.', 'tags' => ['work']],
                ['title' => 'Entretien d\'évaluation', 'description' => 'Votre manager anglophone, en visite à Lomé, fait le bilan de votre année : résultats, difficultés, objectifs.', 'tags' => ['work']],
                ['title' => 'Réseautage à l\'étranger', 'description' => 'Lors d\'un salon professionnel international, à l\'étranger, présentez votre parcours à une personne influente.', 'tags' => ['business', 'conversation']],
            ],
        ],

        // 4
        [
            'title' => 'Débattre de questions de société',
            'objectives' => [
                'construire une argumentation avec des exemples',
                'réfuter un argument sans agressivité',
                'concéder un point tout en maintenant votre position',
            ],
            'vocabulary' => [
                'argument' => 'argument',
                'evidence' => 'preuves',
                'to claim' => 'affirmer',
                'to support' => 'soutenir',
                'to challenge' => 'contester',
                'to acknowledge' => 'reconnaître',
                'drawback' => 'inconvénient',
                'benefit' => 'bénéfice, avantage',
                'brain drain' => 'fuite des cerveaux',
                'migration' => 'migration',
                'inequality' => 'inégalité',
                'education system' => 'système éducatif',
                'funding' => 'financement',
                'to tackle' => "s'attaquer à",
                'long-term' => 'à long terme',
                'whereas' => 'alors que',
                'nevertheless' => 'néanmoins',
                'it could be argued that' => 'on pourrait soutenir que',
            ],
            'grammar' => [
                'Les connecteurs de concession : although, even though, despite + nom/-ing, while.',
                "Les structures d'emphase : « What worries me is… », « The real problem is that… ».",
            ],
            'functions' => [
                'Concéder et contre-argumenter : « I accept that emigration brings money home. Even so, we lose our best doctors. »',
                'Demander des preuves : « Do you have any evidence for that? »',
                'Conclure son argument : « All things considered, I believe investment in training is the priority. »',
            ],
            'pronunciation' => [
                'Argument [ˈɑːrɡjəmənt] : pas de « u » français ; evidence [ˈevɪdəns].',
                'Rythme : les mots grammaticaux (to, of, the) sont faibles, les mots porteurs de sens accentués.',
            ],
            'spoken' => [
                "« That's a fair point, but… » = c'est juste, mais…",
                "« Let's not lose sight of… » = ne perdons pas de vue…",
            ],
            'pitfalls' => [
                "Faux ami : « evidence » = preuves ; « évidence » = obviousness (« c'est une évidence » = it's obvious).",
                'Evidence est indénombrable : « a piece of evidence », jamais « an evidence ».',
            ],
            'situations' => [
                ['title' => 'Débat sur l\'emploi des jeunes', 'description' => 'Dans un débat à l\'université de Lomé, défendez ou critiquez l\'idée que les jeunes qui sortent de l\'université devraient créer leur entreprise plutôt que chercher un emploi.', 'tags' => ['studies', 'exam', 'conversation']],
                ['title' => 'Table ronde sur l\'éducation', 'description' => 'Vous participez à une table ronde sur l\'enseignement de l\'anglais dans les écoles togolaises.', 'tags' => ['education', 'work']],
                ['title' => 'Discussion entre amis', 'description' => 'Un débat animé entre amis sur l\'impact des réseaux sociaux sur la jeunesse togolaise.', 'tags' => ['conversation', 'family']],
            ],
        ],

        // 5
        [
            'title' => 'Marketing, clients et concurrence',
            'objectives' => [
                'décrire votre marché, vos clients et vos concurrents',
                'présenter un produit en mettant en avant ses avantages',
                'analyser des chiffres de vente simplement',
            ],
            'vocabulary' => [
                'target market' => 'marché cible',
                'competitor' => 'concurrent',
                'market share' => 'part de marché',
                'customer loyalty' => 'fidélité des clients',
                'brand awareness' => 'notoriété de la marque',
                'unique selling point' => 'argument de vente unique',
                'pricing' => 'fixation des prix',
                'promotion' => 'promotion',
                'word of mouth' => 'bouche-à-oreille',
                'to launch' => 'lancer',
                'to reach' => 'atteindre (un public)',
                'trend' => 'tendance',
                'to rise / to fall' => 'augmenter / baisser',
                'sharply / slightly' => 'fortement / légèrement',
                'to peak' => 'atteindre un pic',
                'survey' => 'enquête',
                'feedback' => 'retours',
                'affordable' => 'abordable',
            ],
            'grammar' => [
                'Décrire une évolution : verbe + adverbe (« Sales rose sharply ») ou nom + adjectif (« a sharp rise in sales »).',
                'Les prépositions des chiffres : rise to, rise by, from… to, at.',
            ],
            'functions' => [
                "Présenter un produit : « What makes our juice different is that it's 100% local and affordable. »",
                'Analyser : « Sales fell slightly in June, then rose by 20% after the promotion. »',
                'Parler de la concurrence : « Our main competitor is cheaper, but customers prefer our service. »',
            ],
            'pronunciation' => [
                'Les verbes rise / rose / risen : [aɪ] / [oʊ] / [ɪ].',
                'Survey : nom [ˈsɝveɪ], verbe [sɚˈveɪ].',
            ],
            'spoken' => [
                "« It's flying off the shelves » = ça se vend comme des petits pains.",
                '« We need to stand out » = nous devons nous démarquer.',
            ],
            'pitfalls' => [
                'Rise (sans complément : les prix augmentent) ≠ raise (avec complément : on augmente les prix).',
                "Faux ami : « a concurrent » n'existe pas en ce sens → competitor ; « concurrence » = competition.",
            ],
            'situations' => [
                ['title' => 'Présenter sa marque', 'description' => 'Vous présentez votre marque de jus de fruits togolais à l\'acheteur d\'une chaîne internationale de supermarchés.', 'tags' => ['business', 'market']],
                ['title' => 'Réunion marketing', 'description' => 'Vous analysez les ventes du trimestre avec votre équipe à Lomé et proposez une campagne sur WhatsApp.', 'tags' => ['work', 'business', 'tech']],
                ['title' => 'Étude de cas', 'description' => 'En école de commerce à Lomé, vous analysez à l\'oral la stratégie d\'une entreprise togolaise.', 'tags' => ['studies', 'exam']],
            ],
        ],

        // 6
        [
            'title' => 'Regrets, reproches et hypothèses sur le passé',
            'objectives' => [
                'exprimer un regret sur une décision passée',
                'faire un reproche avec tact',
                "faire des hypothèses sur ce qui s'est passé",
            ],
            'vocabulary' => [
                'regret' => 'regret',
                'to blame' => 'rejeter la faute sur',
                'mistake' => 'erreur',
                'to take responsibility' => 'assumer la responsabilité',
                'to miss an opportunity' => 'rater une occasion',
                'in hindsight' => 'avec le recul',
                'to warn' => 'prévenir, avertir',
                'to make up for' => 'rattraper',
                'consequence' => 'conséquence',
                'to turn down' => 'refuser (une offre)',
                'to overlook' => 'négliger',
                'careless' => 'négligent',
                'if only' => 'si seulement',
                "it's no use crying over spilled milk" => 'ce qui est fait est fait',
                'to learn the hard way' => 'apprendre à ses dépens',
                'to second-guess' => 'remettre en question après coup',
                'excuse' => 'excuse (prétexte)',
            ],
            'grammar' => [
                'Troisième conditionnel : « If I had invested earlier, I would have made more profit ».',
                "Modaux au passé : should have / shouldn't have (reproche), might have / must have / can't have (déduction).",
                'I wish / If only + plus-que-parfait : « I wish I had listened to my father ».',
            ],
            'functions' => [
                'Regretter : « I should have checked the contract before signing it. »',
                'Reprocher avec tact : « You might have told me earlier — we could have found a solution. »',
                "Déduire : « The parcel must have been lost at customs. He can't have received it. »",
            ],
            'pronunciation' => [
                "Should have → [ˈʃʊdəv] ; would have → [ˈwʊdəv] (jamais « should of » à l'écrit !).",
                'Must have been → [ˈmʌstəv bɪn] : le « have » est très réduit.',
            ],
            'spoken' => [
                "« I could kick myself! » = je m'en veux terriblement !",
                "« Water under the bridge » = c'est de l'histoire ancienne.",
            ],
            'pitfalls' => [
                '« If I would have known » ✗ → « If I had known » ✓.',
                "Faux ami : « to excuse oneself » = demander à partir ; « s'excuser » = to apologize.",
            ],
            'situations' => [
                ['title' => 'Bilan d\'un projet raté', 'description' => 'Le lancement de votre produit au Grand Marché a échoué : analysez avec votre associé ce que vous auriez dû faire.', 'tags' => ['business', 'work']],
                ['title' => 'Colis perdu', 'description' => 'Un colis envoyé à l\'étranger n\'est jamais arrivé : faites des hypothèses avec le transporteur.', 'tags' => ['transport', 'business', 'phone']],
                ['title' => 'Conseils à un cadet', 'description' => 'Un jeune de votre famille, étudiant à Kara, vous demande des conseils : racontez ce que vous auriez fait différemment à son âge.', 'tags' => ['family', 'conversation', 'studies']],
            ],
        ],

        // 7
        [
            'title' => 'Communication professionnelle écrite et dictée',
            'objectives' => [
                'dicter ou rédiger un e-mail professionnel structuré',
                'adapter le registre (formel, neutre, amical)',
                "relire et améliorer un message à l'oral avec le professeur",
            ],
            'vocabulary' => [
                'Dear Sir or Madam' => 'Madame, Monsieur',
                'I am writing to' => 'je vous écris pour',
                'with reference to' => 'suite à, concernant',
                'please find attached' => 'veuillez trouver ci-joint',
                'I would be grateful if' => 'je vous serais reconnaissant de',
                'at your earliest convenience' => 'dans les meilleurs délais',
                'to look forward to' => 'attendre avec impatience',
                'kind regards' => 'cordialement',
                'sincerely / sincerely yours' => 'veuillez agréer…',
                'subject line' => "objet (d'un e-mail)",
                'to forward' => 'transférer',
                'to cc' => 'mettre en copie',
                'reminder' => 'relance, rappel',
                'to acknowledge receipt' => 'accuser réception',
                'tone' => 'ton',
                'formal / informal' => 'formel / informel',
                'draft' => 'brouillon',
                'to proofread' => 'relire',
            ],
            'grammar' => [
                'Look forward to + -ing : « I look forward to hearing from you » (to est une préposition ici).',
                'Registre formel : pas de contractions, phrases complètes, verbes plus soutenus (to request, to inform).',
            ],
            'functions' => [
                'Dicter un e-mail : « Subject: payment reminder. Dear Mr. Mensah, I am writing with reference to invoice 245… »',
                'Adoucir une relance : « I was wondering whether you had had a chance to look at our proposal. »',
                "Passer du formel à l'amical : « Thanks a lot for your help — speak soon! »",
            ],
            'pronunciation' => [
                'Dicter la ponctuation : comma, period (en anglais britannique, on dit aussi « full stop »), colon, dash, new paragraph.',
                'Lire une adresse e-mail : « k dot mensah at gmail dot com ».',
            ],
            'spoken' => [
                '« Just a quick heads-up » = juste pour vous prévenir (e-mail interne, informel).',
                '« Shoot me an email » = envoie-moi un e-mail (familier).',
            ],
            'pitfalls' => [
                '« I look forward to hear from you » ✗ → « to hearing » ✓.',
                'Faux ami : « eventually » dans un e-mail ne signifie pas « éventuellement » : utilisez « if necessary » ou « possibly ».',
            ],
            'situations' => [
                ['title' => 'Relance de paiement', 'description' => 'Vous dictez au professeur un e-mail de relance poli mais ferme pour un client étranger en retard de paiement.', 'tags' => ['business', 'work']],
                ['title' => 'Candidature par e-mail', 'description' => 'Vous préparez l\'e-mail qui accompagne votre CV pour un poste dans une ONG anglophone installée à Lomé.', 'tags' => ['work']],
                ['title' => 'Message à un professeur', 'description' => 'Vous rédigez un message formel à votre directeur de mémoire de l\'université de Lomé pour demander un délai.', 'tags' => ['studies', 'education']],
            ],
        ],

        // 8
        [
            'title' => 'Santé publique et services',
            'objectives' => [
                'expliquer une procédure ou un protocole étape par étape',
                "parler d'un système de santé ou d'un service public",
                'rassurer et informer un usager',
            ],
            'vocabulary' => [
                'procedure' => 'procédure',
                'step' => 'étape',
                'outbreak' => 'épidémie (début)',
                'vaccination campaign' => 'campagne de vaccination',
                'to be screened' => 'être dépisté',
                'health center' => 'centre de santé',
                'waiting list' => "liste d'attente",
                'patient record' => 'dossier du patient',
                'to be admitted' => 'être hospitalisé',
                'to be discharged' => "sortir de l'hôpital",
                'follow-up' => 'suivi',
                'maternal health' => 'santé maternelle',
                'to raise awareness' => 'sensibiliser',
                'community health worker' => 'agent de santé communautaire',
                'free of charge' => 'gratuit',
                'coverage' => 'couverture',
                'consent' => 'consentement',
                'confidential' => 'confidentiel',
            ],
            'grammar' => [
                'Le passif à tous les temps : « Patients are registered », « The results will be sent », « You have been vaccinated ».',
                'Have something done : « You need to have your blood pressure checked every month ».',
            ],
            'functions' => [
                'Expliquer une procédure : « First, you are registered at reception. Then your temperature is taken… »',
                "Rassurer : « There's nothing to worry about. The test is quick and completely confidential. »",
                'Informer : « The vaccine is free of charge and is given in two doses. »',
            ],
            'pronunciation' => [
                'Les participes passés irréguliers fréquents : taken, given, done, seen — finales nettes.',
                'Outbreak [ˈaʊtbreɪk] : accent sur out.',
            ],
            'spoken' => [
                "« You're in good hands » = vous êtes entre de bonnes mains.",
                '« Let me walk you through it » = je vais vous expliquer étape par étape.',
            ],
            'pitfalls' => [
                'Faux ami : « to be hospitalized » existe mais on dit plus souvent « to be admitted (to the hospital) ».',
                "Faux ami : « a consultation » est une réunion d'avis ; une « consultation médicale » = an appointment / a check-up.",
            ],
            'situations' => [
                ['title' => 'Campagne de vaccination', 'description' => 'Vous expliquez à une famille anglophone installée à Lomé le déroulement d\'une campagne de vaccination.', 'tags' => ['health', 'family']],
                ['title' => 'Accueil hospitalier', 'description' => 'Vous êtes agent d\'accueil au CHU de Kara : expliquez les étapes d\'une hospitalisation à un patient anglophone venu de l\'étranger.', 'tags' => ['health', 'work', 'admin']],
                ['title' => 'Rapport de mission', 'description' => 'Vous présentez à un bailleur de fonds anglophone les résultats d\'une campagne de sensibilisation dans la région des Savanes.', 'tags' => ['work', 'health']],
            ],
        ],

        // 9
        [
            'title' => 'Technologie et innovation au Togo',
            'objectives' => [
                'décrire une innovation et son impact',
                "expliquer le fonctionnement d'un service numérique",
                "spéculer sur l'avenir de la technologie",
            ],
            'vocabulary' => [
                'start-up' => 'jeune entreprise',
                'fintech' => 'technologie financière',
                'mobile banking' => 'banque mobile',
                'cashless' => 'sans espèces',
                'digital payment' => 'paiement numérique',
                'connectivity' => 'connectivité',
                'rural areas' => 'zones rurales',
                'to roll out' => 'déployer',
                'user-friendly' => 'facile à utiliser',
                'breakthrough' => 'percée',
                'to disrupt' => 'bouleverser (un secteur)',
                'artificial intelligence' => 'intelligence artificielle',
                'data' => 'données',
                'cybersecurity' => 'cybersécurité',
                'e-commerce' => 'commerce en ligne',
                'to scale up' => "changer d'échelle",
                'investors' => 'investisseurs',
                'solar energy' => 'énergie solaire',
            ],
            'grammar' => [
                'Futur continu et futur antérieur : « By 2030, most people will be paying by phone », « We will have connected 100 villages ».',
                'Les expressions de probabilité : is likely to, is bound to, is unlikely to.',
            ],
            'functions' => [
                'Expliquer un service : « You simply scan the QR code, enter your PIN, and the money is transferred instantly. »',
                "Évaluer l'impact : « Mobile money has made it much easier for traders to get paid. »",
                'Spéculer : « In ten years, AI is likely to change how farmers sell their crops. »',
            ],
            'pronunciation' => [
                'Data : [ˈdeɪɾə] ou [ˈdæɾə] en anglais américain (le « t » devient un « r » français très bref) ; jamais « da-ta » à la française.',
                "Mots composés : l'accent est sur le premier élément — SMARTphone, START-up.",
            ],
            'spoken' => [
                "« It's a game-changer » = ça change la donne.",
                "« The sky's the limit » = tout est possible.",
            ],
            'pitfalls' => [
                "Data est souvent indénombrable dans l'usage courant : « the data is », « a lot of data ».",
                'Faux ami : « a computer scientist » = informaticien ; « informatique » = IT / computing.',
            ],
            'situations' => [
                ['title' => 'Présenter une application', 'description' => 'Vous présentez une application de paiement mobile à des commerçantes du Grand Marché et répondez à leurs craintes.', 'tags' => ['tech', 'business', 'market']],
                ['title' => 'Conférence tech internationale', 'description' => 'Dans une conférence technologique internationale, à l\'étranger, vous échangez avec un développeur anglophone sur l\'innovation au Togo.', 'tags' => ['tech', 'work', 'travel']],
                ['title' => 'Débat en classe', 'description' => 'À l\'université de Lomé, vous débattez de l\'impact de l\'intelligence artificielle sur l\'emploi au Togo.', 'tags' => ['studies', 'tech']],
            ],
        ],

        // 10
        [
            'title' => 'Gérer les tensions et les conflits',
            'objectives' => [
                'exprimer un désaccord ou un mécontentement avec diplomatie',
                'écouter activement et reformuler les émotions',
                'proposer une solution acceptable pour tous',
            ],
            'vocabulary' => [
                'conflict' => 'conflit',
                'tension' => 'tension',
                'misunderstanding' => 'malentendu',
                'to calm down' => 'se calmer',
                'to get on with' => "bien s'entendre avec",
                'to fall out with' => 'se brouiller avec',
                'to criticize' => 'critiquer',
                'constructive' => 'constructif',
                'to defuse' => 'désamorcer',
                'frustrated' => 'frustré',
                'to feel heard' => 'se sentir écouté',
                'to mediate' => 'servir de médiateur',
                'common ground' => "terrain d'entente",
                'to back down' => 'céder',
                'workload' => 'charge de travail',
                'deadline pressure' => 'pression des délais',
                'to sort things out' => 'régler les choses',
            ],
            'grammar' => [
                "Les phrases en « I » pour éviter d'accuser : « I feel… when… because… ».",
                "Les structures d'adoucissement : « It seems that… », « There appears to be… », « I'm a bit concerned that… ».",
            ],
            'functions' => [
                "Exprimer un problème : « I'm a bit concerned that the deadlines keep changing at the last minute. »",
                "Reformuler l'émotion de l'autre : « So you feel that your work isn't being recognized, is that right? »",
                'Proposer : « How about we agree on a weekly meeting to plan the work together? »',
            ],
            'pronunciation' => [
                "Intonation douce et descendante pour désamorcer ; évitez l'insistance sur « you ».",
                "Les question tags pour adoucir : « That's fair, isn't it? » (montée = vraie question, descente = demande d'accord).",
            ],
            'spoken' => [
                "« Let's not make a big deal out of it » = n'en faisons pas une montagne.",
                '« I hear you » = je vous comprends.',
            ],
            'pitfalls' => [
                'Faux ami : « susceptible » = sujet à ; « susceptible » (vexé facilement) = touchy / sensitive.',
                "Faux ami : « to support » = soutenir ; « supporter quelqu'un » (le tolérer) = to put up with / to stand.",
            ],
            'situations' => [
                ['title' => 'Conflit dans l\'équipe', 'description' => 'Deux collègues de votre agence de Lomé ne s\'entendent pas sur la répartition du travail : vous servez de médiateur.', 'tags' => ['work']],
                ['title' => 'Client agressif', 'description' => 'Un client étranger mécontent élève la voix au téléphone : calmez la situation et trouvez une solution.', 'tags' => ['business', 'phone']],
                ['title' => 'Malentendu familial', 'description' => 'Un malentendu avec un parent installé à l\'étranger : vous en parlez calmement pour le régler.', 'tags' => ['family', 'conversation']],
            ],
        ],

        // 11
        [
            'title' => 'Contrats, droits et démarches',
            'objectives' => [
                "comprendre et expliquer les clauses principales d'un contrat simple",
                'parler de vos droits et obligations',
                "mener une démarche administrative complexe à l'oral",
            ],
            'vocabulary' => [
                'clause' => 'clause',
                'terms and conditions' => 'conditions générales',
                'to be entitled to' => 'avoir droit à',
                'obligation' => 'obligation',
                'liability' => 'responsabilité (juridique)',
                'breach of contract' => 'rupture de contrat',
                'notice' => 'préavis',
                'to terminate' => 'résilier',
                'lease' => 'bail',
                'tenant / landlord' => 'locataire / propriétaire',
                'deposit' => 'caution',
                'registration' => 'immatriculation, enregistrement',
                'permit / license' => 'permis / licence',
                'to comply with' => 'se conformer à',
                'lawyer' => 'avocat',
                'fine' => 'amende',
                'small print' => 'petits caractères',
                'dispute' => 'litige',
            ],
            'grammar' => [
                "Les modaux d'obligation et de permission formels : shall, must, is required to, is entitled to, may.",
                'Les relatives : who, which, that, whose (« the tenant whose lease ends… »).',
            ],
            'functions' => [
                "Expliquer une clause : « This clause says that either party may terminate the contract with one month's notice. »",
                "Défendre ses droits : « According to the lease, I'm entitled to get my deposit back. »",
                'Démarche : « What documents are required to register a subsidiary abroad? »',
            ],
            'pronunciation' => [
                'Accent des mots juridiques : liaBILity, obliGAtion, regiSTRAtion — juste avant les finales -ty et -tion.',
                'Lawyer [ˈlɔɪɚ] à l\'américaine : comme « loy-eur », et le « r » final se prononce.',
            ],
            'spoken' => [
                '« Read the small print » = lisez les petits caractères.',
                "« It's all above board » = tout est en règle.",
            ],
            'pitfalls' => [
                "Faux ami : « a lawyer » = avocat ; « an avocado » = l'avocat (fruit) !",
                "Faux ami : « to resign a contract » n'existe pas : « résilier » = to terminate / cancel.",
            ],
            'situations' => [
                ['title' => 'Ouvrir une filiale à l\'étranger', 'description' => 'En visioconférence, vous vous renseignez auprès d\'un conseiller anglophone sur les démarches pour ouvrir une filiale de votre entreprise à l\'étranger.', 'tags' => ['business', 'admin', 'travel']],
                ['title' => 'Litige de location', 'description' => 'Votre propriétaire à Lomé refuse de rendre la caution : expliquez vos droits à un avocat anglophone en vous appuyant sur le bail.', 'tags' => ['admin', 'conversation']],
                ['title' => 'Contrat de travail', 'description' => 'Avant de signer avec une entreprise anglophone de Lomé, vous posez des questions précises sur les clauses de votre contrat.', 'tags' => ['work', 'admin']],
            ],
        ],

        // 12
        [
            'title' => 'Convaincre en présentation',
            'objectives' => [
                'construire une présentation persuasive (problème, solution, bénéfices)',
                'commenter des données et répondre aux objections',
                "captiver l'auditoire par la voix et le rythme",
            ],
            'vocabulary' => [
                'to persuade' => 'persuader',
                'compelling' => 'convaincant',
                'key message' => 'message clé',
                'to hook the audience' => 'accrocher le public',
                'pain point' => 'problème du client',
                'value proposition' => 'proposition de valeur',
                'return on investment' => 'retour sur investissement',
                'figures' => 'chiffres',
                'to illustrate' => 'illustrer',
                'objection' => 'objection',
                'to address a concern' => 'répondre à une inquiétude',
                'call to action' => "appel à l'action",
                'rhetorical question' => 'question rhétorique',
                'pace' => 'rythme',
                'to emphasize' => 'insister sur',
                'takeaway' => 'message à retenir',
                'Q&A session' => 'séance de questions-réponses',
            ],
            'grammar' => [
                "Les structures emphatiques (cleft sentences) : « What we need is… », « It's the quality that makes the difference ».",
                'Les inversions et les séries de trois pour le rythme : « faster, cheaper and safer ».',
            ],
            'functions' => [
                "Accrocher : « Imagine losing half your harvest every year. That's the reality for thousands of farmers. »",
                'Répondre à une objection : « I understand the concern about cost. Let me show you the return on investment. »',
                "Appel à l'action : « So, let's start with a pilot in three markets next month. »",
            ],
            'pronunciation' => [
                'Utiliser les pauses avant les chiffres importants : « We reduced losses by… [pause] forty percent. »',
                "L'accent contrastif : « We don't need MORE money, we need SMARTER spending. »",
            ],
            'spoken' => [
                "« Here's the thing… » = voilà le problème / le point clé…",
                '« Let that sink in » = laissez cette idée faire son chemin.',
            ],
            'pitfalls' => [
                'Ne lisez pas vos diapositives : parlez au public, les diapositives illustrent.',
                'Faux ami : « to realize » ≠ réaliser une présentation → to give / deliver a presentation.',
            ],
            'situations' => [
                ['title' => 'Convaincre un bailleur', 'description' => 'Vous présentez un projet agricole de la région des Plateaux à un bailleur de fonds international et répondez à ses objections.', 'tags' => ['business', 'work']],
                ['title' => 'Concours de pitch', 'description' => 'Concours de jeunes entrepreneurs à Lomé : vous avez cinq minutes pour convaincre un jury anglophone.', 'tags' => ['business', 'exam']],
                ['title' => 'Proposition à la direction', 'description' => 'Vous proposez à la direction de votre entreprise à Lomé d\'adopter un nouvel outil de gestion et vous défendez le budget.', 'tags' => ['work', 'tech']],
            ],
        ],

    ],

];
