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
            <li><strong>Votre compte</strong> : votre nom, votre numéro de téléphone (il sert à vous connecter) et, si vous la donnez, votre adresse e-mail. Votre mot de passe est enregistré sous une forme protégée que personne ne peut relire.</li>
            <li><strong>Votre pays</strong> : celui que vous choisissez à l'inscription (pour les comptes plus anciens, il est déduit de l'indicatif de votre numéro de téléphone) ; il sert à vous proposer les bons moyens de paiement et à vous saluer à l'heure de votre pays.</li>
            <li><strong>Votre apprentissage</strong> : le niveau trouvé lors de l'évaluation, vos réponses au questionnaire (objectif, métier, jours de travail, difficultés), votre programme, les résumés et devoirs de vos séances, vos erreurs à revoir, vos notes et vos attestations de niveau.</li>
            <li><strong>Vos paiements</strong> : la formule choisie, le pays, le moyen de paiement, le montant, et la capture d'écran de confirmation que vous envoyez, avec ce que le site y a lu (montant, date, numéro de transaction, numéro qui a reçu l'argent).</li>
            <li><strong>« Mes cours »</strong> : les photos ou le PDF des leçons d'anglais que vous envoyez, et ce que {{ $teacher }} en a lu (la leçon entière, ou le sommaire d'un long PDF et les parties que vous choisissez).</li>
            <li><strong>Le texte de vos conversations</strong> avec {{ $teacher }} : ce que vous dites et ce qu'il répond, mot à mot, avec l'heure de chaque réplique, pour l'évaluation gratuite, les séances du jour, « Mes cours » et les bilans de niveau.</li>
            <li><strong>Des mesures techniques de ces conversations</strong> : l'heure où chacun a commencé et fini de parler (mesurée à partir du niveau sonore du micro, sans rien enregistrer du son), le temps de réponse de {{ $teacher }}, et les incidents (reconnexion, coupure, micro fermé, pause). Elles servent à repérer et corriger les lenteurs.</li>
        </ul>
        <p><strong>Votre voix n'est jamais enregistrée.</strong> Seul le texte de la conversation est gardé.</p>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-use" style="--i: 2">
        <h2 id="privacy-use">À quoi elles servent</h2>
        <ul>
            <li>Vous connecter, vous donner accès à votre formule et suivre le temps de séance de chaque jour.</li>
            <li>Construire votre programme personnel et permettre à {{ $teacher }} de vous parler par votre prénom, à votre niveau, sur la leçon du jour.</li>
            <li>Vérifier vos paiements : la capture est lue automatiquement, puis contrôlée par l'administrateur.</li>
            <li>Vous aider sur vos leçons d'anglais de l'école, avec « Mes cours ».</li>
            <li>Relire les conversations pour améliorer {{ $teacher }}, vérifier qu'il se comporte bien et répondre à vos questions ou à vos réclamations.</li>
            <li>Vous délivrer une attestation de niveau. Toute personne qui a son code de vérification peut voir sur le site votre nom, le niveau validé et la date.</li>
        </ul>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-voice" style="--i: 3">
        <h2 id="privacy-voice">Votre voix et l'intelligence artificielle</h2>
        <p>Pendant une séance, votre voix passe en direct par les serveurs de Google (Gemini) ou, en secours, d'OpenAI : ce sont eux qui font comprendre et parler {{ $teacher }}. Pour cela, ils reçoivent aussi les consignes de la séance, avec votre prénom, votre niveau et votre programme. {{ $platform }} n'enregistre pas votre voix.</p>
        <p>Les captures de paiement, les leçons de « Mes cours », les réponses du questionnaire et, si l'évaluation gratuite s'arrête avant l'annonce du niveau, le texte de cette évaluation sont lus par le modèle de texte de Google (Gemini) ou, en secours, d'OpenAI. Nous leur demandons de ne pas conserver ces demandes (option prévue par leurs services). Les messages vocaux de bienvenue, qui disent votre prénom, sont aussi fabriqués par la voix de synthèse de Google ou d'OpenAI.</p>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-duration" style="--i: 4">
        <h2 id="privacy-duration">Combien de temps nous les gardons</h2>
        <ul>
            <li><strong>Le texte des conversations et ses mesures techniques</strong> : {{ $keepDays }} jours, puis ils sont effacés automatiquement chaque nuit.</li>
            <li><strong>Les leçons de « Mes cours »</strong> : jusqu'à ce que vous les supprimiez (bouton « Supprimer » de chaque leçon, fichiers compris), ou jusqu'à la suppression de votre compte.</li>
            <li><strong>Tout le reste</strong> (compte, apprentissage, paiements et captures, attestations) : jusqu'à la suppression de votre compte, que vous pouvez demander à tout moment.</li>
            <li>Votre connexion au site est gardée par un petit fichier de votre navigateur (cookie de session). Le navigateur se souvient aussi de quelques choix faits sur votre téléphone : le micro utilisé, l'affichage de {{ $teacher }} en 3D ou en visage simple, et le bandeau d'installation fermé.</li>
        </ul>
    </section>

    <section class="card privacy rise" aria-labelledby="privacy-access" style="--i: 5">
        <h2 id="privacy-access">Qui y a accès</h2>
        <p>Seul l'administrateur de {{ $platform }} peut voir vos informations, vos captures de paiement, vos leçons de « Mes cours » et le texte de vos conversations. Le site est hébergé par Laravel Cloud, sur des serveurs en Europe ; les fichiers (captures, leçons, attestations) y sont gardés dans un espace privé, jamais accessible par une adresse publique. Quand un paiement arrive, l'administrateur est prévenu par une notification et par un e-mail qui indiquent votre nom, votre pays, la formule et le moyen de paiement (jamais la capture ni le numéro de transaction) ; cet e-mail passe par un service d'envoi de messages.</p>
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
