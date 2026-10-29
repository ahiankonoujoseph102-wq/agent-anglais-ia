<?php

/*
|--------------------------------------------------------------------------
| Catalogue des vidéos du Club des abonnés (App\Support\Videos\ClubCatalog)
|--------------------------------------------------------------------------
|
| Ce que Teacher Joe présente aux abonnés payants, un élément par vidéo : expressions du quotidien (mardi), sons
| difficiles pour les francophones (mercredi), situations de mini-dialogue (jeudi). Un élément déjà présenté n'est
| pas repris avant config('videos.club.repeat_weeks') semaines (historique : les vidéos du Club elles-mêmes).
| Anglais AMÉRICAIN, situations au Togo ou dans un cadre international SANS nommer d'autre pays. Ajouter à la fin ;
| ne jamais changer une clé déjà utilisée.
|
*/

return [

    'expressions' => [
        ['key' => 'how-is-it-going', 'en' => "How's it going?", 'fr' => 'Comment ça va ? (entre amis, collègues)'],
        ['key' => 'no-worries', 'en' => 'No worries.', 'fr' => 'Pas de souci.'],
        ['key' => 'i-am-on-my-way', 'en' => "I'm on my way.", 'fr' => 'Je suis en route.'],
        ['key' => 'sounds-good', 'en' => 'Sounds good!', 'fr' => 'Ça me va ! / D\'accord !'],
        ['key' => 'take-your-time', 'en' => 'Take your time.', 'fr' => 'Prenez votre temps.'],
        ['key' => 'could-you-say-that-again', 'en' => 'Could you say that again, please?', 'fr' => 'Vous pouvez répéter, s\'il vous plaît ?'],
        ['key' => 'what-do-you-mean', 'en' => 'What do you mean?', 'fr' => 'Qu\'est-ce que vous voulez dire ?'],
        ['key' => 'i-see', 'en' => 'I see.', 'fr' => 'Je vois. / Je comprends.'],
        ['key' => 'it-depends', 'en' => 'It depends.', 'fr' => 'Ça dépend.'],
        ['key' => 'i-am-not-sure', 'en' => "I'm not sure.", 'fr' => 'Je ne suis pas sûr(e).'],
        ['key' => 'let-me-think', 'en' => 'Let me think.', 'fr' => 'Laissez-moi réfléchir.'],
        ['key' => 'good-luck', 'en' => 'Good luck!', 'fr' => 'Bonne chance !'],
        ['key' => 'have-a-good-day', 'en' => 'Have a good day!', 'fr' => 'Bonne journée !'],
        ['key' => 'see-you-later', 'en' => 'See you later!', 'fr' => 'À plus tard !'],
        ['key' => 'my-pleasure', 'en' => 'My pleasure.', 'fr' => 'Avec plaisir. (réponse à « merci »)'],
        ['key' => 'you-are-welcome', 'en' => "You're welcome.", 'fr' => 'De rien.'],
        ['key' => 'excuse-me', 'en' => 'Excuse me.', 'fr' => 'Excusez-moi. (pour attirer l\'attention)'],
        ['key' => 'i-am-sorry-i-am-late', 'en' => "I'm sorry I'm late.", 'fr' => 'Désolé(e) pour le retard.'],
        ['key' => 'how-much-is-it', 'en' => 'How much is it?', 'fr' => 'C\'est combien ?'],
        ['key' => 'can-i-help-you', 'en' => 'Can I help you?', 'fr' => 'Je peux vous aider ?'],
        ['key' => 'i-would-like', 'en' => "I'd like a coffee, please.", 'fr' => 'Je voudrais un café, s\'il vous plaît.'],
        ['key' => 'what-time-is-it', 'en' => 'What time is it?', 'fr' => 'Quelle heure est-il ?'],
        ['key' => 'it-is-up-to-you', 'en' => "It's up to you.", 'fr' => 'C\'est vous qui voyez.'],
        ['key' => 'never-mind', 'en' => 'Never mind.', 'fr' => 'Laissez tomber. / Ce n\'est pas grave.'],
        ['key' => 'i-got-it', 'en' => 'I got it.', 'fr' => 'J\'ai compris.'],
        ['key' => 'hang-on', 'en' => 'Hang on a second.', 'fr' => 'Attendez une seconde.'],
        ['key' => 'what-about-you', 'en' => 'What about you?', 'fr' => 'Et vous ?'],
        ['key' => 'nice-to-meet-you', 'en' => 'Nice to meet you.', 'fr' => 'Enchanté(e).'],
        ['key' => 'keep-in-touch', 'en' => "Let's keep in touch.", 'fr' => 'Restons en contact.'],
        ['key' => 'i-am-looking-forward', 'en' => "I'm looking forward to it.", 'fr' => 'J\'ai hâte.'],
        ['key' => 'that-makes-sense', 'en' => 'That makes sense.', 'fr' => 'C\'est logique. / Je comprends.'],
        ['key' => 'go-ahead', 'en' => 'Go ahead.', 'fr' => 'Allez-y.'],
        ['key' => 'it-is-not-a-big-deal', 'en' => "It's not a big deal.", 'fr' => 'Ce n\'est pas grave.'],
        ['key' => 'i-agree', 'en' => 'I agree with you.', 'fr' => 'Je suis d\'accord avec vous.'],
        ['key' => 'how-was-your-day', 'en' => 'How was your day?', 'fr' => 'Comment s\'est passée votre journée ?'],
        ['key' => 'congratulations', 'en' => 'Congratulations!', 'fr' => 'Félicitations !'],
    ],

    'sounds' => [
        ['key' => 'th-voiceless', 'label' => 'le « th » doux de « think »', 'words' => ['think', 'three', 'thank you']],
        ['key' => 'th-voiced', 'label' => 'le « th » vibré de « this »', 'words' => ['this', 'mother', 'the']],
        ['key' => 'h-aspirated', 'label' => 'le « h » qu\'on entend, comme dans « house »', 'words' => ['house', 'happy', 'hello']],
        ['key' => 'short-long-i', 'label' => 'le « i » court de « ship » et le « i » long de « sheep »', 'words' => ['ship', 'sheep', 'live', 'leave']],
        ['key' => 'ed-endings', 'label' => 'la fin « -ed » du passé : « t », « d » ou « id »', 'words' => ['worked', 'played', 'wanted']],
        ['key' => 'final-s', 'label' => 'le « s » final qu\'il faut prononcer', 'words' => ['works', 'books', 'cars']],
        ['key' => 'american-r', 'label' => 'le « r » américain', 'words' => ['car', 'water', 'red']],
        ['key' => 'w-sound', 'label' => 'le « w » de « water »', 'words' => ['water', 'want', 'week']],
        ['key' => 'ng-ending', 'label' => 'la fin « -ing »', 'words' => ['morning', 'thing', 'working']],
        ['key' => 'schwa', 'label' => 'le son faible « e » des syllabes non accentuées', 'words' => ['banana', 'about', 'teacher']],
        ['key' => 'word-stress', 'label' => 'l\'accent tonique sur la bonne syllabe', 'words' => ['photograph', 'hotel', 'computer']],
        ['key' => 'full-fool', 'label' => 'le « ou » court de « full » et le « ou » long de « fool »', 'words' => ['full', 'fool', 'look', 'Luke']],
        ['key' => 'v-and-b', 'label' => 'le « v » et le « b » bien distincts', 'words' => ['very', 'berry', 'vote', 'boat']],
        ['key' => 'silent-letters', 'label' => 'les lettres muettes', 'words' => ['know', 'listen', 'Wednesday']],
        ['key' => 'can-cant', 'label' => '« can » et « can\'t » sans les confondre', 'words' => ['I can', 'I can\'t']],
        ['key' => 'cat-cut', 'label' => 'le « a » de « cat » et le « u » de « cut »', 'words' => ['cat', 'cut', 'bag', 'bug']],
    ],

    'situations' => [
        ['key' => 'restaurant-order', 'label' => 'Commander au restaurant'],
        ['key' => 'phone-call-office', 'label' => 'Répondre au téléphone au bureau'],
        ['key' => 'job-interview-intro', 'label' => 'Se présenter à un entretien d\'embauche'],
        ['key' => 'market-price', 'label' => 'Demander un prix et négocier au Grand Marché de Lomé'],
        ['key' => 'taxi-direction', 'label' => 'Indiquer sa destination à un chauffeur de taxi'],
        ['key' => 'meeting-colleague', 'label' => 'Rencontrer un nouveau collègue'],
        ['key' => 'hotel-check-in', 'label' => 'Arriver à l\'hôtel'],
        ['key' => 'airport-check-in', 'label' => 'S\'enregistrer à l\'aéroport'],
        ['key' => 'asking-directions', 'label' => 'Demander son chemin dans la rue'],
        ['key' => 'doctor-appointment', 'label' => 'Prendre un rendez-vous chez le médecin'],
        ['key' => 'shop-return', 'label' => 'Rapporter un article dans un magasin'],
        ['key' => 'video-call-start', 'label' => 'Commencer une réunion en visioconférence'],
        ['key' => 'welcome-visitor', 'label' => 'Accueillir un visiteur anglophone à Lomé'],
        ['key' => 'bank-account', 'label' => 'Poser une question à la banque'],
        ['key' => 'university-class', 'label' => 'Parler avec un camarade à l\'université de Lomé'],
        ['key' => 'customer-complaint', 'label' => 'Répondre à un client mécontent'],
        ['key' => 'inviting-a-friend', 'label' => 'Inviter un ami à sortir'],
        ['key' => 'buying-phone-credit', 'label' => 'Acheter du crédit de téléphone'],
        ['key' => 'port-delivery', 'label' => 'Suivre une livraison au port autonome de Lomé'],
        ['key' => 'small-talk-weather', 'label' => 'Parler de la météo pour briser la glace'],
    ],

];
