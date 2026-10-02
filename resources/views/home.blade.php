<x-layout :description="'Apprenez l\'anglais à l\'oral avec '.config('platform.name').', votre professeur IA. Évaluation gratuite, séances quotidiennes, paiement par mobile money.'">
    <section class="hero">
        <h1>Parlez anglais avec {{ config('platform.name') }}, votre professeur IA</h1>
        <p class="lead">
            Des séances orales courtes, chaque jour, à votre niveau.
            Commencez par une évaluation gratuite de {{ config('platform.assessment.max_minutes') }} minutes.
        </p>
        <div class="actions">
            @auth
                <a class="btn" href="{{ route('dashboard') }}">Aller à mon espace</a>
            @else
                <a class="btn" href="{{ route('register') }}">Créer mon compte gratuitement</a>
                <a class="btn btn-light" href="{{ route('login') }}">J'ai déjà un compte</a>
            @endauth
        </div>
    </section>

    <section class="card" aria-labelledby="how">
        <h2 id="how">Comment ça marche ?</h2>
        <ol class="steps">
            <li><strong>Créez votre compte</strong> avec votre nom et votre numéro de téléphone.</li>
            <li><strong>Passez l'évaluation orale gratuite</strong> ({{ config('platform.assessment.max_minutes') }} minutes au maximum) avec le professeur.</li>
            <li><strong>Découvrez votre niveau</strong>, de A1 (débutant) à C2 (maîtrise).</li>
            <li><strong>Abonnez-vous</strong> par mobile money : Flooz ou Mixx by Yas.</li>
            <li><strong>Construisez votre programme</strong> personnel avec le professeur.</li>
            <li><strong>Pratiquez chaque jour</strong> pendant une séance orale à durée fixe.</li>
        </ol>
    </section>

    <section aria-labelledby="plans">
        <h2 id="plans">Nos formules</h2>
        <div class="plans">
            @foreach ($plans as $plan)
                <div class="card plan">
                    <h3>{{ $plan->name }}</h3>
                    <div class="price">{{ $plan->formattedPrice() }}</div>
                    <div class="muted">{{ $plan->periodLabel() }}</div>
                    <p>{{ $plan->dailyMinutes }} minutes de séance par jour</p>
                </div>
            @endforeach
        </div>
        <p class="muted small">Paiement par mobile money. L'évaluation de niveau est offerte.</p>
    </section>
</x-layout>
