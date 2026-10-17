@php
    use App\Support\Duration;
@endphp

<x-layout :title="$user->name">
    @include('admin.nav')

    <p><a href="{{ route('admin.users.index') }}">← Tous les inscrits</a></p>
    <h1>{{ $user->name }}</h1>

    @if ($temporaryPassword)
        <div class="alert alert-success" role="alert">
            <strong>Mot de passe provisoire : <code class="secret">{{ $temporaryPassword }}</code></strong><br>
            Notez-le maintenant : il ne sera plus jamais affiché. L'apprenant devra en choisir un nouveau à sa prochaine connexion.
            <a class="btn btn-whatsapp btn-block next-step" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Envoyer à l'apprenant sur WhatsApp</a>
        </div>
    @endif

    <section class="card">
        <dl class="summary">
            <dt>Téléphone</dt><dd><a href="tel:{{ $user->phone }}">{{ $user->phone }}</a></dd>
            @if ($user->email)
                <dt>Adresse e-mail</dt><dd><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd>
            @endif
            <dt>Rôle</dt><dd>{{ $user->role->label() }}</dd>
            <dt>Inscription</dt><dd>{{ $user->created_at->format('d/m/Y H:i') }}</dd>
            <dt>Niveau</dt><dd>{{ $user->level ? $user->level->value.' — '.$user->level->label() : 'Pas encore évalué' }}</dd>
            <dt>Formule en cours</dt>
            <dd>
                @if ($user->activeSubscription)
                    {{ $user->activeSubscription->plan_name }}, jusqu'au {{ $user->activeSubscription->ends_at->format('d/m/Y') }}
                @else
                    Aucune
                @endif
            </dd>
            @if ($user->must_change_password)
                <dt>Mot de passe</dt><dd>Provisoire, à changer à la prochaine connexion</dd>
            @endif
        </dl>

        <form method="POST" action="{{ route('admin.users.password', $user) }}" {!! \App\Support\Confirmation::attributes('password.temporary') !!}>
            @csrf
            <button type="submit" class="btn btn-light">Générer un mot de passe provisoire</button>
        </form>
    </section>

    <section class="card">
        <h2>Évaluation</h2>
        @if ($user->assessment)
            <p>
                {{ $user->assessment->status->label() }}
                ({{ Duration::formatPrecise($user->assessment->used_seconds) }})
                @if ($user->assessment->level)
                    — niveau <span class="badge">{{ $user->assessment->level->value }}</span>
                @endif
            </p>
            @if ($user->assessment->feedback)
                <p class="muted">{{ $user->assessment->feedback }}</p>
            @endif
            <form method="POST" action="{{ route('admin.assessments.destroy', $user) }}" {!! \App\Support\Confirmation::attributes('assessment.reset') !!}>
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-light">Réinitialiser l'évaluation</button>
            </form>
        @else
            <p class="muted">Pas encore passée.</p>
        @endif
    </section>

    <section class="card">
        <h2>Abonnements</h2>
        @forelse ($subscriptions as $subscription)
            <p>
                {{ $subscription->plan_name }} — {{ $subscription->status->label() }},
                du {{ $subscription->starts_at?->format('d/m/Y') ?? '—' }} au {{ $subscription->ends_at?->format('d/m/Y') ?? '—' }}
            </p>
        @empty
            <p class="muted">Aucun abonnement.</p>
        @endforelse
    </section>

    <section class="card">
        <h2>Paiements</h2>
        @forelse ($payments as $payment)
            @include('admin.payments.card', ['payment' => $payment, 'actions' => $payment->isPending()])
        @empty
            <p class="muted">Aucun paiement.</p>
        @endforelse
    </section>
</x-layout>
