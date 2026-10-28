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
            <dt>Téléphone</dt><dd><a href="tel:{{ $user->phone }}">{{ \App\Support\PhoneNumber::display($user->phone) }}</a></dd>
            <dt>Pays</dt><dd>{{ $user->countryName() }}@unless ($user->canPayOnline()) : paiement en ligne indisponible, abonnement par WhatsApp puis « Offrir un accès »@endunless</dd>
            @if ($user->email)
                <dt>Adresse e-mail</dt><dd><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd>
            @endif
            <dt>Rôle</dt><dd>{{ $user->role->label() }}</dd>
            <dt>Inscription</dt><dd>{{ $user->created_at->format('d/m/Y H:i') }}</dd>
            @if ($user->origin)
                <dt>Provenance</dt><dd>{{ \App\Support\Origins::label($user->origin->channel) }}@if ($user->origin->referrer_host) ({{ $user->origin->referrer_host }})@endif, arrivé sur <code>{{ $user->origin->landing_path ?? 'page inconnue' }}</code></dd>
            @endif
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

    {{-- Programme d'ambassadeurs : nommer (une place), renouveler le mois gratuit, retirer, accord d'un parent. --}}
    @unless ($user->isAdmin())
        <section class="card" id="ambassadeur" aria-labelledby="ambassador-title">
            <h2 id="ambassador-title">Ambassadeur</h2>

            @error('ambassador')
                <p class="alert alert-error" role="alert"><x-icon name="alert" /><span>{{ $message }}</span></p>
            @enderror

            {{-- Rattachement de CE compte à un ambassadeur : fixé à l'inscription, corrigé seulement ici. --}}
            @if ($user->referral)
                <p class="grant-current">
                    <x-icon name="award" />
                    <span><strong>Inscrit grâce à {{ $user->referral->ambassador->user->name }} ({{ $user->referral->ambassador->code }})</strong>, {{ $user->referral->sourceLabel() }}, le {{ $user->referral->created_at->format('d/m/Y') }}.</span>
                </p>
                @if ($user->referral->flags)
                    <ul class="referral-flags">
                        @foreach ($user->referral->flagLabels() as $label)
                            <li>À vérifier : {{ $label }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
            @if ($user->referral)
                @if ($user->referralCommission)
                    <p class="small">Commission : <strong>{{ \App\Support\Money::format($user->referralCommission->amount) }}</strong> (formule {{ $user->referralCommission->plan_name }}), {{ mb_strtolower($user->referralCommission->status->label()) }}.</p>
                @else
                    <form method="POST" action="{{ route('admin.users.commission', $user) }}" class="grant-form next-step" {!! \App\Support\Confirmation::attributes('commission.add') !!}>
                        @csrf
                        <p class="muted small">Paiement fait hors des quatre pays (WhatsApp, puis « Offrir un accès ») : ajoutez la commission de l'ambassadeur à la main.</p>
                        <div class="field">
                            <label for="commission-plan">Formule payée</label>
                            <select id="commission-plan" name="plan" required>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->key }}">{{ $plan->name }} ({{ $plan->formattedPrice() }}, commission {{ \App\Support\Money::format(\App\Support\Ambassadors\AmbassadorCommissions::amountFor($plan->price)) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="commission-note">Note facultative</label>
                            <input id="commission-note" name="note" type="text" maxlength="200" placeholder="Paiement reçu sur WhatsApp le …">
                        </div>
                        <button type="submit" class="btn btn-light"><x-icon name="plus" /> Ajouter une commission</button>
                    </form>
                @endif
            @endif
            @if ($activeAmbassadors->isNotEmpty() || $user->referral)
                <form method="POST" action="{{ route('admin.users.referral', $user) }}" class="referral-form next-step">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="referral-ambassador">{{ $user->referral ? 'Corriger le rattachement' : 'Rattacher à un ambassadeur' }}</label>
                        <select id="referral-ambassador" name="ambassador_id">
                            <option value="">Aucun ambassadeur</option>
                            @foreach ($activeAmbassadors as $option)
                                <option value="{{ $option->id }}" @selected($user->referral?->ambassador_id === $option->id)>{{ $option->user->name }} ({{ $option->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-light">Enregistrer le rattachement</button>
                </form>
            @endif

            @if ($user->ambassador?->isActive())
                @php($ambassador = $user->ambassador)
                <dl class="summary">
                    <dt>Code</dt><dd>{{ $ambassador->code }}</dd>
                    <dt>Lien</dt><dd><a href="{{ $ambassador->link() }}">{{ $ambassador->shortLink() }}</a></dd>
                    <dt>Mois gratuit</dt><dd>{{ \App\Support\Ambassadors\AmbassadorProgram::monthLabel($ambassador, $ambassadorGrant) }}</dd>
                    <dt>Accord d'un parent</dt><dd>{{ $ambassador->parental_consent_at ? 'Reçu le '.$ambassador->parental_consent_at->format('d/m/Y') : 'Non noté' }}</dd>
                </dl>
                <div class="ambassador-actions">
                    @if (\App\Support\Ambassadors\AmbassadorProgram::canRenew($ambassador))
                        <form method="POST" action="{{ route('admin.ambassadors.renew', $ambassador) }}" {!! \App\Support\Confirmation::attributes('ambassador.renew') !!}>
                            @csrf
                            <button type="submit" class="btn btn-accent"><x-icon name="refresh" /> Renouveler 1 mois</button>
                        </form>
                    @else
                        <p class="muted small">Les {{ config('platform.ambassadors.max_months') }} mois gratuits ont tous été offerts.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.ambassadors.consent', $ambassador) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="received" value="{{ $ambassador->parental_consent_at ? '0' : '1' }}">
                        <button type="submit" class="btn btn-light">{{ $ambassador->parental_consent_at ? 'Retirer l\'accord d\'un parent' : 'Noter l\'accord écrit d\'un parent (moins de 18 ans)' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.ambassadors.remove', $ambassador) }}" {!! \App\Support\Confirmation::attributes('ambassador.remove') !!}>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-light"><x-icon name="user-off" /> Retirer du programme</button>
                    </form>
                </div>

                <h3 class="next-step">Ses commissions</h3>
                @forelse ($ambassadorCommissions as $commission)
                    <div class="grant-history">
                        <strong>{{ \App\Support\Money::format($commission->amount) }}</strong> grâce à <a href="{{ route('admin.users.show', $commission->user) }}">{{ $commission->user->name }}</a>
                        (formule {{ $commission->plan_name }}, {{ $commission->created_at->format('d/m/Y') }}) :
                        {{ mb_strtolower($commission->status->label()) }}@if ($commission->paid_at) le {{ $commission->paid_at->format('d/m/Y') }}@endif
                        @if ($commission->cancel_reason)
                            <br><span class="muted small">Motif : {{ $commission->cancel_reason }}</span>
                        @endif
                        @if ($commission->isDue())
                            <div class="ambassador-actions">
                                <form method="POST" action="{{ route('admin.commissions.pay', $commission) }}" {!! \App\Support\Confirmation::attributes('commission.pay') !!}>
                                    @csrf
                                    <button type="submit" class="btn btn-accent"><x-icon name="check-circle" /> Commission payée</button>
                                </form>
                                <form method="POST" action="{{ route('admin.commissions.cancel', $commission) }}" class="commission-cancel" {!! \App\Support\Confirmation::attributes('commission.cancel') !!}>
                                    @csrf
                                    <label for="reason-{{ $commission->id }}" class="sr-only">Motif de l'annulation</label>
                                    <input id="reason-{{ $commission->id }}" name="reason" type="text" maxlength="200" required placeholder="Motif (triche)">
                                    <button type="submit" class="btn btn-light"><x-icon name="close-circle" /> Annuler</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="muted">Aucune commission pour le moment.</p>
                @endforelse

                <h3 class="next-step">Ses inscrits : {{ $ambassadorReferrals->count() }}</h3>
                @forelse ($ambassadorReferrals as $referral)
                    <div class="grant-history">
                        <a href="{{ route('admin.users.show', $referral->user) }}">{{ $referral->user->name }}</a>, inscrit le {{ $referral->created_at->format('d/m/Y') }} ({{ $referral->sourceLabel() }})
                        @if ($referral->flags)
                            <ul class="referral-flags">
                                @foreach ($referral->flagLabels() as $label)
                                    <li>À vérifier : {{ $label }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <p class="muted">Aucun inscrit pour le moment.</p>
                @endforelse
            @else
                <p class="muted small">
                    @if ($user->ambassador)
                        Retiré du programme le {{ $user->ambassador->removed_at?->format('d/m/Y') }} (code {{ $user->ambassador->code }}, {{ $user->ambassador->months_granted }} mois déjà offerts).
                    @endif
                    Places libres : {{ $freePlaces }} sur {{ $places }}. Un ambassadeur reçoit un code personnel et un mois gratuit, renouvelable.
                </p>
                @if ($freePlaces > 0)
                    <form method="POST" action="{{ route('admin.users.ambassador', $user) }}" {!! \App\Support\Confirmation::attributes('ambassador.appoint') !!}>
                        @csrf
                        <button type="submit" class="btn btn-outline"><x-icon name="award" /> {{ $user->ambassador ? 'Réintégrer dans le programme' : 'Faire ambassadeur' }}</button>
                    </form>
                @else
                    <p class="alert alert-warning" role="status"><x-icon name="info" /><span>Toutes les places d'ambassadeurs sont prises. Retirez un ambassadeur ou augmentez le nombre de places dans <a href="{{ route('admin.settings.edit') }}#ambassadeurs">Réglages</a>.</span></p>
                @endif
            @endif
        </section>
    @endunless

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
        {{-- Compte de test : passe la nouvelle évaluation quand « Réglages » vaut « Comptes de test seulement ». --}}
        <form method="POST" action="{{ route('admin.users.test-account', $user) }}" id="compte-de-test">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_test_account" value="0">
            <label><input type="checkbox" name="is_test_account" value="1" @checked($user->is_test_account)> Compte de test</label>
            <p class="muted small">Nouvelle évaluation : {{ \App\Support\Live\AssessmentVersion::MODES[\App\Support\Live\AssessmentVersion::mode()] }}. Ce compte passe {{ \App\Support\Live\AssessmentVersion::isNewFor($user) ? 'la nouvelle évaluation' : 'l\'évaluation actuelle' }}.</p>
            <button type="submit" class="btn btn-light">Enregistrer</button>
        </form>
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

    {{-- Toutes les séances orales, avec le texte de leur conversation (jamais l'audio), gardé platform.transcripts.keep_days jours. --}}
    <section class="card" id="conversations" aria-labelledby="conversations-title">
        <h2 id="conversations-title">Conversations</h2>
        @if ($conversations->isEmpty())
            <p class="muted">Aucune séance pour le moment.</p>
        @else
            <p class="muted small">Heure de Lomé. Le texte de chaque conversation est gardé {{ (int) config('platform.transcripts.keep_days') }} jours, puis effacé automatiquement. La voix n'est jamais enregistrée.</p>
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Séance</th><th>Durée</th><th>Fournisseur</th><th>Verdict</th><th>Conversation</th></tr>
                </thead>
                <tbody>
                    @foreach ($conversations as $row)
                        <tr>
                            <td data-label="Date">{{ $row['conversation']->started_at->timezone('Africa/Lome')->format('d/m/Y H:i') }}</td>
                            <td data-label="Séance">{{ $row['label'] }}</td>
                            <td data-label="Durée">{{ Duration::formatPrecise((int) $row['conversation']->used_seconds) }}</td>
                            <td data-label="Fournisseur" data-live-provider>{{ \App\Support\Live\LiveProviders::label($row['conversation']->liveProvider()) }}</td>
                            <td data-label="Verdict">{{ \App\Support\Live\Transcripts::verdict($row['conversation']) ?? '—' }}</td>
                            <td data-label="Conversation">
                                @if ($row['lines'] > 0)
                                    <a href="{{ route('admin.conversations.show', [$user, $row['type'], $row['conversation']->getKey()]) }}">Lire la conversation</a>
                                    <span class="muted small">({{ $row['lines'] }} {{ $row['lines'] > 1 ? 'répliques' : 'réplique' }})</span>
                                @else
                                    <span class="muted">Aucun texte</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($conversations->sum('lines') > 0)
                <form method="POST" action="{{ route('admin.conversations.destroy', $user) }}" {!! \App\Support\Confirmation::attributes('transcripts.delete') !!}>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-light"><x-icon name="trash" /> Supprimer les conversations de cet apprenant</button>
                </form>
            @endif
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
