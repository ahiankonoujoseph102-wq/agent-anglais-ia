{{--
    Page « Confidentialité » : décrit UNIQUEMENT ce que fait le code (vérifié phrase par phrase).
    À mettre à jour à chaque changement des données gardées, de leur durée ou des fournisseurs.
    Responsable : config/platform.php → privacy. Durée des conversations : platform.transcripts.keep_days.
--}}
@php
    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
    $privacy = config('platform.privacy');
    $keepDays = (int) config('platform.transcripts.keep_days');
@endphp

<x-layout title="Confidentialité et données personnelles" narrow :description="'Quelles informations '.$platform.' garde, à quoi elles servent, combien de temps, et comment en demander l\'accès ou la suppression.'">
    <div class="auth-head rise">
        <span class="icon-bubble"><x-icon name="shield" /></span>
        <h1>Confidentialité</h1>
        <p>Ce que {{ $platform }} garde sur vous, pourquoi, pendant combien de temps, et comment en demander la suppression.</p>
    </div>

    <section class="card privacy rise" aria-labelledby="privacy-collected" style="--i: 1">
        <h2 id="privacy-collected">Les informations que nous gardons</h2>
        <ul>
            <li><strong>Votre compte</strong> : votre nom, votre numéro de téléphone (il sert à vous connecter) et, si vous la donnez, votre adresse e-mail (elle sert seulement à vous envoyer vos documents de révision). Votre mot de passe est enregistré sous une forme protégée que personne ne peut relire.</li>
            <li><strong>Votre pays</strong> : celui que vous choisissez à l'inscription (pour les comptes plus anciens, il est déduit de l'indicatif de votre numéro de téléphone) ; il sert à vous proposer les bons moyens de paiement et à vous saluer à l'heure de votre pays.</li>
            <li><strong>Votre apprentissage</strong> : le niveau trouvé lors de l'évaluation, vos réponses au questionnaire (objectif, métier, jours de travail, difficultés), votre programme, les résumés et devoirs de vos séances, vos erreurs à revoir, vos notes et vos attestations de niveau.</li>
            <li><strong>Vos paiements</strong> : la formule choisie, le pays, le moyen de paiement, le montant, et la capture d'écran de confirmation que vous envoyez, avec ce que le site y a lu (montant, date, numéro de transaction, numéro qui a reçu l'argent et numéro qui l'a envoyé).</li>
            <li><strong>« Mes cours »</strong> : les photos ou le PDF des leçons d'anglais que vous envoyez, et ce que {{ $teacher }} en a lu (la leçon entière, ou le sommaire d'un long PDF et les parties que vous choisissez), et, après chaque séance, votre document personnel de révision en PDF (la leçon expliquée, vos erreurs pendant la séance, les phrases à retenir et un exercice).</li>
            <li><strong>Le texte de vos conversations</strong> avec {{ $teacher }} : ce que vous dites et ce qu'il répond, mot à mot, avec l'heure de chaque réplique, pour l'évaluation gratuite, les séances du jour, « Mes cours » et les bilans de niveau.</li>
            <li><strong>Comment vous êtes arrivé sur le site</strong> : quand vous créez votre compte, nous gardons la première page que vous avez vue sur le site, le nom du site d'où vous veniez (par exemple un moteur de recherche ou un réseau social ; jamais l'adresse complète de la page) et, si vous avez suivi un lien partagé, son origine (par exemple WhatsApp). Cela nous aide à savoir comment les gens trouvent {{ $platform }}. Aucun outil de mesure extérieur ni cookie publicitaire n'est utilisé ; si vous ne créez pas de compte, rien n'est gardé.</li>
            <li><strong>Le lien ou le code d'un ambassadeur</strong> : si vous ouvrez le lien personnel d'un ambassadeur de {{ $platform }}, un petit cookie du site garde son code pendant {{ config('platform.ambassadors.link_days') }} jours. Si vous créez votre compte dans ce délai, ou si vous tapez son code en créant votre compte, votre compte lui est rattaché : il voit votre prénom et la date de votre inscription, jamais votre numéro. Pour éviter les abus, nous gardons aussi une empreinte chiffrée de votre adresse internet au moment de l'inscription (jamais l'adresse elle-même).</li>
            <li><strong>Votre candidature d'ambassadeur</strong> : si vous posez votre candidature sur la page « Devenir ambassadeur », nous gardons ce que vous écrivez (pourquoi, à qui vous en parlerez), si vous avez moins de 18 ans, et la décision de notre équipe. Si vous demandez à être prévenu quand une place se libère, nous gardons votre demande et la date à laquelle nous vous avons prévenu.</li>
            <li><strong>Des mesures techniques de ces conversations</strong> : l'heure où chacun a commencé et fini de parler (mesurée à partir du niveau sonore du micro, sans rien enregistrer du son), le temps de réponse de {{ $teacher }}, et les incidents (reconnexion, coupure, micro fermé, pause). Elles servent à repérer et corriger les lenteurs.</li>
        </ul>
        <p><strong>Votre voix n'est jamais enregistrée.</strong> Seul le texte de la conversation est gardé.</p>
        <p>Si vous touchez « Enregistrer ma séance », la vidéo (avec votre voix et celle de {{ $teacher }}) est fabriquée et gardée sur votre téléphone uniquement : elle n'est jamais envoyée à {{ $platform }}. C'est vous qui décidez de la garder, de la partager ou de l'effacer.</p>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-use" style="--i: 2">
        <h2 id="privacy-use">À quoi elles servent</h2>
        <ul>
            <li>Vous connecter, vous donner accès à votre formule et suivre le temps de séance de chaque jour.</li>
            <li>Construire votre programme personnel et permettre à {{ $teacher }} de vous parler par votre prénom, à votre niveau, sur la leçon du jour.</li>
            <li>Vérifier vos paiements : la capture est lue automatiquement, puis contrôlée par l'administrateur.</li>
            <li>Vous aider sur vos leçons d'anglais de l'école, avec « Mes cours », et vous préparer après chaque séance un document de révision, à télécharger et, si vous avez donné votre adresse, envoyé par e-mail.</li>
            <li>Relire les conversations pour améliorer {{ $teacher }}, vérifier qu'il se comporte bien et répondre à vos questions ou à vos réclamations. Des extraits anonymisés (sans nom, numéro ni adresse) peuvent être analysés, y compris avec un outil d'intelligence artificielle, pour améliorer {{ $teacher }}.</li>
            <li>Préparer les vidéos du Club des abonnés (groupe WhatsApp des abonnés payants) : {{ $teacher }} peut y présenter les erreurs fréquentes des abonnés, seulement si au moins trois personnes différentes les ont faites, sans jamais donner de nom ni reprendre une phrase telle qu'elle a été dite (l'exemple est toujours réécrit) ; les progrès du Club n'y sont donnés qu'en chiffres globaux.</li>
            <li>Vous délivrer une attestation de niveau. Toute personne qui a son code de vérification peut voir sur le site votre nom, le niveau validé et la date.</li>
        </ul>
    </section>

    {{-- Aucune marque nommée ici non plus : seulement des « prestataires techniques d'intelligence artificielle et d'hébergement ». --}}
    <section class="card privacy rise" aria-labelledby="privacy-voice" style="--i: 3">
        <h2 id="privacy-voice">Votre voix, l'intelligence artificielle et nos prestataires techniques</h2>
        <p>{{ $platform }} est responsable du traitement de vos données. Il conserve vos informations, le texte de vos conversations et vos documents sur ses serveurs, et les efface selon les durées indiquées plus bas.</p>
        <p>Pour faire fonctionner {{ $teacher }}, certaines données sont transmises de façon sécurisée à des prestataires techniques d'intelligence artificielle et d'hébergement, qui peuvent être situés hors du Togo et les traitent uniquement pour rendre ce service :</p>
        <ul>
            <li>pendant une séance, votre voix passe en direct par leurs serveurs, avec les consignes de la séance (votre prénom, votre niveau et votre programme) : c'est ainsi que {{ $teacher }} vous comprend et vous répond. {{ $platform }} n'enregistre pas votre voix ;</li>
            <li>les captures de paiement, les leçons ajoutées dans « Mes cours », les réponses du questionnaire, le texte d'une séance « Mes cours » avec sa leçon (pour préparer votre document de révision) et, si l'évaluation gratuite s'arrête avant l'annonce du niveau, le texte de cette évaluation leur sont envoyés pour être lus ; nous leur demandons de ne pas conserver ces demandes, quand leur service le permet ;</li>
            <li>les messages vocaux de bienvenue, qui disent votre prénom, sont fabriqués par leur voix de synthèse ;</li>
            <li>le site et ses fichiers sont hébergés chez un prestataire d'hébergement, sur des serveurs en Europe.</li>
        </ul>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-duration" style="--i: 4">
        <h2 id="privacy-duration">Combien de temps nous les gardons</h2>
        <ul>
            <li><strong>Le texte des conversations et ses mesures techniques</strong> : {{ $keepDays }} jours, puis ils sont effacés automatiquement chaque nuit.</li>
            <li><strong>Les leçons de « Mes cours » et leurs documents de révision</strong> : jusqu'à ce que vous supprimiez la leçon (bouton « Supprimer » de chaque leçon, fichiers et documents compris), ou jusqu'à la suppression de votre compte. Un document de révision reste téléchargeable même après la fin de votre formule.</li>
            <li><strong>Tout le reste</strong> (compte, apprentissage, paiements et captures, attestations) : jusqu'à la suppression de votre compte, que vous pouvez demander à tout moment.</li>
            <li>Votre connexion au site est gardée par un petit fichier de votre navigateur (cookie de session). Le navigateur se souvient aussi de quelques choix faits sur votre téléphone : le micro utilisé, l'affichage de {{ $teacher }} en 3D ou en visage simple, et le bandeau d'installation fermé.</li>
        </ul>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-access" style="--i: 5">
        <h2 id="privacy-access">Qui y a accès</h2>
        <p>Seul l'administrateur de {{ $platform }} peut voir vos informations, vos captures de paiement, vos leçons de « Mes cours » et le texte de vos conversations. Le site est hébergé sur des serveurs en Europe ; les fichiers (captures, leçons, documents de révision, attestations) y sont gardés dans un espace privé, jamais accessible par une adresse publique. Quand un paiement arrive, l'administrateur est prévenu par une notification et par un e-mail qui indiquent votre nom, votre pays, la formule et le moyen de paiement (jamais la capture ni le numéro de transaction) ; cet e-mail passe par un service d'envoi de messages. Si vous avez donné votre adresse, votre document de révision vous est envoyé en pièce jointe par ce même service d'envoi.</p>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-parents" style="--i: 6">
        <h2 id="privacy-parents">Pour les parents des élèves mineurs</h2>
        <p>Si votre enfant a moins de 18 ans, inscrivez-le vous-même ou aidez-le à le faire, et restez à ses côtés pour le paiement. Ses conversations avec {{ $teacher }} sont gardées sous forme de texte pendant {{ $keepDays }} jours, et seul l'administrateur peut les lire. Vous pouvez à tout moment demander à voir ses informations ou à les faire supprimer, en nous écrivant avec le numéro de téléphone de son compte.</p>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-rights" style="--i: 7">
        <h2 id="privacy-rights">Voir ou supprimer vos informations</h2>
        <p>Pour demander une copie de vos informations, leur correction ou leur suppression (compte entier ou seulement le texte de vos conversations), écrivez au responsable en donnant le numéro de téléphone de votre compte. Nous y répondons après avoir vérifié que la demande vient bien de vous.</p>
        <ul class="contact-list">
            <li><span class="icon-bubble"><x-icon name="user" /></span><div><strong>Responsable</strong>{{ $privacy['controller'] }}</div></li>
            <li><span class="icon-bubble"><x-icon name="mail" /></span><div><strong>E-mail</strong><a href="mailto:{{ $privacy['email'] }}">{{ $privacy['email'] }}</a></div></li>
            <li><span class="icon-bubble"><x-icon name="phone" /></span><div><strong>Téléphone</strong><a href="tel:{{ preg_replace('/\s+/', '', $privacy['phone']) }}">{{ $privacy['phone'] }}</a></div></li>
        </ul>
    </section>
</x-layout>
