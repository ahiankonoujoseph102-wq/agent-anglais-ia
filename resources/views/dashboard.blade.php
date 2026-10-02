<x-layout title="Mon espace">
    <h1>Bonjour {{ $user->name }}</h1>

    <div class="stats">
        <div class="stat">
            <div class="label">Mon niveau</div>
            @if ($user->level)
                <div class="value">{{ $user->level->value }}</div>
                <div class="detail">{{ $user->level->label() }}</div>
            @else
                <div class="value">—</div>
                <div class="detail">Pas encore évalué</div>
            @endif
        </div>

        <div class="stat">
            <div class="label">Ma formule</div>
            @if ($subscription)
                <div class="value">{{ $subscription->plan_name }}</div>
                <div class="detail">
                    {{ $subscription->daily_minutes }} min par jour,
                    jusqu'au {{ $subscription->ends_at->translatedFormat('j F Y') }}
                </div>
            @else
                <div class="value">Aucune</div>
                <div class="detail">Pas d'abonnement actif</div>
            @endif
        </div>

        <div class="stat">
            <div class="label">Temps restant aujourd'hui</div>
            @if ($remainingSeconds !== null)
                <div class="value">{{ \App\Support\Duration::format($remainingSeconds) }}</div>
                <div class="detail">sur {{ $subscription->daily_minutes }} min</div>
            @else
                <div class="value">—</div>
                <div class="detail">Disponible avec un abonnement</div>
            @endif
        </div>
    </div>

    <section class="card next-step">
        @if (! $user->level)
            <h2>Votre évaluation gratuite</h2>
            <p>
                Parlez {{ $assessmentMinutes }} minutes au maximum avec {{ config('platform.name') }}
                pour connaître votre niveau d'anglais.
            </p>
            <button type="button" class="btn" disabled>Bientôt disponible</button>
        @elseif (! $subscription)
            <h2>Continuez avec {{ config('platform.name') }}</h2>
            <p>Choisissez une formule pour commencer vos séances quotidiennes.</p>
            <button type="button" class="btn" disabled>Bientôt disponible</button>
        @else
            <h2>Ma séance du jour</h2>
            <p>Votre professeur vous attend.</p>
            <button type="button" class="btn" disabled>Bientôt disponible</button>
        @endif
    </section>
</x-layout>
