@php
    use App\Support\Duration;
@endphp

<x-layout :title="$user->name">
    @include('admin.nav')

    <p><a href="{{ route('admin.users.index') }}">← Tous les inscrits</a></p>
    <h1>{{ $user->name }}</h1>

    @if ($temporaryPassword)
        <div class="alert alert-success" role="alert" id="mot-de-passe-provisoire" tabindex="-1">
            <strong>Mot de passe provisoire : <code class="secret">{{ $temporaryPassword }}</code></strong><br>
            Notez-le maintenant : il ne sera plus jamais affiché. L'apprenant devra en choisir un nouveau à sa prochaine connexion.
            <a class="btn btn-whatsapp btn-block next-step" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Envoyer à l'apprenant sur WhatsApp</a>
        </div>
    @endif

    <section class="card" id="compte">
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
                    @if ($user->activeSubscription->isGranted())
                        <span class="badge badge-grant">Accès offert</span>
                    @endif
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

    {{-- Accès offert (démonstrations) : offrir, retirer, historique. Jamais un paiement ni un revenu. --}}
    <section class="card" id="acces-offert" aria-labelledby="grant-title">
        <h2 id="grant-title">Accès offert</h2>
        <p class="muted small">Pour les démonstrations dans les écoles et auprès des parents. Mêmes droits que la formule choisie. Ce n'est pas un paiement : il n'apparaît dans aucun revenu.</p>

        @if ($currentGrant)
            <p class="grant-current">
                <x-icon name="gift" />
                <span><strong>Accès offert jusqu'au {{ $currentGrant->ends_at->translatedFormat('j F Y \à H:i') }}</strong> : formule {{ $currentGrant->plan_name }}.</span>
            </p>
            <form method="POST" action="{{ route('admin.users.revoke', $user) }}" {!! \App\Support\Confirmation::attributes('access.revoke') !!}>
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-light"><x-icon name="close-circle" /> Retirer l'accès offert</button>
            </form>
        @elseif ($grantBlocked)
            <p class="alert alert-warning" role="status"><x-icon name="info" /><span>{{ $grantBlocked }}</span></p>
        @else
            @error('plan')
                <p class="alert alert-error" role="alert"><x-icon name="alert" /><span>{{ $message }}</span></p>
            @enderror
            <form method="POST" action="{{ route('admin.users.grant', $user) }}" class="grant-form" novalidate
                  {!! \App\Support\Confirmation::attributes('access.grant') !!} data-confirm-timezone="{{ config('app.timezone') }}" data-confirm-max-days="{{ config('platform.granted_access.max_days') }}">
                @csrf
                <div class="field">
                    <label for="grant-plan">Formule</label>
                    <select id="grant-plan" name="plan" required>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->key }}" @selected(old('plan', 'essentiel') === $plan->key)>{{ $plan->name }} ({{ $plan->dailyMinutes }} minutes par jour, valeur {{ $plan->formattedPrice() }} {{ $plan->periodLabel() }})</option>
                        @endforeach
                    </select>
                </div>

                <fieldset class="field grant-durations">
                    <legend>Durée</legend>
                    @foreach (config('platform.granted_access.presets') as $days)
                        <label><input type="radio" name="duration" value="{{ $days }}" @checked(old('duration', (string) config('platform.granted_access.presets')[0]) === (string) $days)> {{ $days }} {{ $days > 1 ? 'jours' : 'jour' }}</label>
                    @endforeach
                    <label><input type="radio" name="duration" value="custom" data-then="grant-days" @checked(old('duration') === 'custom')> Autre durée</label>
                    @error('duration')<div class="error">{{ $message }}</div>@enderror
                </fieldset>

                <div class="field">
                    <label for="grant-days">Nombre de jours (si « Autre durée », {{ config('platform.granted_access.max_days') }} au plus)</label>
                    <input id="grant-days" name="custom_days" type="number" inputmode="numeric" min="1" max="{{ config('platform.granted_access.max_days') }}" value="{{ old('custom_days') }}" @error('custom_days') aria-invalid="true" @enderror>
                    @error('custom_days')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label for="grant-note">Note facultative</label>
                    <input id="grant-note" name="note" type="text" maxlength="200" value="{{ old('note') }}" placeholder="Visite au Collège X" @error('note') aria-invalid="true" @enderror>
                    @error('note')<div class="error">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-accent"><x-icon name="gift" /> Offrir un accès</button>
            </form>
        @endif

        <h3 class="next-step">Historique des accès offerts</h3>
        @forelse ($grants as $grant)
            <p class="grant-history">
                <strong>{{ $grant->created_at->format('d/m/Y') }}</strong> · formule {{ $grant->plan_name }} · {{ $grant->granted_days }} {{ $grant->granted_days > 1 ? 'jours' : 'jour' }}
                @if ($grant->revoked_at)
                    · retiré le {{ $grant->revoked_at->format('d/m/Y') }}
                @elseif ($grant->ends_at->isFuture() && $grant->status === \App\Enums\SubscriptionStatus::Active)
                    · en cours, jusqu'au {{ $grant->ends_at->format('d/m/Y') }}
                @else
                    · terminé le {{ $grant->ends_at->format('d/m/Y') }}
                @endif
                @if ($grant->grant_note)
                    <br><span class="muted">{{ $grant->grant_note }}</span>
                @endif
                @if ($grant->grantedBy)
                    <br><span class="muted small">Offert par {{ $grant->grantedBy->name }}</span>
                @endif
            </p>
        @empty
            <p class="muted">Aucun accès offert.</p>
        @endforelse
    </section>

    <section class="card" id="evaluation" aria-labelledby="evaluation-title">
        <h2 id="evaluation-title">Évaluation</h2>
        @if ($user->assessment)
            <p>
                {{ $user->assessment->status->label() }}
                ({{ Duration::formatPrecise($user->assessment->used_seconds) }})
                @if ($user->assessment->level)
                    — niveau <span class="badge">{{ $user->assessment->level->value }}</span>
                @endif
                <span class="badge badge-muted" data-live-provider>{{ \App\Support\Live\LiveProviders::label($user->assessment->liveProvider()) }}</span>
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
        <h2>Séances en direct</h2>
        @if ($conversations->isEmpty())
            <p class="muted">Aucune séance pour le moment.</p>
        @else
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Séance</th><th>Durée</th><th>Fournisseur</th></tr>
                </thead>
                <tbody>
                    @foreach ($conversations as $conversation)
                        <tr>
                            <td data-label="Date">{{ $conversation['started_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td data-label="Séance">{{ $conversation['kind'] }}</td>
                            <td data-label="Durée">{{ Duration::formatPrecise($conversation['seconds']) }}</td>
                            <td data-label="Fournisseur" data-live-provider>{{ \App\Support\Live\LiveProviders::label($conversation['provider']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="card">
        <h2>Abonnements</h2>
        @forelse ($subscriptions as $subscription)
            <p>
                {{ $subscription->plan_name }}@if ($subscription->isGranted()) <span class="badge badge-grant">Accès offert</span>@endif — {{ $subscription->status->label() }},
                du {{ $subscription->starts_at?->format('d/m/Y') ?? '—' }} au {{ $subscription->ends_at?->format('d/m/Y') ?? '—' }}
            </p>
        @empty
            <p class="muted">Aucun abonnement.</p>
        @endforelse
    </section>

    <section class="card">
        <h2>« Mes cours » : {{ $courseLessons->count() }} {{ $courseLessons->count() > 1 ? 'leçons envoyées' : 'leçon envoyée' }}</h2>
        <p class="muted small">Lecture seule : les fichiers sont privés, servis seulement à l'apprenant et aux administrateurs.</p>
        @forelse ($courseLessons as $lesson)
            <details class="course-details">
                <summary>{{ $lesson->title }} · {{ $lesson->created_at->format('d/m/Y') }} · {{ $lesson->pagesLabel() }}</summary>
                <p>
                    @foreach ($lesson->pages as $page)
                        <a href="{{ route('courses.page', [$lesson, $page]) }}" target="_blank" rel="noopener" class="btn btn-light">
                            <x-icon :name="$page->isPdf() ? 'file-text' : 'image'" /> {{ $page->isPdf() ? 'Ouvrir le PDF' : 'Page '.$page->position }}
                        </a>
                    @endforeach
                </p>
                @if (filled($lesson->content['summary'] ?? null))
                    <p>{{ $lesson->content['summary'] }}</p>
                @endif
                @if (filled($lesson->text))
                    <p class="muted small">Contenu extrait par l'IA :</p>
                    <pre class="course-extract">{{ \Illuminate\Support\Str::limit($lesson->text, 6000, '…') }}</pre>
                @endif
            </details>
        @empty
            <p class="muted">Aucune leçon.</p>
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
