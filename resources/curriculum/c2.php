<?php

/*
|--------------------------------------------------------------------------
| Programme officiel — niveau C2 (maîtrise)
|--------------------------------------------------------------------------
|
| Contenu original, aligné sur les descripteurs C2 du CECRL : comprendre sans
| effort pratiquement tout ce qu'on entend, restituer faits et arguments de
| diverses sources de façon cohérente, s'exprimer spontanément, très
| couramment et de façon précise, en rendant distinctes de fines nuances de
| sens en rapport avec des sujets complexes.
|
*/

return [

    'title' => 'C2 — Maîtrise : précision, style et influence',

    'units' => [

        // 1
        [
            'title' => 'La précision lexicale et les connotations',
            'objectives' => [
                'choisir le mot exact parmi des quasi-synonymes',
                'percevoir et utiliser les connotations positives, neutres ou négatives',
                'reformuler un propos avec plus de précision',
            ],
            'vocabulary' => [
                'connotation' => 'connotation',
                'nuance' => 'nuance',
                'frugal / stingy' => 'économe / pingre',
                'assertive / pushy' => 'affirmé / insistant (péjoratif)',
                'slim / skinny' => 'mince / maigre (péjoratif)',
                'determined / stubborn' => 'déterminé / têtu',
                'to imply / to infer' => 'sous-entendre / déduire',
                'to glance / to stare' => "jeter un coup d'œil / fixer",
                'to mutter / to murmur' => 'marmonner / murmurer',
                'to compel / to coerce' => 'obliger / contraindre par la force',
                'ambiguous' => 'ambigu',
                'euphemism' => 'euphémisme',
                'understatement' => 'litote, euphémisme',
                'register' => 'registre',
                'collocation' => 'association de mots usuelle',
                'to convey' => 'transmettre (une idée)',
                'subtle' => 'subtil',
                'loaded word' => 'mot chargé de sens',
            ],
            'grammar' => [
                'Les collocations figées : make a decision, take a risk, heavy traffic, strong coffee, deeply concerned.',
                'La modalité fine : may well, might conceivably, is bound to, can hardly.',
            ],
            'functions' => [
                "Préciser : « He's not so much stubborn as principled — he simply won't compromise on safety. »",
                "Choisir la connotation : « Let's say the budget is “tight” rather than “inadequate”. »",
                "Reformuler : « What I'm getting at, more precisely, is… »",
            ],
            'pronunciation' => [
                'Le ton porte la connotation : un même mot (« Interesting… ») peut être sincère ou sceptique.',
                'Paires minimales avancées : bear / beer, full / fool, ship / sheep, work / walk.',
            ],
            'spoken' => [
                "« That's putting it mildly » = c'est peu dire.",
                '« To put it bluntly… » = pour parler franchement…',
            ],
            'pitfalls' => [
                'Faux amis subtils : « sensible » (raisonnable), « deception » (tromperie), « achieve » (atteindre, pas achever).',
                "Imply ≠ infer : le locuteur sous-entend (implies), l'auditeur déduit (infers).",
            ],
            'situations' => [
                ['title' => 'Rédiger une recommandation', 'description' => 'Vous choisissez avec soin, à l\'oral avec le professeur, les mots d\'une lettre de recommandation pour un collaborateur de Lomé.', 'tags' => ['work']],
                ['title' => 'Négociation tendue', 'description' => 'Dans une négociation internationale en visioconférence, vous reformulez vos propos pour éviter un mot qui a vexé votre interlocuteur.', 'tags' => ['business']],
                ['title' => 'Analyse de discours', 'description' => 'Vous analysez avec le professeur les connotations d\'un discours prononcé en anglais à Lomé lors d\'une conférence internationale.', 'tags' => ['studies', 'exam']],
            ],
        ],

        // 2
        [
            'title' => 'Rhétorique et persuasion',
            'objectives' => [
                'identifier et utiliser les grands procédés de persuasion',
                'adapter votre argumentation à votre auditoire',
                'déjouer les sophismes de vos contradicteurs',
            ],
            'vocabulary' => [
                'ethos / pathos / logos' => 'crédibilité / émotion / logique',
                'fallacy' => 'sophisme',
                'straw man' => "épouvantail (déformer l'argument adverse)",
                'slippery slope' => 'pente glissante',
                'false dilemma' => 'faux dilemme',
                'ad hominem' => 'attaque personnelle',
                'to appeal to' => 'faire appel à',
                'to sway' => 'influencer, faire basculer',
                'compelling' => 'convaincant',
                'to concede' => 'concéder',
                'to rebut' => 'réfuter',
                'rebuttal' => 'réfutation',
                'premise' => 'prémisse',
                'conclusion' => 'conclusion',
                'to undermine credibility' => 'saper la crédibilité',
                'rhetorical question' => 'question rhétorique',
                'tricolon' => 'énumération en trois temps',
                'antithesis' => 'antithèse',
            ],
            'grammar' => [
                "Les phrases clivées et pseudo-clivées pour l'emphase : « It is precisely this point that… », « What troubles me is… ».",
                "L'inversion stylistique : « Rarely has a policy been so misunderstood », « Had we known, we would have acted ».",
            ],
            'functions' => [
                "Réfuter un sophisme : « That's a false dilemma — we don't have to choose between growth and the environment. »",
                "Faire appel à l'émotion avec mesure : « Behind every statistic is a family waiting for clean water. »",
                'Antithèse : « We do not inherit the land from our ancestors; we borrow it from our children. »',
            ],
            'pronunciation' => [
                'Le rythme ternaire et les pauses avant la chute.',
                "L'accent d'insistance sur les mots de contraste : « not THIS policy, but THAT one ».",
            ],
            'spoken' => [
                "« With respect, that's not what I said » = sauf votre respect, ce n'est pas ce que j'ai dit.",
                "« Let's not conflate two different issues » = ne mélangeons pas deux questions différentes.",
            ],
            'pitfalls' => [
                "L'emphase française (« c'est … qui … ») sur-employée devient lourde en anglais : réservez-la aux moments clés.",
                'Faux ami : « to argue » = se disputer OU argumenter ; « argument » peut aussi signifier dispute.',
            ],
            'situations' => [
                ['title' => 'Débat télévisé', 'description' => 'Débat sur une télévision togolaise, en anglais, sur l\'avenir énergétique du Togo face à un contradicteur habile.', 'tags' => ['conversation', 'exam']],
                ['title' => 'Plaidoyer d\'ONG', 'description' => 'Vous plaidez à Lomé devant des bailleurs internationaux pour le financement d\'un programme d\'éducation dans la région des Savanes.', 'tags' => ['work', 'education']],
                ['title' => 'Assemblée d\'actionnaires', 'description' => 'Vous convainquez des actionnaires sceptiques d\'approuver l\'ouverture d\'une succursale à l\'étranger.', 'tags' => ['business']],
            ],
        ],

        // 3
        [
            'title' => 'Négociations à fort enjeu et médiation',
            'objectives' => [
                'mener une négociation multipartite complexe',
                'jouer le rôle de médiateur entre des positions opposées',
                'formuler des propositions créatives pour élargir le gâteau',
            ],
            'vocabulary' => [
                'multilateral' => 'multilatéral',
                'mediator' => 'médiateur',
                'impartial' => 'impartial',
                'to bridge the gap' => 'rapprocher les positions',
                'common ground' => "terrain d'entente",
                'to reframe' => 'recadrer',
                'zone of possible agreement' => "zone d'accord possible",
                'to make headway' => 'progresser',
                'to backtrack' => 'faire marche arrière',
                'goodwill gesture' => 'geste de bonne volonté',
                'sticking point' => "point d'achoppement",
                'breakthrough' => 'percée',
                'memorandum of understanding' => "protocole d'accord",
                'binding' => 'contraignant (juridiquement)',
                'to ratify' => 'ratifier',
                'concessions on both sides' => 'concessions mutuelles',
                'face-saving formula' => 'formule pour sauver la face',
            ],
            'grammar' => [
                'Les structures hypothétiques sophistiquées : « Were we to…, would you be prepared to…? », « Supposing that… ».',
                'Le registre diplomatique : « We take note of…», « We are not in a position to… at this juncture ».',
            ],
            'functions' => [
                'Recadrer : « Rather than seeing this as a question of who pays, could we frame it as an investment we share? »',
                'Médier : « If I understand both parties correctly, the real concern is not price but reliability. »',
                'Proposer : « Were we to guarantee volumes for three years, would you be prepared to revisit the margin? »',
            ],
            'pronunciation' => [
                'Le registre diplomatique exige une prosodie mesurée, sans emphase agressive.',
                'Memorandum [ˌmeməˈrændəm] : accent sur « ran ».',
            ],
            'spoken' => [
                "« We're not that far apart » = nous ne sommes pas si éloignés.",
                "« Let's not let the perfect be the enemy of the good » = le mieux est l'ennemi du bien.",
            ],
            'pitfalls' => [
                "Faux ami : « to engage » = s'impliquer, mobiliser ; « s'engager à » = to commit to.",
                'Faux ami : « a compromise » est positif (un compromis) ; « to compromise someone » = compromettre.',
            ],
            'situations' => [
                ['title' => 'Médiation entre partenaires', 'description' => 'Vous servez de médiateur entre un fournisseur étranger et un distributeur de Lomé en conflit.', 'tags' => ['business', 'work']],
                ['title' => 'Négociation internationale', 'description' => 'Simulation de négociation entre une délégation togolaise et une délégation étrangère anglophone sur la fluidité du passage des marchandises au port autonome de Lomé.', 'tags' => ['admin', 'exam']],
                ['title' => 'Fusion d\'entreprises', 'description' => 'Vous négociez les conditions d\'une fusion entre une entreprise de transport de Lomé et une entreprise internationale.', 'tags' => ['business', 'transport']],
            ],
        ],

        // 4
        [
            'title' => "L'improvisation et la répartie",
            'objectives' => [
                'parler de façon structurée sans préparation',
                'gagner du temps avec élégance',
                'répondre avec esprit et à-propos',
            ],
            'vocabulary' => [
                'impromptu' => 'improvisé',
                'off the cuff' => 'au pied levé',
                "to think on one's feet" => 'réfléchir vite',
                'to buy time' => 'gagner du temps',
                'filler' => 'mot de remplissage',
                'to ramble' => 'divaguer',
                'to get to the point' => "aller à l'essentiel",
                'witty' => 'spirituel',
                'comeback / retort' => 'réplique',
                "to keep one's cool" => 'garder son calme',
                'on the spot' => 'sur le champ',
                'to put someone on the spot' => "mettre quelqu'un sur la sellette",
                'framework' => 'cadre (de réponse)',
                'spontaneity' => 'spontanéité',
                'composure' => 'sang-froid',
                'to wing it' => 'improviser',
                'tongue-tied' => "muet d'embarras",
            ],
            'grammar' => [
                'Les structures de cadrage rapide : past / present / future ; problem / cause / solution ; for / against / verdict.',
                'Les marqueurs de discours oraux : mind you, admittedly, that said, as it happens.',
            ],
            'functions' => [
                "Gagner du temps : « That's a question I've been asking myself, actually. Let me think about how best to put it. »",
                "Structurer en direct : « I'd look at this from three angles: the cost, the people and the timing. »",
                "Répartie : « I'll take that as a compliment — I think. »",
            ],
            'pronunciation' => [
                'Remplacer les « euh » français par des pauses silencieuses ou « well… », « so… ».',
                'Garder un rythme posé même sous pression.',
            ],
            'spoken' => [
                '« Off the top of my head… » = comme ça, sans réfléchir…',
                "« Funny you should ask » = c'est drôle que vous posiez la question.",
            ],
            'pitfalls' => [
                'Le « euh » français trahit la traduction mentale : entraînez-vous à penser directement en anglais.',
                "Faux ami : « to improvise » est correct, mais « on improvise » se dit plus naturellement « we'll wing it ».",
            ],
            'situations' => [
                ['title' => 'Question surprise en conférence', 'description' => 'Le modérateur d\'une conférence à Lomé vous pose une question inattendue devant deux cents personnes.', 'tags' => ['work', 'conversation']],
                ['title' => 'Toast improvisé', 'description' => 'On vous demande au dernier moment de porter un toast en anglais au mariage d\'un ami togolais qui épouse une jeune femme anglophone rencontrée pendant ses études à l\'étranger.', 'tags' => ['family', 'conversation']],
                ['title' => 'Oral surprise', 'description' => 'Épreuve orale : un sujet tiré au sort, deux minutes de préparation, cinq minutes de parole.', 'tags' => ['exam', 'studies']],
            ],
        ],

        // 5
        [
            'title' => 'Le style professionnel écrit',
            'objectives' => [
                'écrire et dicter des textes professionnels élégants et concis',
                'maîtriser la cohésion et les transitions',
                'adapter le ton à des destinataires de haut niveau',
            ],
            'vocabulary' => [
                'cohesion' => 'cohésion',
                'transition' => 'transition',
                'conciseness' => 'concision',
                'redundancy' => 'redondance',
                'tone of voice' => 'ton',
                'to tailor' => 'adapter',
                'stakeholder letter' => 'lettre aux parties prenantes',
                'position paper' => 'note de position',
                'briefing note' => 'note de synthèse',
                'to draft' => 'rédiger (une première version)',
                'to edit' => 'corriger, retravailler',
                'to tighten' => 'resserrer (un texte)',
                'active voice' => 'voix active',
                'hedging language' => 'langage prudent',
                'to articulate' => 'formuler clairement',
                'persuasive writing' => 'écriture persuasive',
                'readability' => 'lisibilité',
            ],
            'grammar' => [
                "Préférer la voix active pour la clarté, le passif pour l'objectivité ou la diplomatie.",
                'Les connecteurs avancés : notwithstanding, insofar as, in light of, by the same token, conversely.',
            ],
            'functions' => [
                'Resserrer : « Due to the fact that » → « because » ; « It is important to note that » → (supprimer).',
                'Dicter une note de position : « In light of recent developments, we propose a phased approach… »',
                "Adapter le ton : « We regret to inform you… » (formel) / « Unfortunately, we can't… » (neutre).",
            ],
            'pronunciation' => [
                'Lire à voix haute pour tester le rythme : si vous manquez de souffle, la phrase est trop longue.',
                "Les mots savants d'origine latine gardent un accent anglais : conVERsely, notwithSTANding.",
            ],
            'spoken' => [
                "« Kill your darlings » = sacrifiez les phrases que vous aimez mais qui n'apportent rien.",
                '« Keep it short and sweet » = faites court et efficace.',
            ],
            'pitfalls' => [
                "Le français valorise la phrase longue et subordonnée ; l'anglais professionnel valorise la phrase courte.",
                "Faux ami : « to precise » n'existe pas ; « to specify / clarify ».",
            ],
            'situations' => [
                ['title' => 'Lettre au conseil d\'administration', 'description' => 'Vous dictez et peaufinez avec le professeur une lettre stratégique au conseil d\'administration de votre entreprise de Lomé.', 'tags' => ['business', 'work']],
                ['title' => 'Note pour un directeur', 'description' => 'Vous rédigez à l\'oral une note de synthèse d\'une page pour un directeur d\'administration togolaise qui reçoit des partenaires anglophones.', 'tags' => ['admin', 'work']],
                ['title' => 'Article de revue', 'description' => 'Vous retravaillez l\'introduction d\'un article académique de l\'université de Lomé soumis à une revue anglophone.', 'tags' => ['studies', 'exam']],
            ],
        ],

        // 6
        [
            'title' => 'Humour, ironie et sous-entendus',
            'objectives' => [
                "comprendre l'ironie, l'understatement et les sous-entendus",
                "utiliser l'humour à bon escient en contexte professionnel",
                'réagir avec finesse aux plaisanteries',
            ],
            'vocabulary' => [
                'irony' => 'ironie',
                'sarcasm' => 'sarcasme',
                'understatement' => 'euphémisme, litote',
                'deadpan' => 'pince-sans-rire',
                'pun' => 'jeu de mots',
                'tongue-in-cheek' => 'ironique, au second degré',
                'self-deprecating' => 'autodérision',
                'to tease' => 'taquiner',
                'banter' => 'échanges de plaisanteries',
                'to take a joke' => 'savoir encaisser une plaisanterie',
                'inside joke' => 'plaisanterie entre initiés',
                'to fall flat' => 'tomber à plat',
                'dry humor' => 'humour pince-sans-rire',
                'to read between the lines' => 'lire entre les lignes',
                'innuendo' => 'sous-entendu',
                'to crack a joke' => 'faire une blague',
                'witty remark' => 'remarque spirituelle',
            ],
            'grammar' => [
                "L'understatement britannique : « not bad at all » (= excellent), « a bit of a problem » (= un gros problème).",
                'Les intensifs ironiques : « Oh, brilliant », « That went well » (dit après un échec).',
            ],
            'functions' => [
                'Décoder : « When my British boss said the plan was “quite brave”, he meant it was risky. »',
                "Autodérision : « My English used to be so bad that my phone's autocorrect gave up on me. »",
                "Réagir à une taquinerie : « Very funny! I'll get you back for that. »",
            ],
            'pronunciation' => [
                "L'intonation plate (deadpan) du sarcasme britannique ; l'intonation exagérée de l'ironie américaine.",
                'Les jeux de mots reposent souvent sur des homophones : flour / flower, knight / night.',
            ],
            'spoken' => [
                "« I'm only pulling your leg » = je te fais marcher.",
                '« No offense, but… » = sans vouloir te vexer, mais…',
            ],
            'pitfalls' => [
                "L'humour ne se traduit pas : évitez les blagues françaises traduites mot à mot.",
                'Prudence avec le sarcasme en contexte multiculturel : il peut être pris au premier degré.',
            ],
            'situations' => [
                ['title' => 'Pause avec des collègues anglophones', 'description' => 'Pendant une pause à Lomé, des collègues anglophones plaisantent avec beaucoup d\'ironie : comprenez et répondez.', 'tags' => ['work', 'conversation']],
                ['title' => 'Discours avec humour', 'description' => 'Vous ouvrez par une touche d\'autodérision bien dosée le discours de fin d\'année de votre entreprise à Lomé.', 'tags' => ['business', 'work']],
                ['title' => 'Humour en voyage', 'description' => 'En voyage à l\'étranger, un ami anglophone vous raconte des histoires drôles pleines de références locales : suivez et réagissez.', 'tags' => ['conversation', 'travel']],
            ],
        ],

        // 7
        [
            'title' => 'Débats éthiques et philosophiques',
            'objectives' => [
                'discuter de dilemmes éthiques avec rigueur',
                'manier des concepts abstraits avec aisance',
                'construire un raisonnement nuancé sur des valeurs',
            ],
            'vocabulary' => [
                'ethics' => 'éthique',
                'dilemma' => 'dilemme',
                'moral obligation' => 'obligation morale',
                'the greater good' => "l'intérêt général",
                'individual rights' => 'droits individuels',
                'to justify' => 'justifier',
                'principle' => 'principe',
                'consequences' => 'conséquences',
                'integrity' => 'intégrité',
                'accountability' => 'responsabilité',
                'to weigh against' => 'mettre en balance avec',
                'a gray area' => 'une zone grise',
                'to uphold' => 'défendre (un principe)',
                'relativism' => 'relativisme',
                'to presuppose' => 'présupposer',
                'solidarity' => 'solidarité',
                'Ubuntu' => 'Ubuntu (« je suis parce que nous sommes »)',
                'tradition vs modernity' => 'tradition et modernité',
            ],
            'grammar' => [
                "Les modaux de jugement moral au passé : ought to have, needn't have, shouldn't have.",
                'Les propositions concessives complexes : « However compelling the argument may be, … », « Be that as it may, … ».',
            ],
            'functions' => [
                'Poser un dilemme : « Is it ever justifiable to bend the rules to help a member of your community? »',
                'Mettre en balance : « Individual freedom must be weighed against our responsibility to the community. »',
                "Nuancer : « It's a gray area. Be that as it may, transparency should remain the guiding principle. »",
            ],
            'pronunciation' => [
                'Mots abstraits : inTEGrity, philoSOphical, uBUNtu.',
                'Rythme du raisonnement : marquer clairement les articulations (however, therefore, nevertheless).',
            ],
            'spoken' => [
                '« It boils down to… » = ça se résume à…',
                '« That opens a can of worms » = ça soulève une foule de problèmes.',
            ],
            'pitfalls' => [
                "Faux ami : « a moral » = la morale d'une histoire ; « le moral » = morale.",
                'Faux ami : « to ignore » = ne pas tenir compte volontairement ; « ignorer » (ne pas savoir) = not to know / to be unaware.',
            ],
            'situations' => [
                ['title' => 'Comité d\'éthique', 'description' => 'Dans le comité d\'éthique d\'un hôpital de Lomé, vous discutez en anglais d\'un cas délicat de consentement.', 'tags' => ['health', 'work']],
                ['title' => 'Séminaire de philosophie', 'description' => 'Séminaire à l\'université de Lomé sur la solidarité africaine face à l\'individualisme contemporain.', 'tags' => ['studies', 'education']],
                ['title' => 'Dilemme en entreprise', 'description' => 'Un fournisseur étranger propose un « arrangement » : débattez avec votre associé de la bonne conduite à tenir.', 'tags' => ['business', 'work']],
            ],
        ],

        // 8
        [
            'title' => "Culture, littérature et anglais d'Afrique",
            'objectives' => [
                "discuter d'œuvres littéraires et culturelles africaines anglophones",
                'analyser un texte ou un discours avec finesse',
                "parler des variétés d'anglais et de leur légitimité",
            ],
            'vocabulary' => [
                'novel' => 'roman',
                'short story' => 'nouvelle',
                'poetry' => 'poésie',
                'playwright' => 'dramaturge',
                'plot' => 'intrigue',
                'character' => 'personnage',
                'theme' => 'thème',
                'narrator' => 'narrateur',
                'metaphor' => 'métaphore',
                'oral tradition' => 'tradition orale',
                'proverb' => 'proverbe',
                'postcolonial' => 'postcolonial',
                'identity' => 'identité',
                'World Englishes' => 'les anglais du monde',
                'standard English' => 'anglais standard',
                'vernacular' => 'langue vernaculaire',
                'to resonate' => 'trouver un écho',
                'masterpiece' => "chef-d'œuvre",
            ],
            'grammar' => [
                'Le présent de narration pour analyser une œuvre : « In the novel, the hero returns to his village and discovers… ».',
                "Les structures d'analyse : « The author seems to suggest that… », « This could be read as… ».",
            ],
            'functions' => [
                'Analyser : « The novel explores the tension between tradition and change through the eyes of a young narrator. »',
                'Interpréter un proverbe : « This proverb could be read as a warning against pride. »',
                "Défendre une variété d'anglais : « African English isn't “broken” English — it's a legitimate variety with its own norms. »",
            ],
            'pronunciation' => [
                'Lire un poème à voix haute : rythme, accent, pauses de fin de vers.',
                'Comprendre les variétés : anglais américain, anglais britannique, anglais d\'Afrique de l\'Ouest — et l\'anglais parlé par les Togolais.',
            ],
            'spoken' => [
                "« It's a page-turner » = c'est un livre qu'on ne lâche pas.",
                "« It struck a chord with me » = ça m'a touché.",
            ],
            'pitfalls' => [
                'Faux ami : « a novel » = un roman ; « une nouvelle » = a short story ; « les nouvelles » = the news.',
                'Faux ami : « a library » ≠ une librairie (bookstore).',
            ],
            'situations' => [
                ['title' => 'Club de lecture', 'description' => 'Dans un club de lecture anglophone de Lomé, vous présentez et analysez un roman africain que vous avez aimé.', 'tags' => ['conversation', 'education']],
                ['title' => 'Conférence sur les langues', 'description' => 'Vous intervenez sur la place de l\'anglais, du français, de l\'éwé et du kabiyè dans l\'éducation au Togo.', 'tags' => ['education', 'studies']],
                ['title' => 'Proverbes et sagesse', 'description' => 'Vous expliquez à des amis anglophones le sens de proverbes togolais et cherchez leurs équivalents anglais.', 'tags' => ['conversation', 'family']],
            ],
        ],

        // 9
        [
            'title' => 'Expert de son domaine',
            'objectives' => [
                'présenter votre domaine à des experts comme à des non-spécialistes',
                'répondre à des questions techniques pointues',
                'défendre une analyse face à des pairs critiques',
            ],
            'vocabulary' => [
                'field of expertise' => 'domaine de compétence',
                'state of the art' => "état de l'art, dernier cri",
                'benchmark' => 'référence, étalon',
                'best practice' => 'bonne pratique',
                'case study' => 'étude de cas',
                'to peer review' => 'évaluer par les pairs',
                'robust' => 'solide',
                'rigorous' => 'rigoureux',
                'assumption' => 'hypothèse de départ',
                'limitation' => 'limite',
                'to extrapolate' => 'extrapoler',
                'to corroborate' => 'corroborer',
                'counterintuitive' => 'contre-intuitif',
                'caveat' => 'réserve, mise en garde',
                'cutting-edge' => 'de pointe',
                'to be at the forefront of' => "être à l'avant-garde de",
                'to drill down into' => 'approfondir',
            ],
            'grammar' => [
                'La prudence académique : « The data would appear to suggest… », « It is not inconceivable that… ».',
                'Les structures de précision : « to the extent that », « insofar as », « with the caveat that ».',
            ],
            'functions' => [
                'Adapter le niveau : « For the non-specialists in the room: think of it as… For the experts: the model assumes… »',
                "Reconnaître une limite : « That's a fair challenge. Our sample is small, so I'd be cautious about extrapolating. »",
                'Défendre : « The findings are corroborated by two independent studies. »',
            ],
            'pronunciation' => [
                'Prononcer clairement les termes techniques de votre domaine (à travailler avec le professeur).',
                'Caveat [ˈkæviæt] : trois syllabes.',
            ],
            'spoken' => [
                "« I'll take that offline » = on en reparle en aparté.",
                "« That's above my pay grade » = ce n'est pas de mon ressort (humour).",
            ],
            'pitfalls' => [
                'Faux ami : « an expertise » ≠ « une expertise » (rapport) → an expert report / assessment.',
                'Faux ami : « a manifestation » = un signe ; « une manifestation » (événement) = an event, (protestation) = a demonstration.',
            ],
            'situations' => [
                ['title' => 'Panel d\'experts', 'description' => 'Vous intervenez comme expert de votre secteur dans un panel international organisé à Lomé et répondez aux questions pointues.', 'tags' => ['work', 'business']],
                ['title' => 'Soutenance de thèse', 'description' => 'Vous défendez vos résultats à l\'université de Lomé face à un jury exigeant.', 'tags' => ['studies', 'exam']],
                ['title' => 'Formation de professionnels', 'description' => 'Vous formez des professionnels de santé anglophones venus de l\'étranger à une nouvelle pratique lors d\'un atelier à Lomé.', 'tags' => ['health', 'education']],
            ],
        ],

        // 10
        [
            'title' => 'Diplomatie et relations internationales',
            'objectives' => [
                'employer le langage diplomatique avec aisance',
                'représenter une organisation dans un cadre officiel',
                'exprimer un désaccord ferme en restant courtois',
            ],
            'vocabulary' => [
                'delegation' => 'délégation',
                'summit' => 'sommet',
                'bilateral' => 'bilatéral',
                'sovereignty' => 'souveraineté',
                'cooperation' => 'coopération',
                'to convene' => 'convoquer, se réunir',
                'communiqué' => 'communiqué',
                'to reaffirm' => 'réaffirmer',
                'to express concern' => 'exprimer sa préoccupation',
                'to welcome (a decision)' => 'saluer (une décision)',
                'to call upon' => 'appeler (à agir)',
                'to reiterate' => 'réitérer',
                'protocol' => 'protocole',
                'envoy' => 'envoyé',
                'consensus-building' => 'recherche de consensus',
                'constructive dialogue' => 'dialogue constructif',
                'regional integration' => 'intégration régionale',
                'to defuse tensions' => 'désamorcer les tensions',
            ],
            'grammar' => [
                'Le langage diplomatique et ses codes : « We note with concern… » (= désaccord), « frank discussions » (= désaccord profond).',
                'Les formules de déférence : « Your Excellency », « Distinguished delegates », « Allow me to… ».',
            ],
            'functions' => [
                'Ouvrir officiellement : « Your Excellencies, distinguished delegates, allow me first to thank our hosts. »',
                'Désaccord diplomatique : « While we respect the position of our partners, we cannot support this proposal in its current form. »',
                'Saluer : « We welcome the progress achieved and reaffirm our commitment to regional integration. »',
            ],
            'pronunciation' => [
                'Sovereignty [ˈsɑːvrənti] : le « g » est muet.',
                'Débit lent et articulé, pauses marquées : la gravité est portée par la voix.',
            ],
            'spoken' => [
                "« Let me be frank » = permettez-moi d'être franc.",
                '« We agreed to disagree » = nous avons constaté nos désaccords.',
            ],
            'pitfalls' => [
                'Faux ami : « to assist a meeting » ✗ → « to attend » ✓.',
                "Le « nous » de modestie français se traduit rarement : utilisez « I » ou le nom de l'organisation.",
            ],
            'situations' => [
                ['title' => 'Sommet à Lomé', 'description' => 'Vous prononcez la déclaration de votre délégation lors d\'un sommet régional organisé à Lomé.', 'tags' => ['admin', 'travel']],
                ['title' => 'Réunion bilatérale', 'description' => 'Réunion tendue entre représentants togolais et une délégation étrangère anglophone sur un accord commercial.', 'tags' => ['business', 'admin']],
                ['title' => 'Simulation des Nations unies', 'description' => 'Vous représentez une délégation dans une simulation universitaire de l\'ONU à l\'université de Lomé.', 'tags' => ['studies', 'exam']],
            ],
        ],

        // 11
        [
            'title' => "Reformuler, résumer et servir d'intermédiaire",
            'objectives' => [
                'résumer fidèlement un long échange ou un document',
                "servir d'intermédiaire entre francophones et anglophones",
                'reformuler en adaptant le registre et le niveau de détail',
            ],
            'vocabulary' => [
                'to paraphrase' => 'paraphraser',
                'to summarize' => 'résumer',
                'gist' => "l'essentiel",
                'verbatim' => 'mot pour mot',
                'to interpret' => 'interpréter (oral)',
                'to translate' => 'traduire',
                'liaison' => 'intermédiaire',
                'nuance' => 'nuance',
                'to gloss over' => 'passer sous silence',
                'to convey the tone' => 'rendre le ton',
                'untranslatable' => 'intraduisible',
                'equivalent' => 'équivalent',
                'faithful' => 'fidèle',
                'to clarify intent' => "clarifier l'intention",
                'cultural reference' => 'référence culturelle',
                'key takeaway' => 'point essentiel',
                'to condense' => 'condenser',
            ],
            'grammar' => [
                'Le discours indirect sophistiqué : « She acknowledged that…, while insisting that… ».',
                'Les verbes introducteurs précis : point out, stress, concede, question, dismiss, highlight.',
            ],
            'functions' => [
                "Résumer : « The gist of it is that they'll accept the deal if we handle the transport. »",
                "Servir d'intermédiaire : « What Mr. Agbodjan is saying, in essence, is that the deadline is non-negotiable. »",
                "Rendre une nuance : « He didn't say no — he said it would be “difficult”, which is a polite no. »",
            ],
            'pronunciation' => [
                'Interprétation : chuchoter, rester neutre, suivre le rythme du locuteur.',
                'Verbatim [vɚˈbeɪɾɪm].',
            ],
            'spoken' => [
                '« Lost in translation » = perdu à la traduction.',
                '« In a nutshell » = en un mot, en résumé.',
            ],
            'pitfalls' => [
                "Ne traduisez pas mot à mot : traduisez l'intention et le registre.",
                'Faux ami : « to resume » ≠ résumer → to sum up / summarize.',
            ],
            'situations' => [
                ['title' => 'Interprète au Grand Marché', 'description' => 'Vous servez d\'interprète entre une commerçante togolaise et un acheteur étranger anglophone.', 'tags' => ['business', 'market']],
                ['title' => 'Synthèse pour la direction', 'description' => 'Vous résumez en trois minutes un long rapport en anglais pour votre direction francophone à Lomé.', 'tags' => ['work', 'admin']],
                ['title' => 'Consultation médicale', 'description' => 'Vous aidez un proche francophone à comprendre un médecin anglophone d\'une clinique de Lomé, en restituant les nuances.', 'tags' => ['health', 'family']],
            ],
        ],

        // 12
        [
            'title' => 'Leadership inspirant et storytelling',
            'objectives' => [
                'raconter une histoire qui porte une vision',
                'inspirer et mobiliser un groupe',
                'parler avec authenticité et autorité',
            ],
            'vocabulary' => [
                'storytelling' => 'art de raconter',
                'vision' => 'vision',
                'mission' => 'mission',
                'to inspire' => 'inspirer',
                'to rally' => 'rassembler, mobiliser',
                'turning point' => 'moment décisif',
                'setback' => 'revers',
                'resilience' => 'résilience',
                'authenticity' => 'authenticité',
                'legacy' => 'héritage',
                'to empower' => "donner les moyens d'agir",
                'purpose' => "raison d'être",
                'collective effort' => 'effort collectif',
                'to overcome' => 'surmonter',
                'milestone' => 'étape importante',
                'aspiration' => 'aspiration',
                'to leave a mark' => 'laisser une empreinte',
                'call to action' => "appel à l'action",
            ],
            'grammar' => [
                'Le passé narratif riche : past perfect continuous, would pour les habitudes passées (« Every morning, my mother would… »).',
                "Les structures d'appel : « Let us…», « May we never forget… », « Imagine a future where… ».",
            ],
            'functions' => [
                'Raconter : « I had been knocking on doors for months when a market woman at the Grand Marché in Lomé gave me my first chance. »',
                'Transmettre une vision : « Imagine a generation of young Africans who negotiate, create and lead in any language. »',
                "Mobiliser : « This is not my project. It's ours. Let's build it together. »",
            ],
            'pronunciation' => [
                'Variations de rythme et de volume pour faire vivre un récit ; silences porteurs.',
                'Legacy [ˈleɡəsi] : accent sur la première syllabe.',
            ],
            'spoken' => [
                "« Fast forward to today… » = aujourd'hui, des années plus tard…",
                "« That's when it hit me » = c'est là que j'ai compris.",
            ],
            'pitfalls' => [
                "L'authenticité convainc plus que la grandiloquence : les grands mots sans histoire sonnent creux.",
                'Faux ami : « an anecdote » est un récit court ; « anecdotique » (sans importance) = trivial / insignificant.',
            ],
            'situations' => [
                ['title' => 'Discours aux employés', 'description' => 'Pour les dix ans de votre entreprise de Lomé, vous racontez son histoire et présentez la vision des dix prochaines.', 'tags' => ['business', 'work']],
                ['title' => 'Cérémonie de fin d\'études', 'description' => 'Vous êtes invité à parler aux étudiants qui terminent leur cursus à l\'université de Kara.', 'tags' => ['education', 'studies']],
                ['title' => 'Mobilisation communautaire', 'description' => 'Vous mobilisez votre quartier de Lomé autour d\'un projet de santé grâce à une histoire personnelle.', 'tags' => ['health', 'conversation', 'family']],
            ],
        ],

    ],

];
