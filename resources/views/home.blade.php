@php
    use App\Enums\Level;
    use App\Support\Asset;

    $minutes = config('platform.assessment.max_minutes');
    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
    // Bouton principal : inscription pour un visiteur, évaluation (ou espace) pour un compte connecté.
    $start = auth()->check() ? (auth()->user()->assessment()->exists() ? route('dashboard') : route('assessment.show')) : route('register');
    $startLabel = auth()->check() && auth()->user()->assessment()->exists() ? 'Aller à mon espace' : 'Passer mon évaluation gratuite';
    // Section « élèves » : avec une formule active, le bouton ouvre directement « Mes cours ».
    $hasAccess = auth()->check() && auth()->user()->activeSubscription()->exists();
    $coursesUrl = $hasAccess ? route('courses.index') : $start;
    $coursesLabel = $hasAccess ? 'Ouvrir Mes cours' : $startLabel;
    $coursesIcon = $hasAccess ? 'book' : 'mic';
@endphp

<x-layout install :description="'Apprenez à parler anglais avec '.$teacher.', professeur d\'anglais en ligne disponible chaque jour sur votre téléphone. Évaluation orale gratuite.'">
    {{-- Haut de page : la promesse et le bouton orange, visibles sans faire défiler. --}}
    <section class="hero" aria-labelledby="promesse">
        <div class="hero-text rise">
            {{-- Téléphone : Teacher Joe visible dès l'ouverture (même image que plus bas, aucun poids en plus). --}}
            <p class="hero-teacher">
                <span class="hero-teacher-face"><img src="{{ Asset::url('images/teacher-joe.webp') }}" width="56" height="56" alt=""></span>
                <span><strong>{{ $teacher }}</strong> Votre professeur d'anglais à l'oral</span>
            </p>
            <span class="eyebrow hero-eyebrow"><x-icon name="mic" /> L'anglais à l'oral, pour les francophones</span>
            <h1 id="promesse">Parlez anglais avec un professeur <span class="accent">disponible chaque jour</span></h1>
            <p class="lead">
                {{ $teacher }} vous fait parler, vous corrige et vous fait progresser à votre rythme, depuis votre téléphone.
                Commencez par une évaluation orale gratuite de {{ $minutes }} minutes.
            </p>
            <p class="hero-also">Vous êtes élève ? {{ $teacher }} vous aide aussi à comprendre vos cours d'anglais de l'école.</p>
            <div class="actions actions-row">
                <a class="btn btn-accent btn-lg btn-block" href="{{ $start }}"><x-icon name="mic" /> {{ $startLabel }}</a>
                @guest
                    <a class="btn btn-outline btn-block" href="{{ route('login') }}">J'ai déjà un compte</a>
                @endguest
            </div>
            <ul class="hero-points">
                <li><x-icon name="check-circle" /> Gratuite et sans engagement</li>
                <li><x-icon name="check-circle" /> Votre niveau, de A1 à C2</li>
                <li><x-icon name="check-circle" /> Paiement par Mobile Money</li>
            </ul>
        </div>

        <figure class="hero-media rise" style="--i: 2">
            <img src="{{ Asset::url('images/teacher-joe.webp') }}" width="560" height="560" alt="{{ $teacher }}, le professeur d'anglais de {{ $platform }}" fetchpriority="high">
            <span class="hero-bubble right" lang="en" aria-hidden="true">Hello!</span>
            <span class="hero-bubble" lang="en" aria-hidden="true">Let's talk!</span>
        </figure>
    </section>

    <section class="section" aria-labelledby="etapes">
        <div class="section-head reveal">
            <span class="eyebrow">Comment ça marche</span>
            <h2 id="etapes">Quatre étapes simples</h2>
        </div>
        <ol class="how">
            <li class="card reveal" style="--i: 0">
                <div class="how-head"><span class="how-num">1</span><span class="icon-bubble"><x-icon name="user-plus" /></span></div>
                <h3>Créez votre compte</h3>
                <p>Avec votre nom et votre numéro de téléphone. Aucune carte bancaire.</p>
            </li>
            <li class="card reveal" style="--i: 1">
                <div class="how-head"><span class="how-num">2</span><span class="icon-bubble"><x-icon name="mic" /></span></div>
                <h3>Passez l'évaluation gratuite</h3>
                <p>{{ $minutes }} minutes à l'oral avec {{ $teacher }}, qui vous annonce votre niveau et répond à vos questions.</p>
            </li>
            <li class="card reveal" style="--i: 2">
                <div class="how-head"><span class="how-num">3</span><span class="icon-bubble"><x-icon name="mobile" /></span></div>
                <h3>Choisissez votre formule</h3>
                <p>Payez par Mobile Money depuis votre téléphone, puis envoyez la capture de la confirmation.</p>
            </li>
            <li class="card reveal" style="--i: 3">
                <div class="how-head"><span class="how-num">4</span><span class="icon-bubble"><x-icon name="calendar-check" /></span></div>
                <h3>Parlez chaque jour</h3>
                <p>Une séance orale à durée fixe, construite à partir de votre programme personnel.</p>
            </li>
        </ol>
    </section>

    <section class="section" aria-labelledby="difference">
        <div class="section-split">
            <div class="section-head reveal">
                <span class="eyebrow">Pourquoi {{ $platform }}</span>
                <h2 id="difference">Ce qui rend {{ $platform }} différent</h2>
                <p class="muted">On apprend à parler en parlant. Chaque séance est une vraie conversation, guidée par un professeur patient.</p>
            </div>
            <figure class="photo-frame reveal"><x-photo name="etudiante-parle" /></figure>
        </div>
        <div class="features">
            <article class="card feature reveal" style="--i: 0">
                <span class="icon-bubble icon-bubble-primary"><x-icon name="mic" /></span>
                <div>
                    <h3>Vous parlez vraiment</h3>
                    <p>La séance est orale du début à la fin. {{ $teacher }} pose des questions, écoute et vous fait parler le plus possible.</p>
                </div>
            </article>
            <article class="card feature reveal" style="--i: 1">
                <span class="icon-bubble"><x-icon name="check-circle" /></span>
                <div>
                    <h3>Des corrections immédiates</h3>
                    <p>Une erreur ? Il vous donne la bonne façon de dire, <span lang="en">« You said… We say… »</span>, puis vous répétez. Vos erreurs reviennent aux séances suivantes jusqu'à ce qu'elles soient corrigées.</p>
                </div>
            </article>
            <article class="card feature reveal" style="--i: 2">
                <span class="icon-bubble"><x-icon name="pin" /></span>
                <div>
                    <h3>Des situations de chez nous</h3>
                    <p>Au Grand Marché de Lomé, en zémidjan, au port autonome, à la frontière d'Aflao ou pour accueillir un visiteur venu du Ghana.</p>
                </div>
            </article>
            <article class="card feature reveal" style="--i: 3">
                <span class="icon-bubble"><x-icon name="calendar" /></span>
                <div>
                    <h3>À votre rythme</h3>
                    <p>Vous choisissez vos jours. Chaque séance a une durée fixe et suit votre programme : votre niveau, votre objectif, votre métier.</p>
                </div>
            </article>
        </div>
        <ul class="levels reveal" aria-label="Les niveaux, de A1 à C2">
            @foreach (Level::cases() as $level)
                <li><strong>{{ $level->value }}</strong><span>{{ $level->label() }}</span></li>
            @endforeach
        </ul>
        <p class="muted small reveal next-step">Douze unités par niveau. À la fin de chaque niveau, un bilan oral réussi donne une attestation de niveau.</p>
    </section>

    <section class="section" aria-labelledby="plans">
        <div class="section-head reveal">
            <span class="eyebrow">Formules</span>
            <h2 id="plans">Des formules simples, sans engagement</h2>
            <p class="muted">L'évaluation est offerte. Ensuite, choisissez la durée de séance qui vous convient.</p>
        </div>
        <div class="plans">
            @foreach ($plans as $plan)
                <x-plan-card :plan="$plan" :href="auth()->check() ? route('payment.create', $plan->key) : route('register')" class="reveal" style="--i: {{ $loop->index }}" />
            @endforeach
        </div>
        <p class="muted small reveal next-step">
            <a href="{{ route('plans') }}">Tout savoir sur les formules et les prix</a>.
            Paiement par Mobile Money ({{ implode(', ', $countries) }}) : {{ implode(', ', $operators) }}.
        </p>
    </section>

    {{-- Trois cartes de même format (.students) : élèves, parents, professionnels. --}}
    <section class="section" aria-labelledby="eleves">
        <div class="students students-reverse reveal">
            <figure class="photo-frame"><x-photo name="eleve-ecouteurs" /></figure>
            <div>
                <span class="eyebrow">Vous êtes élève ?</span>
                <h2 id="eleves">Vos cours d'anglais enfin compris</h2>
                <p>En plus de l'oral, {{ $teacher }} vous aide dans vos leçons d'anglais de l'école avec « Mes cours ».</p>
                <ol class="help-steps">
                    <li><span class="icon-bubble"><x-icon name="camera" /></span><span>Photographiez votre leçon ou votre exercice d'anglais</span></li>
                    <li><span class="icon-bubble"><x-icon name="book" /></span><span>{{ $teacher }} la lit et vous l'explique en détail, en français quand il le faut</span></li>
                    <li><span class="icon-bubble"><x-icon name="bubble" /></span><span>Posez-lui vos questions sur la leçon</span></li>
                    <li><span class="icon-bubble"><x-icon name="edit" /></span><span>Il vous guide pour faire votre exercice vous-même, puis corrige</span></li>
                </ol>
                <p class="help-only"><x-icon name="info" /> Uniquement pour les cours d'anglais.</p>
                <a class="btn btn-accent btn-lg" href="{{ $coursesUrl }}"><x-icon :name="$coursesIcon" /> {{ $coursesLabel }}</a>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="parents">
        <div class="students reveal">
            <figure class="photo-frame"><x-photo name="parent-enfant-lecture" /></figure>
            <div>
                <span class="eyebrow">Vous êtes parent ?</span>
                <h2 id="parents">Un professeur d'anglais patient pour votre enfant</h2>
                <p>{{ $teacher }} ne fait pas les devoirs à la place de votre enfant. Il l'aide à comprendre sa leçon d'anglais et à faire lui-même son exercice, avec patience, une étape après l'autre. Il l'entraîne aussi à parler anglais à voix haute.</p>
                <a class="btn btn-accent btn-lg" href="{{ route('register') }}"><x-icon name="user-plus" /> Inscrire mon enfant</a>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="pros">
        <div class="students students-reverse reveal">
            <figure class="photo-frame"><x-photo name="equipe-reunion" /></figure>
            <div>
                <span class="eyebrow">Vous travaillez ?</span>
                <h2 id="pros">Réunions, clients, visiteurs : parlez anglais dans votre métier</h2>
                <p>Quand vous construisez votre programme, vous indiquez votre métier et votre objectif. Les situations qui vous concernent passent en premier : accueillir un partenaire venu du Ghana, présenter votre activité, négocier, voyager.</p>
                <a class="btn btn-accent btn-lg" href="{{ $start }}"><x-icon name="mic" /> {{ $startLabel }}</a>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="pays">
        <div class="section-head reveal">
            <span class="eyebrow">Dans votre pays</span>
            <h2 id="pays">Apprendre l'anglais au Togo, au Bénin, au Burkina Faso et en Côte d'Ivoire</h2>
            <p class="muted">Mêmes formules et mêmes prix partout, avec les moyens de paiement de votre pays. <a href="{{ route('about') }}">Qui sommes-nous ?</a></p>
        </div>
        <x-country-links :pages="$countryPages" />
    </section>

    <section class="section" aria-labelledby="questions">
        <div class="section-head reveal">
            <span class="eyebrow">Vos questions</span>
            <h2 id="questions">Questions fréquentes</h2>
        </div>
        {{-- Questions fréquentes : le même HTML donne les données structurées FAQPage (App\Support\Seo::faq). --}}
        @php(ob_start())
        <div class="faq reveal">
            <details>
                <summary><x-icon name="wallet" /><span>Comment payer mon abonnement ?</span></summary>
                <p>Par Mobile Money, depuis votre téléphone. Vous envoyez le montant exact au numéro affiché sur le site, puis la capture d'écran de la confirmation. Aucune carte bancaire n'est nécessaire. Mêmes formules et mêmes prix dans tous les pays :</p>
                <ul class="faq-list">
                    @foreach ($paymentCountries as $paymentCountry)
                        <li><strong>{{ $paymentCountry['name'] }}</strong> : {{ implode(', ', $paymentCountry['operators']) }}</li>
                    @endforeach
                </ul>
            </details>
            <details>
                <summary><x-icon name="hourglass" /><span>Qu'est-ce que l'accès provisoire ?</span></summary>
                <p>Si votre capture est claire (bon montant, bon numéro, paiement récent), vous pouvez commencer vos séances tout de suite, pendant 24 heures, le temps que notre équipe vérifie votre paiement. Une fois vérifiée, votre formule devient définitive, sans perdre aucun jour.</p>
            </details>
            <details>
                <summary><x-icon name="headphones" /><span>De quel matériel ai-je besoin ?</span></summary>
                <p>Un téléphone ou un ordinateur avec un micro, un navigateur récent comme Chrome, et une connexion internet. Des écouteurs sont pratiques mais pas obligatoires. Aucune application à installer : vous pouvez simplement ajouter {{ $platform }} à l'écran d'accueil de votre téléphone.</p>
            </details>
            <details>
                <summary><x-icon name="wifi" /><span>Combien de données internet faut-il ?</span></summary>
                <p>Les pages du site sont légères. Pendant une séance, la voix passe en continu : comptez environ 3 à 4 mégaoctets par minute selon le temps de parole, soit environ 50 mégaoctets pour une séance de 15 minutes. Le professeur en 3D (environ 1 mégaoctet) n'est téléchargé qu'une seule fois. Si vous le pouvez, utilisez le wifi.</p>
            </details>
            <details>
                <summary><x-icon name="award" /><span>Qu'est-ce que l'attestation de niveau ?</span></summary>
                <p>À la fin de chaque niveau (douze unités), vous passez un bilan oral avec {{ $teacher }}. S'il est réussi, vous recevez une attestation de niveau en PDF, que chacun peut vérifier en ligne grâce à son code.</p>
            </details>
            <details>
                <summary><x-icon name="book" /><span>{{ $teacher }} peut-il m'aider dans mes cours d'anglais ?</span></summary>
                <p>Oui, avec « Mes cours », pour les élèves qui ont une formule active. Vous photographiez votre leçon ou votre exercice d'anglais, {{ $teacher }} la lit et vous l'explique, répond à vos questions et vous guide pour faire l'exercice vous-même. C'est uniquement pour les cours d'anglais, et c'est une aide en plus de votre programme pour parler anglais. Votre formule donne un temps par jour, que vous partagez librement entre la séance orale et « Mes cours ».</p>
            </details>
            <details>
                <summary><x-icon name="gift" /><span>L'évaluation est-elle vraiment gratuite ?</span></summary>
                <p>Oui. Une évaluation orale de {{ $minutes }} minutes au maximum par compte, sans paiement ni engagement. Vous découvrez votre niveau, puis vous décidez.</p>
            </details>
        </div>
        @php($faqHtml = ob_get_clean())
        {!! $faqHtml !!}
        @push('head')
            {!! \App\Support\Seo::jsonLd(\App\Support\Seo::organization()) !!}
            {!! \App\Support\Seo::jsonLd(\App\Support\Seo::website()) !!}
            {!! \App\Support\Seo::jsonLd(\App\Support\Seo::offers()) !!}
            {!! \App\Support\Seo::jsonLd(\App\Support\Seo::faq($faqHtml)) !!}
        @endpush
    </section>

    <section class="section">
        <div class="cta-band reveal">
            <h2>Votre anglais oral commence aujourd'hui</h2>
            <p>{{ $minutes }} minutes gratuites avec {{ $teacher }} pour connaître votre niveau et poser vos questions.</p>
            <div class="cta-actions">
                <a class="btn btn-accent btn-lg" href="{{ $start }}"><x-icon name="mic" /> {{ $startLabel }}</a>
                @if ($whatsappUrl)
                    <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Une question ? WhatsApp</a>
                @endif
            </div>
        </div>
    </section>
</x-layout>
