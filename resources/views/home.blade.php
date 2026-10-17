@php
    use App\Enums\Level;
    use App\Support\Asset;

    $minutes = config('platform.assessment.max_minutes');
    $start = auth()->check() ? route('dashboard') : route('register');
@endphp

<x-layout install :description="'Apprenez l\'anglais à l\'oral avec '.config('platform.name').', votre professeur IA. Évaluation gratuite, séances quotidiennes, paiement par Mobile Money.'">
    <section class="hero">
        <div class="rise">
            <span class="eyebrow"><x-icon name="sparkle" /> Professeur d'anglais pour les francophones</span>
            <h1>Parlez anglais avec <span class="accent">{{ config('platform.name') }}</span>, votre professeur IA</h1>
            <p class="lead">
                Un professeur qui vous fait parler, à votre niveau, un peu chaque jour, depuis votre téléphone.
                Commencez par une évaluation orale gratuite de {{ $minutes }} minutes.
            </p>
            <div class="actions actions-row">
                @auth
                    <a class="btn btn-accent btn-lg btn-block" href="{{ route('dashboard') }}"><x-icon name="home" /> Aller à mon espace</a>
                @else
                    <a class="btn btn-accent btn-lg btn-block" href="{{ route('register') }}"><x-icon name="mic" /> Commencer l'évaluation gratuite</a>
                    <a class="btn btn-outline btn-block" href="{{ route('login') }}">J'ai déjà un compte</a>
                @endauth
            </div>
            <ul class="hero-points">
                <li><x-icon name="check-circle" /> Évaluation orale offerte, sans engagement</li>
                <li><x-icon name="check-circle" /> Votre niveau d'anglais, de A1 à C2</li>
                <li><x-icon name="check-circle" /> Paiement par Mobile Money</li>
            </ul>
        </div>

        <figure class="hero-media rise" style="--i: 2" aria-hidden="true">
            <img src="{{ Asset::url('images/teacher-joe.webp') }}" width="560" height="560" alt="" fetchpriority="high">
            <span class="hero-bubble right" lang="en">Hello!</span>
            <span class="hero-bubble" lang="en">Let's talk!</span>
        </figure>
    </section>

    <section class="section" aria-labelledby="offre">
        <div class="section-head reveal">
            <span class="eyebrow">Ce que vous obtenez</span>
            <h2 id="offre">Un vrai professeur de conversation, dans votre poche</h2>
            <p class="muted">Le professeur vous parle, vous écoute, vous corrige avec bienveillance et adapte la séance à votre niveau.</p>
        </div>
        <div class="features">
            <article class="card feature reveal" style="--i: 0">
                <span class="icon-bubble"><x-icon name="mic" /></span>
                <div><h3>Il vous fait parler</h3><p>Des conversations orales en anglais : vous parlez, il répond, il vous aide à dire les choses correctement.</p></div>
            </article>
            <article class="card feature reveal" style="--i: 1">
                <span class="icon-bubble icon-bubble-accent"><x-icon name="sparkle" /></span>
                <div><h3>Évaluation orale gratuite</h3><p>{{ $minutes }} minutes au maximum, une seule fois par compte, pour connaître votre niveau.</p></div>
            </article>
            <article class="card feature reveal" style="--i: 2">
                <span class="icon-bubble"><x-icon name="level" /></span>
                <div><h3>Les niveaux A1 à C2</h3><p>Un programme officiel de 12 unités par niveau, du débutant à la maîtrise.</p></div>
            </article>
            <article class="card feature reveal" style="--i: 0">
                <span class="icon-bubble"><x-icon name="calendar" /></span>
                <div><h3>Une séance chaque jour</h3><p>Une séance orale à durée fixe, les jours que vous choisissez, avec un résumé et un petit devoir.</p></div>
            </article>
            <article class="card feature reveal" style="--i: 1">
                <span class="icon-bubble icon-bubble-accent"><x-icon name="award" /></span>
                <div><h3>Une attestation de niveau</h3><p>À la fin de chaque niveau, un bilan oral : réussi, il vous donne une attestation vérifiable.</p></div>
            </article>
            <article class="card feature reveal" style="--i: 2">
                <span class="icon-bubble"><x-icon name="mobile" /></span>
                <div><h3>Paiement Mobile Money</h3><p>Vous payez depuis votre téléphone, nous validons votre paiement et votre formule s'active.</p></div>
            </article>
        </div>
    </section>

    <section class="section" aria-labelledby="niveaux">
        <div class="section-head reveal">
            <span class="eyebrow">Niveaux</span>
            <h2 id="niveaux">Du premier mot à la maîtrise</h2>
            <p class="muted">L'évaluation vous place au bon niveau ; chaque niveau réussi vous fait passer au suivant.</p>
        </div>
        <ul class="levels reveal">
            @foreach (Level::cases() as $level)
                <li><strong>{{ $level->value }}</strong><span>{{ $level->label() }}</span></li>
            @endforeach
        </ul>
    </section>

    <section class="section" aria-labelledby="etapes">
        <div class="section-head reveal">
            <span class="eyebrow">Comment ça marche ?</span>
            <h2 id="etapes">Six étapes, de l'inscription à la séance du jour</h2>
        </div>
        <ol class="steps card reveal">
            <li><strong>Créez votre compte</strong> avec votre nom et votre numéro de téléphone.</li>
            <li><strong>Passez l'évaluation orale gratuite</strong> ({{ $minutes }} minutes au maximum) avec le professeur.</li>
            <li><strong>Découvrez votre niveau</strong>, de A1 (débutant) à C2 (maîtrise).</li>
            <li><strong>Abonnez-vous</strong> par Mobile Money ({{ implode(', ', $operators) }}).</li>
            <li><strong>Construisez votre programme</strong> personnel en répondant à quatre questions.</li>
            <li><strong>Pratiquez chaque jour</strong> pendant une séance orale à durée fixe.</li>
        </ol>
    </section>

    <section class="section" aria-labelledby="plans">
        <div class="section-head reveal">
            <span class="eyebrow">Formules</span>
            <h2 id="plans">Nos formules</h2>
            <p class="muted">L'évaluation de niveau est offerte. Choisissez ensuite la formule qui vous convient.</p>
        </div>
        <div class="plans">
            @foreach ($plans as $plan)
                <article class="card plan reveal" style="--i: {{ $loop->index }}">
                    <h3>{{ $plan->name }}</h3>
                    <p class="price">{{ $plan->formattedPrice() }}</p>
                    <span class="period">{{ $plan->periodLabel() }}</span>
                    <ul>
                        <li><x-icon name="clock" /> {{ $plan->dailyMinutes }} minutes de séance par jour</li>
                        <li><x-icon name="calendar" /> Valable {{ $plan->durationDays }} jours</li>
                        <li><x-icon name="check" /> Programme personnel et suivi</li>
                    </ul>
                    <a class="btn btn-light btn-block" href="{{ auth()->check() ? route('payment.create', $plan->key) : route('register') }}">Choisir {{ $plan->name }}</a>
                </article>
            @endforeach
        </div>
        <div class="reveal next-step">
            <p class="muted small">Paiement par Mobile Money ({{ implode(', ', $countries) }}), avec :</p>
            <ul class="pay-logos">
                @foreach ($operators as $operator)
                    <li>{{ $operator }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="section">
        <div class="cta-band reveal">
            <h2>Prêt à parler anglais ?</h2>
            <p>Créez votre compte et passez votre évaluation orale gratuite : {{ $minutes }} minutes suffisent.</p>
            <a class="btn btn-accent btn-lg" href="{{ $start }}"><x-icon name="mic" /> Commencer l'évaluation gratuite</a>
        </div>
    </section>
</x-layout>
