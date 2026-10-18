<?php

/*
|--------------------------------------------------------------------------
| Programme officiel — niveau A1 (débutant)
|--------------------------------------------------------------------------
|
| Contenu original, aligné sur les descripteurs A1 du CECRL : comprendre et
| utiliser des expressions familières et quotidiennes, se présenter, poser
| des questions simples sur des sujets familiers, si l'interlocuteur parle
| lentement et distinctement.
|
| Structure d'une unité : voir App\Support\Curriculum\Curriculum.
| Thèmes autorisés pour les situations : Curriculum::TAGS.
|
*/

return [

    'title' => "A1 — Débutant : les premiers pas à l'oral",

    'units' => [

        // 1
        [
            'title' => 'Bonjour ! Se saluer et se présenter',
            'objectives' => [
                "saluer quelqu'un le matin, l'après-midi et le soir",
                'dire votre nom, votre pays et votre ville',
                "demander à quelqu'un comment il s'appelle et d'où il vient",
            ],
            'vocabulary' => [
                'hello / hi' => 'bonjour / salut',
                'good morning' => 'bonjour (le matin)',
                'good afternoon' => "bonjour (l'après-midi)",
                'good evening' => 'bonsoir',
                'goodbye / bye' => 'au revoir',
                'see you later' => 'à plus tard',
                'my name is…' => "je m'appelle…",
                'first name' => 'prénom',
                'surname' => 'nom de famille',
                "I'm from…" => 'je viens de…',
                'I live in…' => "j'habite à…",
                'country' => 'pays',
                'city / town' => 'ville',
                'nice to meet you' => 'enchanté(e)',
                'how are you?' => 'comment allez-vous ?',
                "I'm fine, thank you" => 'je vais bien, merci',
                'and you?' => 'et vous ?',
                'please' => "s'il vous plaît",
                'thank you / thanks' => 'merci',
                'excuse me' => 'excusez-moi',
            ],
            'grammar' => [
                'Le verbe to be au présent : I am, you are, he/she is (« I am Kofi », « She is from Kara »).',
                'Les pronoms personnels sujets : I, you, he, she, we, they.',
                "Les questions avec what et where : « What's your name? », « Where are you from? ».",
            ],
            'functions' => [
                'Saluer selon le moment de la journée : « Good morning, madam! »',
                "Se présenter : « My name is Afi. I'm from Togo. I live in Lomé. »",
                "Demander des nouvelles et répondre : « How are you? — I'm fine, thanks. And you? »",
            ],
            'pronunciation' => [
                "Le « h » aspiré de hello, hi, how : on souffle légèrement, on ne le supprime pas (« 'ello » ✗).",
                "Les formes contractées : I'm, you're, she's, what's — plus naturelles que I am, you are.",
            ],
            'spoken' => [
                "« Hi! How's it going? » — façon décontractée de dire bonjour entre amis.",
                '« Nice to meet you! — Nice to meet you too! » : la réponse reprend la phrase avec too.',
            ],
            'pitfalls' => [
                'Ne dites pas « I have 25 years » ni « I am called… » au quotidien : on dit « My name is… ».',
                "« Excuse me » sert à attirer l'attention ; pour s'excuser d'une erreur, on dit « Sorry ».",
            ],
            'situations' => [
                ['title' => 'Premier jour à l\'université de Lomé', 'description' => 'À la rentrée, un enseignant invité anglophone salue les étudiants de votre amphi. Saluez-le et présentez-vous : nom, ville d\'origine, quartier.', 'tags' => ['studies', 'education']],
                ['title' => 'Un client étranger à la boutique', 'description' => 'Un client anglophone de passage à Lomé entre dans votre boutique du quartier Bè. Saluez-le, demandez son nom et d\'où il vient, présentez-vous.', 'tags' => ['business', 'market', 'conversation']],
                ['title' => 'Accueil d\'un visiteur à l\'hôtel', 'description' => 'Vous travaillez à la réception d\'un hôtel du boulevard du Mono. Accueillez un visiteur anglophone qui arrive à Lomé et présentez-vous.', 'tags' => ['work', 'travel', 'conversation']],
            ],
        ],

        // 2
        [
            'title' => 'Ma famille',
            'objectives' => [
                'présenter les membres de votre famille',
                "dire leur âge et ce qu'ils font, en phrases simples",
                "poser des questions sur la famille de quelqu'un",
            ],
            'vocabulary' => [
                'family' => 'famille',
                'father / dad' => 'père / papa',
                'mother / mom' => 'mère / maman',
                'parents' => 'parents (père et mère)',
                'brother' => 'frère',
                'sister' => 'sœur',
                'son' => 'fils',
                'daughter' => 'fille (enfant)',
                'children / kids' => 'enfants',
                'husband' => 'mari',
                'wife' => 'épouse',
                'grandmother / grandma' => 'grand-mère',
                'grandfather / grandpa' => 'grand-père',
                'uncle' => 'oncle',
                'aunt' => 'tante',
                'cousin' => 'cousin(e)',
                'married' => 'marié(e)',
                'single' => 'célibataire',
                'old / young' => 'âgé / jeune',
                'relatives' => 'les proches, la famille élargie',
            ],
            'grammar' => [
                "Les adjectifs possessifs : my, your, his, her, our, their (« his sister » = la sœur de lui, « her brother » = le frère d'elle).",
                "Le génitif 's : « Kossi's wife », « my mother's name ».",
                "Have / has pour la possession : « I have two brothers », « She has three children », « Do you have any children? » (en anglais britannique, on dit aussi « I've got »).",
            ],
            'functions' => [
                "Présenter sa famille : « This is my sister. Her name is Ama. She's twenty. »",
                "Dire l'âge : « My son is seven (years old). »",
                'Poser des questions : « Do you have any children? », « How many brothers do you have? »',
            ],
            'pronunciation' => [
                'Le son « th » de mother, father, brother : la langue entre les dents, sans dire « z » ni « d » (« moder » ✗).',
                "Le « s » final de brothers, sisters, parents se prononce : c'est la marque du pluriel.",
            ],
            'spoken' => [
                "« I have » et « Do you have…? » sont les formes courantes en anglais américain ; « I've got » (anglais britannique) se comprend aussi.",
                '« Mom » et « Dad » sont les mots de tous les jours (anglais américain) ; en anglais britannique, on dit aussi « Mum ».',
            ],
            'pitfalls' => [
                "His / her dépend du possesseur, pas de l'objet : « Kofi and his mother » (pas « her mother »).",
                'Parents veut dire père et mère seulement ; pour toute la famille élargie, dites relatives.',
            ],
            'situations' => [
                ['title' => 'La photo de famille sur WhatsApp', 'description' => 'Une collègue anglophone de votre entreprise internationale à Lomé vous demande de lui montrer les photos de votre famille sur WhatsApp. Présentez chaque personne.', 'tags' => ['family', 'phone', 'conversation']],
                ['title' => 'Famille d\'accueil à Lomé', 'description' => 'Vous accueillez chez vous, à Agoè, un jeune volontaire anglophone. Présentez-lui les membres de la famille.', 'tags' => ['family', 'conversation', 'travel']],
                ['title' => 'Pause au bureau', 'description' => 'Pendant la pause, un collègue étranger de votre entreprise internationale à Lomé vous parle de ses enfants. Posez-lui des questions et parlez des vôtres.', 'tags' => ['work', 'conversation']],
            ],
        ],

        // 3
        [
            'title' => "Les nombres, l'âge et les numéros de téléphone",
            'objectives' => [
                'compter de 0 à 100 et donner votre âge',
                'dicter et noter un numéro de téléphone',
                "épeler votre nom avec l'alphabet anglais",
            ],
            'vocabulary' => [
                'number' => 'nombre, numéro',
                'phone number' => 'numéro de téléphone',
                'zero / oh' => 'zéro',
                'thirteen / thirty' => 'treize / trente',
                'fourteen / forty' => 'quatorze / quarante',
                'fifteen / fifty' => 'quinze / cinquante',
                'a hundred' => 'cent',
                'double (two)' => 'deux fois (deux) — dans un numéro, surtout en anglais britannique',
                'age' => 'âge',
                'how old…?' => 'quel âge… ?',
                'to spell' => 'épeler',
                'letter' => 'lettre',
                'to call' => 'appeler',
                'to write down' => 'noter',
                'again' => 'encore, de nouveau',
                'slowly' => 'lentement',
                'sorry?' => "pardon ? (je n'ai pas compris)",
                "it's correct" => "c'est exact",
            ],
            'grammar' => [
                "How old are you? — I'm 32 (years old). On utilise be, jamais have, pour l'âge.",
                "L'impératif poli : « Please spell your name », « Can you repeat, please? ».",
            ],
            'functions' => [
                "Donner son numéro chiffre par chiffre : « It's nine-oh, four-four, one-two… »",
                'Épeler : « K-O-F-I, Kofi. »',
                'Demander de répéter : « Sorry, can you say that again, slowly? »',
            ],
            'pronunciation' => [
                "L'accent tonique distingue thirTEEN (sur la fin) et THIRty (sur le début) — très important au téléphone.",
                'Les lettres pièges pour les francophones : A [eɪ], E [iː], I [aɪ], G [dʒiː], J [dʒeɪ], R [ɑː], Y [waɪ].',
            ],
            'spoken' => [
                'On dit souvent « oh » au lieu de « zero » dans un numéro de téléphone.',
                '« Five five » = 55 dans un numéro (anglais américain) ; en anglais britannique, on dit aussi « double five ».',
            ],
            'pitfalls' => [
                "« I have 30 years » est une traduction du français : dites « I'm thirty ».",
                'Les lettres E et I sont inversées par rapport au français : E se dit « i », I se dit « aï ».',
            ],
            'situations' => [
                ['title' => 'Échanger les numéros au port', 'description' => 'Après une réunion au port autonome de Lomé, le représentant anglophone d\'une compagnie maritime internationale vous demande votre numéro WhatsApp. Dictez-le et notez le sien.', 'tags' => ['phone', 'business', 'work']],
                ['title' => 'Inscription à l\'école internationale', 'description' => 'À l\'accueil d\'une école bilingue de Lomé, vous épelez votre nom et donnez l\'âge et la classe de votre enfant.', 'tags' => ['education', 'admin', 'family']],
                ['title' => 'Le standard de la clinique', 'description' => 'Vous appelez une clinique de Lomé où un médecin anglophone consulte : épelez le nom d\'un proche et laissez votre numéro.', 'tags' => ['health', 'phone']],
            ],
        ],

        // 4
        [
            'title' => "Au marché : acheter et compter l'argent",
            'objectives' => [
                'demander un prix et comprendre la réponse',
                'acheter des produits en précisant la quantité',
                'négocier poliment avec des phrases simples',
            ],
            'vocabulary' => [
                'market' => 'marché',
                'store / shop' => 'magasin / boutique',
                'seller / trader' => 'vendeur / commerçant',
                'customer' => 'client',
                'price' => 'prix',
                'how much is it?' => "c'est combien ?",
                'cheap' => 'pas cher',
                'expensive' => 'cher',
                'money' => 'argent',
                'change' => 'monnaie (à rendre)',
                'a kilo of' => 'un kilo de',
                'a bag of' => 'un sac de',
                'tomatoes' => 'tomates',
                'rice' => 'riz',
                'onions' => 'oignons',
                'fish' => 'poisson',
                'to buy' => 'acheter',
                'to sell' => 'vendre',
                'discount' => 'réduction',
                "that's all" => "c'est tout",
            ],
            'grammar' => [
                'This / that / these / those : « How much is this? », « How much are those onions? ».',
                'Dénombrable et indénombrable : some rice (pas de pluriel), two tomatoes ; how much / how many.',
                "Want / would like : « I want two kilos » (direct), « I'd like two kilos, please » (poli).",
            ],
            'functions' => [
                "Demander un prix : « How much is the fish? — It's two thousand francs. »",
                "Négocier : « It's too expensive. Can you reduce it a bit? »",
                "Conclure : « OK, I'll take it. Here you are. Keep the change. »",
            ],
            'pronunciation' => [
                'La terminaison -es de tomatoes, potatoes : « -oz » ; et le « th » de thousand.',
                'Intonation de la question « How much is it? » : la voix descend à la fin (question avec un mot interrogatif).',
            ],
            'spoken' => [
                "« I'll take it » = je le prends ; « I'm just looking » = je regarde seulement.",
                '« Can you do it for…? » : la façon naturelle de proposer un prix plus bas.',
            ],
            'pitfalls' => [
                'Money est indénombrable : « much money », jamais « moneys ».',
                'Faux ami : « monnaie » (pièces à rendre) se dit change, pas money.',
            ],
            'situations' => [
                ['title' => 'Au Grand Marché de Lomé', 'description' => 'Une touriste anglophone veut acheter du pagne et des tomates à votre étal du Grand Marché. Donnez les prix et négociez.', 'tags' => ['market', 'business']],
                ['title' => 'Au marché, en voyage à l\'étranger', 'description' => 'En voyage à l\'étranger, vous achetez du riz et des oignons dans un grand marché. Demandez les prix dans la monnaie locale et négociez.', 'tags' => ['travel', 'market']],
                ['title' => 'Commande au grossiste', 'description' => 'Vous appelez un grossiste international anglophone pour connaître le prix d\'un sac de riz et d\'un carton d\'huile livrés au port autonome de Lomé.', 'tags' => ['business', 'phone']],
            ],
        ],

        // 5
        [
            'title' => 'Chez moi et dans mon quartier',
            'objectives' => [
                'décrire votre maison et ses pièces',
                "dire ce qu'il y a dans votre quartier",
                'situer des objets et des lieux',
            ],
            'vocabulary' => [
                'house' => 'maison',
                'apartment' => 'appartement (en anglais britannique, on dit aussi « flat »)',
                'room' => 'pièce, chambre',
                'bedroom' => 'chambre à coucher',
                'living room' => 'salon',
                'kitchen' => 'cuisine',
                'bathroom' => 'salle de bain',
                'yard / compound' => 'cour / concession',
                'neighbor' => 'voisin',
                'neighborhood' => 'quartier',
                'church' => 'église',
                'mosque' => 'mosquée',
                'pharmacy' => 'pharmacie',
                'near' => 'près de',
                'next to' => 'à côté de',
                'opposite' => 'en face de',
                'behind' => 'derrière',
                'in front of' => 'devant',
            ],
            'grammar' => [
                'There is / there are : « There is a pharmacy near my house », « There are three rooms ».',
                'Les prépositions de lieu : in, on, under, next to, opposite, behind, between.',
                "Les questions : « Is there a…? », « Are there any…? » — réponses courtes « Yes, there is / No, there aren't ».",
            ],
            'functions' => [
                'Décrire son logement : « I live in a small house with two bedrooms and a big yard. »',
                'Situer : « The market is opposite the church. »',
                'Demander : « Is there a bank near here? »',
            ],
            'pronunciation' => [
                "La contraction there's [ðɛrz] : « th » doux, langue entre les dents.",
                'Voyelle longue / courte : live [ɪ] (habiter) et leave [iː] (partir) ne se prononcent pas pareil.',
            ],
            'spoken' => [
                "À l'oral on dit souvent « There's two banks » ; à l'écrit et en examen, gardez « There are ».",
                '« Just around the corner » = juste à côté, au coin de la rue.',
            ],
            'pitfalls' => [
                'Ne traduisez pas « il y a » par « it has » : dites there is / there are.',
                'Room est une pièce en général ; la chambre à coucher se dit bedroom.',
            ],
            'situations' => [
                ['title' => 'Louer une chambre près du campus', 'description' => 'Un étudiant anglophone en échange cherche une chambre à louer près de l\'université de Lomé. Décrivez la chambre et le quartier.', 'tags' => ['business', 'studies']],
                ['title' => 'Accueillir un collègue', 'description' => 'Un collègue anglophone vient dîner chez vous à Hédzranawoé. Expliquez-lui où se trouve la maison à partir du carrefour le plus proche.', 'tags' => ['work', 'conversation', 'family']],
                ['title' => 'Le quartier de l\'hôtel', 'description' => 'Un client anglophone d\'un hôtel de Lomé vous demande s\'il y a une pharmacie, un distributeur et une église près de l\'hôtel.', 'tags' => ['travel', 'health', 'work']],
            ],
        ],

        // 6
        [
            'title' => "Ma journée et l'heure",
            'objectives' => [
                "dire l'heure et demander l'heure",
                'raconter votre journée habituelle',
                'dire à quelle fréquence vous faites quelque chose',
            ],
            'vocabulary' => [
                'time' => 'heure, temps',
                "o'clock" => 'heure pile',
                'half past' => 'et demie',
                'quarter past / to' => 'et quart / moins le quart',
                'to wake up' => 'se réveiller',
                'to get up' => 'se lever',
                'to take a shower' => 'prendre une douche',
                'to have breakfast' => 'prendre le petit-déjeuner',
                'to go to work' => 'aller au travail',
                'to start / to finish' => 'commencer / finir',
                'to have lunch' => 'déjeuner',
                'to go to bed' => 'se coucher',
                'always' => 'toujours',
                'usually' => "d'habitude",
                'sometimes' => 'parfois',
                'never' => 'jamais',
                'every day' => 'tous les jours',
                'weekend' => 'week-end',
            ],
            'grammar' => [
                'Le présent simple pour les habitudes : « I get up at six », « She works in a bank ».',
                "Le -s de la 3e personne : he works, she goes, it finishes — à l'oral aussi !",
                "Place des adverbes de fréquence : avant le verbe (« I usually walk »), après be (« I'm always late »).",
            ],
            'functions' => [
                "Dire l'heure : « It's half past seven », « It's quarter to nine ».",
                'Raconter sa routine : « I usually wake up at five thirty and I pray. Then I have breakfast. »',
                'Demander : « What time do you start work? »',
            ],
            'pronunciation' => [
                'Les trois prononciations du -s : works [s], goes [z], finishes [ɪz].',
                'Le « h » de half et have : bien soufflé.',
            ],
            'spoken' => [
                "Les natifs disent souvent l'heure en chiffres : « It's seven thirty » plutôt que « half past seven ».",
                "« I'm running late » = je suis en retard.",
            ],
            'pitfalls' => [
                'Oubli du -s à la 3e personne : « He work » ✗ → « He works » ✓ (erreur très fréquente).',
                "« Half past seven » = 7 h 30 (pas 6 h 30 comme l'allemand « halb sieben »).",
            ],
            'situations' => [
                ['title' => 'Entretien pour un petit emploi', 'description' => 'Le gérant anglophone d\'un restaurant de Lomé vous demande à quelle heure vous pouvez commencer et comment se passe votre journée.', 'tags' => ['work']],
                ['title' => 'Emploi du temps à l\'université de Kara', 'description' => 'Un étudiant étranger en échange à l\'université de Kara compare sa journée de cours avec la vôtre.', 'tags' => ['studies', 'conversation']],
                ['title' => 'Rendez-vous téléphonique', 'description' => 'Un client étranger veut passer à votre boutique pendant son séjour à Lomé : fixez une heure qui convient aux deux.', 'tags' => ['business', 'phone']],
            ],
        ],

        // 7
        [
            'title' => 'Manger et boire',
            'objectives' => [
                'parler de ce que vous aimez manger et boire',
                'commander dans un maquis ou un restaurant',
                'proposer à manger ou à boire à un invité',
            ],
            'vocabulary' => [
                'food' => 'nourriture',
                'drink' => 'boisson',
                'breakfast / lunch / dinner' => 'petit-déjeuner / déjeuner / dîner',
                'chicken' => 'poulet',
                'beef' => 'bœuf',
                'beans' => 'haricots',
                'plantain' => 'banane plantain',
                'yam' => 'igname',
                'spicy' => 'pimenté',
                'sweet' => 'sucré',
                'water' => 'eau',
                'juice' => 'jus',
                'menu' => 'carte, menu',
                'the check' => "l'addition (en anglais britannique, on dit aussi « the bill »)",
                'hungry' => 'avoir faim',
                'thirsty' => 'avoir soif',
                'delicious' => 'délicieux',
                'to cook' => 'cuisiner',
            ],
            'grammar' => [
                "Like / love / don't like + nom ou verbe-ing : « I love rice », « I don't like cooking ».",
                'Would you like…? pour proposer : « Would you like some water? — Yes, please / No, thank you. »',
                "Some / any : some dans l'affirmation et les offres, any dans les questions et la négation.",
            ],
            'functions' => [
                'Commander : « Can I have the grilled fish with plantain, please? »',
                "Dire ses goûts : « I like spicy food, but I don't like beef. »",
                "Demander l'addition : « Can we have the check, please? »",
            ],
            'pronunciation' => [
                'Voyelles longues de beans [iː] et food [uː] : tenez-les longtemps, et opposez-les aux courtes de bins [ɪ] et good [ʊ].',
                'Le « h » de hungry ; ne pas confondre hungry (faim) et angry (en colère).',
                "Water à l'américaine [ˈwɑːɾɚ] : le « t » entre deux voyelles devient un « r » français très bref, et le « r » final se prononce.",
            ],
            'spoken' => [
                "« I'm starving! » = je meurs de faim (exagération courante).",
                '« Can I get…? » est très utilisé (surtout en anglais américain) pour commander.',
            ],
            'pitfalls' => [
                "On n'« a » pas faim en anglais, on « est » : « I'm hungry » (pas « I have hunger »).",
                "« I would like a coffee » : ne dites pas « I want » au serveur, c'est trop direct.",
            ],
            'situations' => [
                ['title' => 'Au maquis avec un client', 'description' => 'Vous invitez un client étranger dans un maquis de Lomé. Expliquez-lui l\'akoumé, le fufu et le poisson braisé, et commandez pour vous deux.', 'tags' => ['business', 'conversation']],
                ['title' => 'Restaurant à l\'étranger', 'description' => 'En voyage à l\'étranger, vous lisez la carte, posez des questions sur les plats et commandez.', 'tags' => ['travel']],
                ['title' => 'Recevoir à la maison', 'description' => 'Une amie anglophone vient chez vous. Proposez-lui à boire et à manger et parlez de vos plats togolais préférés.', 'tags' => ['family', 'conversation']],
            ],
        ],

        // 8
        [
            'title' => 'Se déplacer en ville',
            'objectives' => [
                'demander et indiquer un chemin simple',
                'prendre un taxi ou une moto et discuter le prix',
                'comprendre des directions données lentement',
            ],
            'vocabulary' => [
                'street / road' => 'rue / route',
                'roundabout' => 'rond-point',
                'traffic lights' => 'feux de circulation',
                'bus station' => 'gare routière',
                'taxi' => 'taxi',
                'motorbike taxi' => 'taxi-moto (zémidjan)',
                'turn left / right' => 'tournez à gauche / à droite',
                'go straight (ahead)' => 'allez tout droit',
                'stop here' => 'arrêtez-vous ici',
                'far' => 'loin',
                'close / near' => 'proche',
                'the way to…' => 'le chemin pour…',
                'fare' => 'prix du trajet',
                'to drop someone off' => "déposer quelqu'un",
                'to get on / off' => "monter / descendre (d'un véhicule)",
                'traffic jam' => 'embouteillage',
                'map' => 'plan, carte',
            ],
            'grammar' => [
                "L'impératif pour donner des directions : « Go straight, then turn left at the roundabout. »",
                'Les questions avec how : « How do I get to…? », « How far is it? », « How much is the fare? »',
            ],
            'functions' => [
                'Demander son chemin : « Excuse me, how do I get to the Grand Marché? »',
                'Négocier une course : « How much to the airport? — Three thousand. — Two thousand? »',
                "Indiquer : « It's the second street on the right. »",
            ],
            'pronunciation' => [
                'Le « r » américain (right, road, car) : la langue ne touche pas le palais, pas de « r » roulé ni de « r » de gorge ; il se prononce aussi en fin de mot.',
                'Straight [streɪt] : le « gh » ne se prononce pas.',
            ],
            'spoken' => [
                '« Drop me off here » / « Drop me off at the corner » : très courant, et utile avec les visiteurs à Lomé.',
                "« It's about a five-minute walk » = c'est à environ cinq minutes à pied.",
            ],
            'pitfalls' => [
                '« Turn on the left » ✗ → « turn left » ✓.',
                "Faux ami : « car » en anglais = voiture ; l'autocar se dit bus (en anglais britannique, on dit aussi « coach »).",
            ],
            'situations' => [
                ['title' => 'Un touriste perdu à Lomé', 'description' => 'Un touriste anglophone cherche le marché des féticheurs d\'Akodessewa. Indiquez-lui le chemin depuis le rond-point du port.', 'tags' => ['conversation', 'travel']],
                ['title' => 'Zémidjan pour l\'aéroport', 'description' => 'Un visiteur anglophone doit aller de Tokoin à l\'aéroport de Lomé. Aidez-le à négocier le prix avec un conducteur de zémidjan.', 'tags' => ['transport', 'travel']],
                ['title' => 'Livraison client', 'description' => 'Vous êtes livreur à Lomé ; un client anglophone vous guide au téléphone jusqu\'à sa maison à Baguida.', 'tags' => ['transport', 'work', 'phone']],
            ],
        ],

        // 9
        [
            'title' => 'Le travail et les métiers',
            'objectives' => [
                'dire quel est votre métier et où vous travaillez',
                'décrire simplement ce que vous faites au travail',
                "poser des questions sur le travail de quelqu'un",
            ],
            'vocabulary' => [
                'job' => 'emploi, métier',
                'work' => 'travail',
                'teacher' => 'enseignant',
                'nurse' => 'infirmier, infirmière',
                'doctor' => 'médecin',
                'trader' => 'commerçant',
                'driver' => 'chauffeur',
                'farmer' => 'agriculteur',
                'tailor' => 'couturier, tailleur',
                'student' => 'étudiant',
                'accountant' => 'comptable',
                'office' => 'bureau',
                'company' => 'entreprise',
                'boss' => 'patron, chef',
                'colleague' => 'collègue',
                'salary' => 'salaire',
                'unemployed' => 'sans emploi',
                'self-employed' => 'à son compte',
            ],
            'grammar' => [
                "L'article a/an devant un métier : « I'm a nurse », « She's an accountant » (obligatoire en anglais).",
                'Questions au présent simple avec do/does : « What do you do? », « Where does he work? ».',
            ],
            'functions' => [
                "Dire son métier : « I'm a tailor. I make clothes for men and women. »",
                "Dire où l'on travaille : « I work for a transport company in Lomé. »",
                "Demander : « What do you do? — I'm self-employed. I sell phones. »",
            ],
            'pronunciation' => [
                'Le « or » de work [wɝk] : ni « o » ni « eur » français, une voyelle neutre suivie d\'un « r » bien prononcé.',
                'Accent tonique : acCOUNtant, COLleague, comPAny.',
            ],
            'spoken' => [
                '« What do you do? » veut dire « Que faites-vous dans la vie ? », pas « Que fais-tu maintenant ? ».',
                "« I run my own business » = j'ai ma propre affaire.",
            ],
            'pitfalls' => [
                "N'oubliez pas a/an : « I am teacher » ✗ → « I'm a teacher » ✓.",
                'Faux ami : a library est une bibliothèque ; la librairie se dit bookstore.',
            ],
            'situations' => [
                ['title' => 'Salon professionnel à Lomé', 'description' => 'À la Foire internationale de Lomé, présentez votre métier et votre entreprise à un visiteur étranger anglophone.', 'tags' => ['business', 'work']],
                ['title' => 'Le centre de santé', 'description' => 'Un médecin anglophone en mission dans un centre de santé de Kara vous demande quel est votre rôle.', 'tags' => ['health', 'work']],
                ['title' => 'Conseiller d\'orientation', 'description' => 'Un étudiant de l\'université de Kara parle de ses études et du métier qu\'il veut faire plus tard.', 'tags' => ['studies', 'education']],
            ],
        ],

        // 10
        [
            'title' => "Les vêtements, les couleurs et le temps qu'il fait",
            'objectives' => [
                'décrire des vêtements et des couleurs',
                'acheter un vêtement en demandant la taille',
                "parler du temps qu'il fait et des saisons",
            ],
            'vocabulary' => [
                'clothes' => 'vêtements',
                'shirt' => 'chemise',
                'dress' => 'robe',
                'pants' => 'pantalon (en anglais britannique, on dit aussi « trousers »)',
                'shoes' => 'chaussures',
                'fabric / wax print' => 'tissu / pagne wax',
                'size' => 'taille',
                'to try on' => 'essayer (un vêtement)',
                'red / blue / green' => 'rouge / bleu / vert',
                'yellow / white / black' => 'jaune / blanc / noir',
                'weather' => 'temps (météo)',
                'hot' => 'chaud',
                'sunny' => 'ensoleillé',
                "it's raining" => 'il pleut',
                'rainy season' => 'saison des pluies',
                'dry season' => 'saison sèche',
                'wind' => 'vent',
                'cold' => 'froid',
            ],
            'grammar' => [
                "Place de l'adjectif : avant le nom et invariable — « a blue dress », « red shoes » (pas « shoes reds »).",
                "Le présent continu pour ce qui se passe maintenant : « It's raining », « I'm wearing a wax shirt today ».",
            ],
            'functions' => [
                "Décrire une tenue : « She's wearing a long green dress and white shoes. »",
                'En boutique : « Can I try it on? Do you have it in a bigger size? »',
                "Parler météo : « It's very hot today. — Yes, the rainy season is late this year. »",
            ],
            'pronunciation' => [
                'Clothes [kloʊðz] : une seule syllabe, le « th » est presque avalé.',
                'Le « w » de weather, white, wind : lèvres arrondies comme pour « oui ».',
            ],
            'spoken' => [
                "« It's boiling! » = il fait une chaleur terrible ; « It's pouring » = il pleut à verse.",
                '« That suits you! » = ça vous va bien.',
            ],
            'pitfalls' => [
                "Ne confondez pas weather (le temps qu'il fait) et time (le temps qui passe).",
                'Clothes est toujours pluriel ; un vêtement = a piece of clothing.',
            ],
            'situations' => [
                ['title' => 'Chez la couturière', 'description' => 'Une cliente anglophone de passage veut faire coudre une robe en pagne dans votre atelier de Lomé : couleurs, tissu, taille et date de livraison.', 'tags' => ['business', 'market']],
                ['title' => 'Valise pour l\'étranger', 'description' => 'Un ami vous demande quels vêtements prendre pour un voyage à l\'étranger en pleine saison des pluies.', 'tags' => ['travel', 'conversation']],
                ['title' => 'Tenue pour l\'entretien', 'description' => 'Vous préparez un entretien d\'embauche dans une entreprise du port de Lomé : discutez de la tenue avec un proche.', 'tags' => ['work', 'family']],
            ],
        ],

        // 11
        [
            'title' => 'La santé et le corps',
            'objectives' => [
                'dire où vous avez mal et depuis quand',
                "comprendre un conseil simple d'un médecin ou d'un pharmacien",
                'acheter un médicament à la pharmacie',
            ],
            'vocabulary' => [
                'head' => 'tête',
                'stomach' => 'ventre, estomac',
                'back' => 'dos',
                'throat' => 'gorge',
                'tooth / teeth' => 'dent / dents',
                'it hurts' => 'ça fait mal',
                'headache' => 'mal de tête',
                'fever' => 'fièvre',
                'malaria' => 'paludisme',
                'cough' => 'toux',
                'tired' => 'fatigué',
                'sick / ill' => 'malade',
                'medicine' => 'médicament',
                'tablets' => 'comprimés',
                'twice a day' => 'deux fois par jour',
                'appointment' => 'rendez-vous (médical)',
                'to rest' => 'se reposer',
                'pharmacist' => 'pharmacien',
            ],
            'grammar' => [
                'Have + mal : « I have a headache », « I have a fever » ; my + partie du corps : « My back hurts ».',
                "Should pour un conseil : « You should rest », « You shouldn't eat spicy food ».",
                "For + durée : « I've had a fever for two days » (expression figée à apprendre telle quelle).",
            ],
            'functions' => [
                'Expliquer ses symptômes : « I have a headache and a fever. It started yesterday. »',
                'À la pharmacie : « Do you have something for a cough? How many times a day? »',
                "Prendre rendez-vous : « I'd like to make an appointment with the doctor, please. »",
            ],
            'pronunciation' => [
                'Stomach [ˈstʌmək] : le « ch » se dit « k ». Headache : « ache » se dit [eɪk].',
                'Le « th » de throat, teeth, mouth.',
            ],
            'spoken' => [
                "« I'm not feeling well » = je ne me sens pas bien (plus naturel que « I am sick »).",
                '« Get well soon! » = bon rétablissement.',
            ],
            'pitfalls' => [
                '« I have mal at my head » ✗ → « I have a headache » ou « My head hurts » ✓.',
                'Faux ami : un « médecin » est a doctor ; « medicine » désigne le médicament (ou la médecine).',
            ],
            'situations' => [
                ['title' => 'À la pharmacie à Lomé', 'description' => 'Un visiteur anglophone a de la fièvre. Aidez-le à expliquer ses symptômes au pharmacien et à comprendre la posologie.', 'tags' => ['health', 'travel']],
                ['title' => 'Appeler son chef', 'description' => 'Vous êtes malade : appelez votre responsable anglophone, à son bureau de Lomé, pour dire que vous ne pouvez pas venir travailler.', 'tags' => ['work', 'phone', 'health']],
                ['title' => 'Enfant malade', 'description' => 'Vous emmenez votre enfant chez un médecin anglophone d\'une clinique de Lomé : expliquez où il a mal et depuis quand.', 'tags' => ['family', 'health']],
            ],
        ],

        // 12
        [
            'title' => 'Mes capacités et mes projets',
            'objectives' => [
                'dire ce que vous savez faire et ne savez pas faire',
                'parler de vos projets pour le week-end ou les vacances',
                "inviter quelqu'un et accepter ou refuser une invitation",
            ],
            'vocabulary' => [
                "can / can't" => 'savoir, pouvoir / ne pas pouvoir',
                'to swim' => 'nager',
                'to drive' => 'conduire',
                'to dance' => 'danser',
                'to sing' => 'chanter',
                'to play soccer' => 'jouer au football',
                'to use a computer' => 'utiliser un ordinateur',
                'free time' => 'temps libre',
                'plan' => 'projet',
                'tomorrow' => 'demain',
                'next week' => 'la semaine prochaine',
                'vacation' => 'vacances (en anglais britannique, on dit aussi « holiday »)',
                'to visit' => 'rendre visite, visiter',
                'party' => 'fête',
                'invitation' => 'invitation',
                'maybe' => 'peut-être',
                'of course' => 'bien sûr',
            ],
            'grammar' => [
                "Can + verbe sans to : « I can drive », « Can you swim? — Yes, I can / No, I can't ».",
                "Be going to pour un projet : « I'm going to visit my aunt on Saturday ».",
                "Le présent continu pour un rendez-vous prévu : « I'm meeting a client tomorrow ».",
            ],
            'functions' => [
                'Parler de ses capacités : « I can speak French and Ewe, and I can use Excel. »',
                'Inviter : « Would you like to come to my party on Sunday? »',
                "Accepter ou refuser poliment : « I'd love to! » / « Sorry, I can't. I'm working. »",
            ],
            'pronunciation' => [
                "Can [kən] est faible dans la phrase, can't [kɑːnt] est long et accentué : c'est ainsi qu'on les distingue.",
                "Going to se réduit souvent en gonna à l'oral rapide.",
            ],
            'spoken' => [
                "« I'm gonna call you » = I'm going to call you (oral uniquement, jamais à l'écrit formel).",
                '« Sounds great! » = super, ça me va !',
            ],
            'pitfalls' => [
                'Pas de to après can : « I can to drive » ✗ → « I can drive » ✓.',
                "Faux ami : « to visit » s'utilise pour une ville ou une personne ; « visiter un appartement » = to look at an apartment.",
            ],
            'situations' => [
                ['title' => 'Compétences pour un emploi', 'description' => 'Une ONG installée à Lomé vous demande ce que vous savez faire : langues, informatique, permis de conduire.', 'tags' => ['work', 'tech']],
                ['title' => 'Invitation au mariage', 'description' => 'Invitez un collègue étranger de votre entreprise internationale au mariage de votre frère à Aného ; il accepte ou s\'excuse.', 'tags' => ['family', 'conversation']],
                ['title' => 'Projet de voyage à l\'étranger', 'description' => 'Vous racontez à un ami ce que vous allez faire pendant votre premier voyage à l\'étranger.', 'tags' => ['travel', 'conversation']],
            ],
        ],

    ],

];
