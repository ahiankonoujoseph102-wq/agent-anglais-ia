<?php

/*
| VIDÉOS DE PRÉSENTATION de Talkami par Teacher Joe (App\Support\Videos\PresentationScripts) : une grande vidéo et six
| courtes, une par étape. Teacher Joe parle face caméra (plein écran au début et à la fin), en médaillon pendant que
| l'écran du site défile (enregistré sur un site LOCAL de démonstration par tools/presentation, jamais en production).
|
| Chaque phrase :
| - « text » : ce qui est dit ET sous-titré. *mots en orange* dans les sous-titres. Repères remplis depuis la
|   configuration (jamais un prix, une durée ni un nom écrit à la main) : :teacher, :platform, :site (adresse du site
|   réglée dans la page « Vidéos », dite « … point … »), :name_{formule}, :price_{formule}, :days_{formule},
|   :minutes_{formule}, :assessment_minutes, :assessment_total, :questions_max, :warning_minutes, :course_minutes,
|   :course_photos, :pdf_pages, :part_pages, :units, :address (bureau, Réglages), :pay_countries (« au Togo, … et en
|   Côte d'Ivoire »), :group_link (texte de publication seulement : lien du groupe WhatsApp). Les nombres sont dits en
|   toutes lettres, écrits en chiffres ; {{dit|écrit}} pour un autre cas (« dix-neuf heures » / « 19 heures »).
| - « card » : lignes écrites en gros dans un bandeau, au-dessus de Teacher Joe en plein écran.
| - « voice_style » (par vidéo) : ton de la voix, à la place du ton commun des présentations.
| - « shot » : « full » (Teacher Joe en plein écran) ou « screen » (écran du téléphone, Teacher Joe en médaillon).
| - « scene » : ce que l'outil d'enregistrement fait à l'écran pendant la phrase (tools/presentation/scenes.mjs).
| - « chapter » : titre court affiché à côté du téléphone, à partir de cette phrase.
| - « medallion » : false pour cacher le médaillon (écrans où Teacher Joe est déjà visible dans la page).
|
| Règles (vérifiées par tests/Feature/Videos/PresentationScriptsTest.php) : uniquement des faits du code ; jamais
| « accès immédiat », un délai de vérification chiffré, un remboursement, des rappels automatiques, un PDF de la
| séance du jour, un paiement par carte, une fausse urgence, un faux chiffre ou avis, une promesse de résultat, ni un
| autre pays que ceux du site. Changer un texte refait la voix de cette vidéo (empreinte du script).
*/

return [

    'talkami' => [
        'title' => ':platform en 5 minutes',
        'caption' => 'Comment marche :platform, de l\'inscription à l\'attestation : je vous montre tout, écran par écran.',
        'hashtags' => ['#anglais', '#apprendrelanglais', '#parleranglais', '#togo', '#lome'],
        'segments' => [
            ['shot' => 'full', 'scene' => 'prepare.home', 'text' => 'Bonjour ! Je suis :teacher, le professeur d\'anglais de *:platform*.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => ':platform vous aide à *parler anglais*, depuis votre téléphone, avec moi, à l\'oral.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'J\'ai été conçu et programmé par Joseph Ahiankonou, et je m\'appuie sur une *intelligence artificielle*.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'En cinq minutes, je vous montre tout le site, étape par étape.'],
            ['shot' => 'screen', 'scene' => 'home.audiences', 'chapter' => 'Pour qui ?', 'text' => ':platform est pour vous si vous voulez enfin oser parler anglais : *élèves, étudiants, parents*, et tous ceux qui *travaillent*.'],
            ['shot' => 'screen', 'scene' => 'home.course', 'text' => 'J\'enseigne l\'*anglais américain*, compris partout dans le monde. Et j\'explique *en français* quand c\'est nécessaire.'],
            ['shot' => 'screen', 'scene' => 'home.start', 'chapter' => 'Créer un compte', 'text' => 'Tout commence par un compte. Touchez « *Passer mon évaluation gratuite* ».'],
            ['shot' => 'screen', 'scene' => 'register.fill', 'text' => 'Choisissez votre *pays*, écrivez votre *nom* et votre *numéro de téléphone*, puis un *mot de passe* d\'au moins huit caractères.'],
            ['shot' => 'screen', 'scene' => 'register.submit', 'text' => 'L\'adresse e-mail est *facultative*. Touchez « *Créer mon compte* ».'],
            ['shot' => 'screen', 'scene' => 'dashboard.assessment', 'chapter' => 'Évaluation gratuite', 'text' => 'Ensuite, l\'*évaluation orale gratuite* : *:assessment_minutes minutes* avec moi, une seule fois par compte.'],
            ['shot' => 'screen', 'scene' => 'assessment.intro', 'text' => 'Touchez « *Commencer* », puis « *Autoriser* » quand le téléphone demande le micro.'],
            ['shot' => 'screen', 'scene' => 'assessment.talk', 'medallion' => false, 'text' => 'Je vous pose des questions en anglais, à votre niveau. Si vous ne comprenez pas, dites-le-moi, *même en français*.'],
            ['shot' => 'screen', 'scene' => 'assessment.phrases', 'medallion' => false, 'text' => 'Pas de stress : vous avez *le droit de vous tromper*. C\'est en parlant qu\'on progresse.'],
            ['shot' => 'screen', 'scene' => 'assessment.verdict', 'medallion' => false, 'text' => 'Je vous annonce votre *niveau*, de *A1 à C2*, puis je vous montre une première phrase à répéter.'],
            ['shot' => 'screen', 'scene' => 'assessment.questions', 'medallion' => false, 'text' => 'Après le verdict, vous pouvez me poser jusqu\'à *:questions_max questions*, en français. L\'évaluation dure *:assessment_total minutes au plus* en tout.'],
            ['shot' => 'screen', 'scene' => 'result.level', 'chapter' => 'Votre niveau', 'text' => 'Votre niveau et mon avis restent sur la *page de résultat*. Vous pouvez aussi choisir un autre niveau.'],
            ['shot' => 'screen', 'scene' => 'result.plans', 'chapter' => 'Les formules', 'text' => 'Ensuite, choisissez votre *formule*.'],
            ['shot' => 'screen', 'scene' => 'plans.semaine', 'text' => '*:name_semaine* : *:price_semaine* pour :days_semaine jours, :minutes_semaine minutes par jour.'],
            ['shot' => 'screen', 'scene' => 'plans.essentiel', 'text' => '*:name_essentiel* : *:price_essentiel* pour :days_essentiel jours, :minutes_essentiel minutes par jour.'],
            ['shot' => 'screen', 'scene' => 'plans.intensif', 'text' => '*:name_intensif* : *:price_intensif* pour :days_intensif jours, :minutes_intensif minutes par jour.'],
            ['shot' => 'screen', 'scene' => 'plans.shared', 'text' => 'Ce temps se partage entre la *séance orale* et « *Mes cours* ».'],
            ['shot' => 'screen', 'scene' => 'plans.choose', 'chapter' => 'Payer', 'text' => 'Le paiement se fait par *Mobile Money*, depuis votre téléphone.'],
            ['shot' => 'screen', 'scene' => 'pay.method', 'text' => 'Votre pays est déjà choisi. Touchez votre *moyen de paiement*, par exemple Mixx by Yas.'],
            ['shot' => 'screen', 'scene' => 'pay.step1', 'text' => '*Étape un* : copiez le numéro. Vérifiez le *nom du titulaire*.'],
            ['shot' => 'screen', 'scene' => 'pay.step2', 'text' => '*Étape deux* : envoyez le *montant exact* avec votre application, et faites une *capture d\'écran* de la confirmation.'],
            ['shot' => 'screen', 'scene' => 'pay.step3', 'text' => '*Étape trois* : revenez ici et touchez « *J\'ai payé, j\'envoie ma capture* ».'],
            ['shot' => 'screen', 'scene' => 'pay.send', 'text' => 'Choisissez la capture, puis touchez « *Envoyer ma capture* ».'],
            ['shot' => 'screen', 'scene' => 'pay.outcomes', 'text' => 'Si votre capture est *bien lue*, vous pouvez *commencer tout de suite*. Sinon, *notre équipe vérifie*.'],
            ['shot' => 'screen', 'scene' => 'pay.abroad', 'text' => 'Dans un autre pays ? Écrivez-nous sur *WhatsApp* : nous vous expliquons comment vous abonner.'],
            ['shot' => 'screen', 'scene' => 'welcome.page', 'chapter' => 'Après le paiement', 'text' => 'Après le paiement, je vous souhaite la *bienvenue* dans un message vocal.'],
            ['shot' => 'screen', 'scene' => 'questionnaire.all', 'text' => 'Puis vous répondez à *quatre petits écrans* : votre objectif, votre métier, vos jours de travail et ce qui vous semble difficile.'],
            ['shot' => 'screen', 'scene' => 'program.show', 'chapter' => 'Votre programme', 'text' => 'Le site construit alors votre *programme personnel*, à partir du programme officiel de votre niveau : *:units unités*.'],
            ['shot' => 'screen', 'scene' => 'program.goals', 'text' => 'Vos objectifs changent ? Touchez « *Modifier mes objectifs* ».'],
            ['shot' => 'screen', 'scene' => 'lesson.board', 'chapter' => 'La séance du jour', 'medallion' => false, 'text' => 'Chaque jour, votre *séance du jour* vous attend. J\'enseigne d\'abord la leçon *au tableau*. Puis vous pratiquez, vous parlez, et on fait le *bilan*.'],
            ['shot' => 'screen', 'scene' => 'lesson.timer', 'medallion' => false, 'text' => 'Je vous préviens *:warning_minutes minutes avant la fin*, et le site arrête la séance à la fin de votre temps.'],
            ['shot' => 'screen', 'scene' => 'lesson.cut', 'medallion' => false, 'text' => 'Si la connexion est coupée, reprenez dès que possible : *le temps de la coupure est mis en pause*.'],
            ['shot' => 'screen', 'scene' => 'lesson.record', 'medallion' => false, 'text' => 'Vous pouvez même *enregistrer votre séance* en vidéo. La vidéo reste sur votre téléphone.'],
            ['shot' => 'screen', 'scene' => 'courses.new', 'chapter' => 'Mes cours', 'text' => 'Avec « *Mes cours* », envoyez votre leçon d\'anglais de l\'école : jusqu\'à *:course_photos photos*, ou *un PDF*.'],
            ['shot' => 'screen', 'scene' => 'courses.time', 'text' => '*Uniquement pour les cours d\'anglais.* Une séance dure *:course_minutes minutes au plus*, prises sur votre temps du jour.'],
            ['shot' => 'screen', 'scene' => 'courses.lesson', 'text' => 'Je vous l\'explique *en français*. Après une séance assez longue, un *document de révision* se prépare.'],
            ['shot' => 'screen', 'scene' => 'history.show', 'chapter' => 'Mes séances', 'text' => 'Dans « *Mes séances* », retrouvez vos *résumés*, vos *devoirs* et vos *erreurs à revoir*.'],
            ['shot' => 'screen', 'scene' => 'attestation.show', 'chapter' => 'L\'attestation', 'text' => 'À la fin des :units unités, vous passez un *bilan oral*. S\'il est réussi, vous recevez une *attestation de niveau*, vérifiable en ligne.'],
            ['shot' => 'screen', 'scene' => 'help.whatsapp', 'chapter' => 'Besoin d\'aide ?', 'text' => 'Une question ? Touchez « *Aide* », puis « *Écrire sur WhatsApp* ». Mot de passe oublié ? Même chose.'],
            ['shot' => 'screen', 'scene' => 'install.page', 'text' => 'Et *installez l\'application* : :platform sera sur l\'écran d\'accueil de votre téléphone.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'Alors, prêt à parler anglais ? Commencez par l\'*évaluation orale gratuite* de :assessment_minutes minutes.'],
            ['shot' => 'full', 'scene' => 'wait', 'closing' => true, 'text' => 'Rendez-vous sur *:site*. On se retrouve de l\'autre côté !'],
        ],
    ],

    'compte' => [
        'title' => 'Créer votre compte',
        'caption' => 'Créer votre compte :platform prend une minute. Ensuite, l\'évaluation orale gratuite vous attend.',
        'hashtags' => ['#anglais', '#apprendrelanglais', '#togo'],
        'segments' => [
            ['shot' => 'full', 'scene' => 'prepare.home', 'text' => 'Créer votre compte :platform prend une minute. Je vous montre.'],
            ['shot' => 'screen', 'scene' => 'home.start', 'chapter' => 'Créer un compte', 'text' => 'Sur *:site*, touchez « *Passer mon évaluation gratuite* ».'],
            ['shot' => 'screen', 'scene' => 'register.country', 'text' => 'Choisissez votre *pays* dans la liste.'],
            ['shot' => 'screen', 'scene' => 'register.identity', 'text' => 'Écrivez votre *nom complet*, puis votre *numéro de téléphone*.'],
            ['shot' => 'screen', 'scene' => 'register.email', 'text' => 'L\'adresse e-mail est *facultative* : elle sert à recevoir vos documents de révision.'],
            ['shot' => 'screen', 'scene' => 'register.password', 'text' => 'Choisissez un *mot de passe* d\'au moins huit caractères.'],
            ['shot' => 'screen', 'scene' => 'register.submit', 'text' => 'Touchez « *Créer mon compte* ».'],
            ['shot' => 'screen', 'scene' => 'dashboard.next', 'chapter' => 'Mon espace', 'text' => 'Votre compte est prêt. Votre *prochaine étape* est déjà là : l\'évaluation orale gratuite.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'Gardez bien votre numéro et votre mot de passe : ils vous servent à *vous connecter*.'],
            ['shot' => 'full', 'scene' => 'wait', 'closing' => true, 'text' => 'Rendez-vous sur *:site*.'],
        ],
    ],

    'evaluation' => [
        'title' => 'Votre évaluation orale gratuite',
        'caption' => ':assessment_minutes minutes à l\'oral avec :teacher pour trouver votre niveau d\'anglais, de A1 à C2. Gratuit, une fois par compte.',
        'hashtags' => ['#anglais', '#niveaudanglais', '#apprendrelanglais'],
        'segments' => [
            ['shot' => 'full', 'scene' => 'prepare.dashboard', 'text' => 'Votre évaluation orale est *gratuite* : *:assessment_minutes minutes* avec moi, une seule fois par compte.'],
            ['shot' => 'screen', 'scene' => 'dashboard.assessment', 'chapter' => 'Évaluation gratuite', 'text' => 'Dans « Mon espace », touchez « *Commencer mon évaluation gratuite* ».'],
            ['shot' => 'screen', 'scene' => 'assessment.calm', 'text' => 'Installez-vous au calme. Touchez « *Commencer* ».'],
            ['shot' => 'screen', 'scene' => 'assessment.mic', 'text' => 'Quand le téléphone demande le micro, touchez « *Autoriser* ».'],
            ['shot' => 'screen', 'scene' => 'assessment.turns', 'medallion' => false, 'text' => 'Je parle, puis l\'écran affiche « *À vous de parler* » : c\'est votre tour.'],
            ['shot' => 'screen', 'scene' => 'assessment.phrases', 'medallion' => false, 'text' => 'Répondez comme vous pouvez. Si vous ne comprenez pas, dites-le, *même en français*.'],
            ['shot' => 'screen', 'scene' => 'assessment.verdict', 'medallion' => false, 'text' => 'Je vous annonce votre *niveau*, de *A1 à C2*.'],
            ['shot' => 'screen', 'scene' => 'assessment.discovery', 'medallion' => false, 'text' => 'Puis je vous montre une première phrase à répéter, et vous pouvez me poser vos *questions en français*.'],
            ['shot' => 'screen', 'scene' => 'assessment.total', 'medallion' => false, 'text' => 'En tout, *:assessment_total minutes au plus*.'],
            ['shot' => 'screen', 'scene' => 'result.level', 'chapter' => 'Votre niveau', 'text' => 'Votre niveau et mon avis restent sur la *page de résultat*.'],
            ['shot' => 'full', 'scene' => 'wait', 'closing' => true, 'text' => 'Faites votre évaluation gratuite sur *:site*.'],
        ],
    ],

    'formules' => [
        'title' => 'Choisir votre formule',
        'caption' => 'Trois formules, les mêmes prix dans tous les pays : :name_semaine, :name_essentiel et :name_intensif.',
        'hashtags' => ['#anglais', '#apprendrelanglais', '#togo'],
        'segments' => [
            ['shot' => 'full', 'scene' => 'prepare.plans', 'text' => 'Il y a *trois formules*. Je vous les présente.'],
            ['shot' => 'screen', 'scene' => 'plans.semaine', 'chapter' => 'Les formules', 'text' => '*:name_semaine* : *:price_semaine* pour :days_semaine jours, :minutes_semaine minutes par jour. Pour essayer.'],
            ['shot' => 'screen', 'scene' => 'plans.essentiel', 'text' => '*:name_essentiel* : *:price_essentiel* pour :days_essentiel jours, :minutes_essentiel minutes par jour.'],
            ['shot' => 'screen', 'scene' => 'plans.intensif', 'text' => '*:name_intensif* : *:price_intensif* pour :days_intensif jours, :minutes_intensif minutes par jour.'],
            ['shot' => 'screen', 'scene' => 'plans.shared', 'text' => 'Chaque jour, ce temps se partage entre la *séance orale* et « *Mes cours* ».'],
            ['shot' => 'screen', 'scene' => 'plans.midnight', 'text' => 'Le temps repart à zéro *chaque nuit à minuit*, heure de Lomé.'],
            ['shot' => 'screen', 'scene' => 'plans.same', 'text' => '*Mêmes formules et mêmes prix* dans tous les pays.'],
            ['shot' => 'screen', 'scene' => 'plans.pick', 'text' => 'Touchez « *Choisir* » sous la formule qui vous convient.'],
            ['shot' => 'full', 'scene' => 'wait', 'closing' => true, 'text' => 'Pas encore sûr de votre niveau ? Commencez par l\'*évaluation gratuite*, sur *:site*.'],
        ],
    ],

    'paiement' => [
        'title' => 'Payer en 3 étapes',
        'caption' => 'Payer votre formule par Mobile Money en 3 étapes : copiez le numéro, envoyez le montant, envoyez la capture.',
        'hashtags' => ['#anglais', '#mobilemoney', '#togo'],
        'segments' => [
            ['shot' => 'full', 'scene' => 'prepare.pay', 'text' => 'Payer prend *trois étapes*. Regardez.'],
            ['shot' => 'screen', 'scene' => 'pay.method', 'chapter' => 'Payer', 'text' => 'Votre pays est déjà choisi. Touchez votre *moyen de paiement*.'],
            ['shot' => 'screen', 'scene' => 'pay.countries', 'text' => 'Au Togo : *Mixx by Yas* ou *Flooz*. Chaque pays du site a ses moyens.'],
            ['shot' => 'screen', 'scene' => 'pay.step1', 'text' => '*Un* : copiez le numéro, et vérifiez le nom.'],
            ['shot' => 'screen', 'scene' => 'pay.step2', 'text' => '*Deux* : dans votre application, envoyez le *montant exact*, puis faites une capture de la confirmation.'],
            ['shot' => 'screen', 'scene' => 'pay.step3', 'text' => '*Trois* : revenez ici et touchez « *J\'ai payé, j\'envoie ma capture* ».'],
            ['shot' => 'screen', 'scene' => 'pay.send', 'text' => 'Choisissez la capture, puis touchez « *Envoyer ma capture* ».'],
            ['shot' => 'screen', 'scene' => 'pay.outcomes', 'text' => 'Si votre capture est *bien lue*, vous pouvez *commencer tout de suite*. Sinon, *notre équipe vérifie*.'],
            ['shot' => 'full', 'scene' => 'wait', 'closing' => true, 'text' => 'Une question sur le paiement ? Écrivez-nous sur *WhatsApp*, depuis la page « Aide ».'],
        ],
    ],

    'seance' => [
        'title' => 'Votre séance du jour',
        'caption' => 'Une séance avec :teacher : la leçon au tableau, la pratique, la conversation, puis le bilan.',
        'hashtags' => ['#anglais', '#parleranglais', '#apprendrelanglais'],
        'segments' => [
            ['shot' => 'full', 'scene' => 'prepare.dashboard', 'text' => 'Votre séance du jour, c\'est *:minutes_semaine ou :minutes_intensif minutes* avec moi, selon votre formule.'],
            ['shot' => 'screen', 'scene' => 'dashboard.lesson', 'chapter' => 'La séance du jour', 'text' => 'Dans « Mon espace », touchez « *Parler avec :teacher* ».'],
            ['shot' => 'screen', 'scene' => 'lesson.start', 'medallion' => false, 'text' => 'Touchez « *Commencer* ». Je vous dis combien de temps nous avons.'],
            ['shot' => 'screen', 'scene' => 'lesson.teach', 'medallion' => false, 'text' => 'D\'abord, *la leçon*, écrite *au tableau*. Puis vous *pratiquez*, et vous *parlez* d\'une situation de votre vie.'],
            ['shot' => 'screen', 'scene' => 'lesson.phrases', 'medallion' => false, 'text' => 'Les phrases utiles sont à l\'écran : « *Can you repeat, please?* »'],
            ['shot' => 'screen', 'scene' => 'lesson.summary', 'medallion' => false, 'text' => 'Je vous préviens *:warning_minutes minutes avant la fin*, puis on fait le *bilan*.'],
            ['shot' => 'screen', 'scene' => 'lesson.timer', 'medallion' => false, 'text' => 'Le site *arrête la séance* à la fin de votre temps.'],
            ['shot' => 'screen', 'scene' => 'lesson.cut', 'medallion' => false, 'text' => 'Si la connexion est coupée, reprenez dès que possible : *le temps de la coupure est mis en pause*.'],
            ['shot' => 'screen', 'scene' => 'lesson.pause', 'medallion' => false, 'text' => 'Attention : le bouton « *Pause* », lui, n\'arrête pas le temps.'],
            ['shot' => 'screen', 'scene' => 'lesson.next', 'text' => 'Après la séance : votre *résumé*, votre *devoir*, et votre *prochaine leçon*.'],
            ['shot' => 'full', 'scene' => 'wait', 'closing' => true, 'text' => 'Pas encore inscrit ? Commencez par l\'*évaluation gratuite* sur *:site*.'],
        ],
    ],

    'mes-cours' => [
        'title' => 'Mes cours',
        'caption' => 'Votre leçon d\'anglais de l\'école expliquée en français par :teacher, avec un document de révision. Uniquement pour les cours d\'anglais.',
        'hashtags' => ['#anglais', '#coursdanglais', '#lycee', '#togo'],
        'segments' => [
            ['shot' => 'full', 'scene' => 'prepare.dashboard', 'text' => 'Votre leçon d\'anglais de l\'école vous pose problème ? *Apportez-la-moi.*'],
            ['shot' => 'screen', 'scene' => 'courses.menu', 'chapter' => 'Mes cours', 'text' => 'Ouvrez le *Menu*, puis « *Mes cours* ».'],
            ['shot' => 'screen', 'scene' => 'courses.add', 'text' => 'Touchez « *Ajouter une leçon* ». Envoyez jusqu\'à *:course_photos photos* de votre cahier, ou un *PDF* de :pdf_pages pages au plus.'],
            ['shot' => 'screen', 'scene' => 'courses.part', 'text' => 'Pour un long PDF, choisissez les pages à travailler : *:part_pages au plus* à la fois.'],
            ['shot' => 'screen', 'scene' => 'courses.lesson', 'text' => 'Je lis la leçon, je vous l\'explique *en français*, et je vous aide à faire l\'exercice *vous-même*.'],
            ['shot' => 'screen', 'scene' => 'courses.time', 'text' => 'Une séance dure *:course_minutes minutes au plus*, prises sur votre temps du jour.'],
            ['shot' => 'screen', 'scene' => 'courses.review', 'text' => 'Après une séance assez longue, un *document de révision* se prépare : vous le *téléchargez* en PDF, et vous le recevez *par e-mail* si vous avez donné votre adresse.'],
            ['shot' => 'screen', 'scene' => 'courses.only', 'text' => 'C\'est *uniquement pour les cours d\'anglais*.'],
            ['shot' => 'full', 'scene' => 'wait', 'closing' => true, 'text' => '« Mes cours » est compris dans *chaque formule*. Commencez par l\'évaluation gratuite, sur *:site*.'],
        ],
    ],

    // Rendez-vous du soir dans le groupe WhatsApp (octobre 2026) : Teacher Joe seul, bandeau en gros (« card »), aucun
    // lien dans la vidéo (il est dans le texte de publication), aucune mention de l'intelligence artificielle.
    'ce-soir' => [
        'title' => 'Rendez-vous ce soir à 19 heures',
        'caption' => 'Ce soir à 19 heures, rendez-vous dans le groupe WhatsApp :platform : :teacher vous montre comment parler anglais depuis votre téléphone, même en partant de zéro. Lien du groupe : :group_link',
        'hashtags' => ['#anglais', '#apprendrelanglais', '#togo', '#talkami'],
        'voice_style' => 'Lis en français comme un professeur très chaleureux et enthousiaste qui invite ses amis à un rendez-vous : souriant, plein d\'énergie, à un rythme naturel. Les mots anglais avec une prononciation américaine, joyeuse.',
        'segments' => [
            ['shot' => 'full', 'scene' => 'wait', 'card' => ['CE SOIR · 19 HEURES'], 'text' => '*Hello everyone! Tonight at 7 p.m., let\'s talk!*'],
            ['shot' => 'full', 'scene' => 'wait', 'card' => ['CE SOIR · 19 HEURES', 'Groupe WhatsApp :platform'], 'text' => 'Ce soir, à *{{dix-neuf heures|19 heures}}*, je vous donne rendez-vous dans le *groupe WhatsApp :platform*.'],
            ['shot' => 'full', 'scene' => 'wait', 'card' => ['CE SOIR · 19 HEURES', 'Groupe WhatsApp :platform'], 'text' => 'Je vous montre comment *parler anglais* depuis votre *téléphone*, même si vous partez *de zéro*.'],
            ['shot' => 'full', 'scene' => 'wait', 'card' => ['CE SOIR · 19 HEURES', 'Groupe WhatsApp :platform'], 'text' => 'Et je vous présente l\'*évaluation orale gratuite* : *:assessment_minutes minutes* avec moi pour découvrir votre niveau.'],
            ['shot' => 'full', 'scene' => 'wait', 'card' => ['CE SOIR · 19 HEURES', 'Groupe WhatsApp :platform'], 'text' => 'Je vous attends ! *See you tonight!*'],
        ],
    ],

    // Les questions que les gens se posent, et des réponses vraies d'après le code (octobre 2026). Aucune mention de
    // l'intelligence artificielle ni de la façon dont :teacher a été créé : il parle simplement en professeur.
    'questions' => [
        'title' => 'Vos questions, mes réponses',
        'caption' => 'Pourquoi apprendre l\'anglais ? Pas le temps ? Trop cher ? Peur de vous tromper ? :teacher répond à vos questions. Évaluation orale gratuite sur :site',
        'hashtags' => ['#anglais', '#apprendrelanglais', '#togo', '#talkami'],
        'segments' => [
            ['shot' => 'full', 'scene' => 'prepare.home', 'text' => 'Vous hésitez encore ? C\'est normal. Voici *vos questions*, et *mes réponses*.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'Est-ce vraiment important d\'apprendre l\'anglais aujourd\'hui ? Oui : l\'anglais est *compris partout dans le monde*. Au travail, dans les études, sur Internet, il est autour de vous.'],
            ['shot' => 'screen', 'scene' => 'home.cards', 'chapter' => 'Ce que l\'anglais apporte', 'text' => 'Qu\'est-ce que l\'anglais peut vous apporter ? Il peut vous *ouvrir des portes* : parler avec des entreprises, des ONG ou des clients, vous présenter dans un *entretien d\'embauche*, comprendre des cours et des documents en anglais pour vos études.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'Et aussi suivre des *formations en ligne*, *voyager*, et avoir la *confiance* de répondre quand quelqu\'un vous parle anglais.'],
            ['shot' => 'screen', 'scene' => 'home.assessment', 'chapter' => 'Pourquoi maintenant ?', 'text' => 'Pourquoi maintenant ? Parce que l\'anglais s\'apprend *en le pratiquant*, et que vous pouvez commencer *dès aujourd\'hui*, depuis votre téléphone, par l\'*évaluation orale gratuite*.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'Je comprends un peu, mais je n\'arrive pas à parler. C\'est justement notre travail : à chaque séance, vous *répétez*, vous *pratiquez*, puis vous *parlez* d\'une situation de votre vie.'],
            ['shot' => 'screen', 'scene' => 'home.course', 'chapter' => 'Oser parler', 'text' => 'Tout se passe *à l\'oral*, avec la leçon écrite *au tableau*.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'J\'ai peur qu\'on se moque de moi. Vous parlez *avec moi*, pas devant une classe. Je ne me moque jamais : vous avez *le droit de vous tromper*, et je vous aide à corriger.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'Je suis trop âgé, ou trop nul en anglais. *Il n\'y a pas d\'âge pour apprendre.* Vous commencez *à votre niveau*, même au tout début, et je parle *lentement* aux débutants.'],
            ['shot' => 'full', 'scene' => 'wait', 'text' => 'J\'ai déjà essayé, sans résultat. Ici, vous *parlez dès la première séance* : même l\'évaluation se fait *à l\'oral*, avec moi.'],
            ['shot' => 'screen', 'scene' => 'plans.time', 'chapter' => 'Pas le temps ?', 'text' => 'Je n\'ai pas le temps. Avec les formules :name_semaine et :name_essentiel, c\'est *:minutes_essentiel minutes par jour*, depuis votre téléphone, à l\'heure qui vous arrange.'],
            ['shot' => 'screen', 'scene' => 'plans.semaine', 'chapter' => 'Trop cher ?', 'text' => 'C\'est trop cher. Commencez par l\'*évaluation gratuite*. Puis, pour essayer : la formule *:name_semaine*, *:price_semaine* pour :days_semaine jours.'],
            ['shot' => 'screen', 'scene' => 'plans.essentiel', 'chapter' => 'Un prélèvement ?', 'text' => 'On va me prélever de l\'argent chaque mois ? *Non.* Vous payez vous-même chaque formule par *Mobile Money*. *Rien n\'est prélevé automatiquement* : à la fin, vous choisissez de continuer, ou non.'],
            ['shot' => 'screen', 'scene' => 'help.whatsapp', 'chapter' => 'Est-ce sérieux ?', 'text' => 'Est-ce sérieux ? :platform a été fondé par *Joseph Ahiankonou*. Le bureau : *:address*. Et Joseph vous répond sur *WhatsApp*.'],
            ['shot' => 'screen', 'scene' => 'home.faq.data', 'chapter' => 'Vos données', 'text' => 'Et mes données internet ? Une séance utilise environ *{{trois à quatre|3 à 4}} mégaoctets par minute*. Le wifi, si vous le pouvez.'],
            ['shot' => 'screen', 'scene' => 'lesson.light', 'medallion' => false, 'text' => 'Votre téléphone a du mal ? Touchez « *Affichage plus léger* ».'],
            ['shot' => 'screen', 'scene' => 'pay.step1', 'chapter' => 'Payer', 'text' => 'Je ne sais pas payer. *Trois étapes* : copiez le numéro, envoyez le montant avec *Mobile Money*,'],
            ['shot' => 'screen', 'scene' => 'pay.step3', 'text' => 'puis revenez et envoyez la *capture* de la confirmation.'],
            ['shot' => 'screen', 'scene' => 'pay.abroad', 'chapter' => 'Hors du Togo ?', 'text' => 'Je ne suis pas au Togo. On paie par Mobile Money :pay_countries. Ailleurs, écrivez-nous sur *WhatsApp*.'],
            ['shot' => 'screen', 'scene' => 'install.page', 'chapter' => 'Une application ?', 'text' => 'Faut-il une application ? *Non* : :platform marche dans le *navigateur*. Vous pouvez aussi l\'installer sur l\'*écran d\'accueil*.'],
            ['shot' => 'screen', 'scene' => 'courses.new', 'chapter' => 'Pour l\'école', 'text' => 'Je suis élève : ça m\'aide pour l\'école ? Oui, avec « *Mes cours* » : envoyez votre leçon d\'anglais en photo ou en PDF, je vous l\'explique *en français*. *Uniquement pour les cours d\'anglais.*'],
            ['shot' => 'screen', 'scene' => 'attestation.show', 'chapter' => 'Un document ?', 'text' => 'Aurai-je un document ? Après le *bilan oral* réussi de chaque niveau, vous recevez une *attestation de niveau*, vérifiable en ligne. Ce n\'est pas un examen officiel.'],
            ['shot' => 'full', 'scene' => 'wait', 'closing' => true, 'text' => 'Une autre question ? Écrivez-nous sur WhatsApp, ou commencez par l\'évaluation orale gratuite sur *:site*.'],
        ],
    ],

];
