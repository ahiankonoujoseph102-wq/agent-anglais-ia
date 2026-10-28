@php
    use App\Support\Ambassadors\AmbassadorProgram;
    use App\Support\Confirmation;
@endphp

<x-layout title="Ambassadeurs">
    @include('admin.nav')

    <h1>Ambassadeurs</h1>
    <p class="lead"><strong>Ambassadeurs actifs : {{ $activeCount }} sur {{ $places }}</strong></p>
    <p class="muted small">
        Le nombre de places se change dans <a href="{{ route('admin.settings.edit') }}#ambassadeurs">Réglages</a>.
        Pour nommer un ambassadeur : ouvrez sa fiche dans « Inscrits », puis « Faire ambassadeur ».
        Mois gratuit : formule {{ \App\Support\Plan::find((string) config('platform.ambassadors.plan'))?->name }}, {{ config('platform.ambassadors.month_days') }} jours, {{ config('platform.ambassadors.max_months') }} mois au plus.
    </p>

    @error('ambassador')
        <p class="alert alert-error" role="alert"><x-icon name="alert" /><span>{{ $message }}</span></p>
    @enderror

    {{-- Candidatures de la page « Devenir ambassadeur » : accepter = « Faire ambassadeur » (refusé si tout est pris). --}}
    <section class="card" id="candidatures" aria-labelledby="candidatures-title">
        <h2 id="candidatures-title">Candidatures à examiner : {{ $applications->count() }}</h2>
        <p class="muted small">
            Envoyées depuis la page <a href="{{ route('ambassador.join') }}">« Devenir ambassadeur »</a>.
            Places libres : {{ $freePlaces }} sur {{ $places }}.
        </p>
        @forelse ($applications as $application)
            <div class="commission-row">
                <p>
                    <a href="{{ route('admin.users.show', $application->user) }}"><strong>{{ $application->user->name }}</strong></a>
                    · {{ $application->user->countryName() }} · envoyée le {{ $application->created_at->format('d/m/Y') }}
                    @if ($application->minor)
                        <br><span class="badge badge-alert">Moins de 18 ans : accord écrit d'un parent à demander</span>
                    @endif
                </p>
                <p class="small"><strong>Pourquoi :</strong> {{ $application->motivation }}</p>
                <p class="small"><strong>Va en parler à :</strong> {{ $application->channels }}</p>
                <div class="ambassador-actions">
                    @if ($freePlaces > 0)
                        <form method="POST" action="{{ route('admin.applications.accept', $application) }}" {!! Confirmation::attributes('ambassador.appoint') !!}>
                            @csrf
                            <button type="submit" class="btn btn-accent"><x-icon name="award" /> Accepter</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.applications.reject', $application) }}" {!! Confirmation::attributes('application.reject') !!}>
                        @csrf
                        <button type="submit" class="btn btn-light"><x-icon name="close-circle" /> Refuser</button>
                    </form>
                </div>
                @if ($freePlaces === 0)
                    <p class="muted small">Toutes les places sont prises : retirez un ambassadeur ou augmentez le nombre de places pour accepter.</p>
                @endif
            </div>
        @empty
            <p class="muted">Aucune candidature à examiner.</p>
        @endforelse
    </section>

    {{-- Liste d'attente : « Me prévenir quand une place se libère ». L'administrateur prévient par WhatsApp. --}}
    <section class="card" id="liste-d-attente" aria-labelledby="attente-title">
        <h2 id="attente-title">Liste d'attente : {{ $waitlist->count() }}</h2>
        @if ($freePlaces > 0 && $waitlist->isNotEmpty())
            <p class="alert alert-info"><x-icon name="info" /><span>{{ $freePlaces > 1 ? $freePlaces.' places sont libres' : 'Une place est libre' }} : prévenez les personnes de la liste, dans l'ordre d'arrivée.</span></p>
        @endif
        @forelse ($waitlist as $entry)
            <div class="commission-row">
                <p>
                    {{ $loop->iteration }}. <a href="{{ route('admin.users.show', $entry->user) }}">{{ $entry->user->name }}</a>
                    · inscrit le {{ $entry->created_at->format('d/m/Y') }}
                    @if ($entry->notified_at)
                        <br><span class="muted small">Prévenu le {{ $entry->notified_at->format('d/m/Y') }}</span>
                    @endif
                </p>
                <div class="ambassador-actions">
                    <a class="btn btn-whatsapp" href="{{ route('admin.waitlist.notify', $entry) }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Prévenir sur WhatsApp</a>
                    <form method="POST" action="{{ route('admin.waitlist.remove', $entry) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-light"><x-icon name="user-off" /> Retirer de la liste</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="muted">Personne sur la liste d'attente.</p>
        @endforelse
    </section>

    <section id="liste" aria-label="Liste des ambassadeurs">
        @if ($ambassadors->isEmpty())
            <div class="card empty-state">
                <span class="icon-bubble"><x-icon name="award" /></span>
                <p>Aucun ambassadeur pour le moment. Ouvrez la fiche d'un inscrit pour le nommer.</p>
                <a class="btn btn-outline" href="{{ route('admin.users.index') }}"><x-icon name="users" /> Voir les inscrits</a>
            </div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Ambassadeur</th>
                        <th>Code</th>
                        <th>Inscrits</th>
                        <th>Commissions</th>
                        <th>Mois gratuit</th>
                        <th>Séances du jour</th>
                        <th>Accord d'un parent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ambassadors as $ambassador)
                        @php
                            $grant = $ambassador->currentGrant();
                            $seen = $activity[$ambassador->user_id];
                        @endphp
                        <tr>
                            <td data-label="Ambassadeur">
                                <a href="{{ route('admin.users.show', $ambassador->user) }}">{{ $ambassador->user->name }}</a>
                                @unless ($ambassador->isActive())
                                    <br><span class="badge badge-muted">{{ $ambassador->status->label() }}</span>
                                @endunless
                            </td>
                            <td data-label="Code"><strong>{{ $ambassador->code }}</strong></td>
                            <td data-label="Inscrits">
                                {{ $ambassador->referrals_count }}
                                @if ($ambassador->flagged_count > 0)
                                    <br><span class="badge badge-alert">{{ $ambassador->flagged_count }} à vérifier</span>
                                @endif
                            </td>
                            <td data-label="Commissions">
                                {{ $totals[$ambassador->id]['subscribers'] }} {{ $totals[$ambassador->id]['subscribers'] > 1 ? 'abonnés payants' : 'abonné payant' }}
                                <br><span class="small">À payer : {{ \App\Support\Money::format($totals[$ambassador->id]['due']) }}</span>
                                <br><span class="small muted">Déjà payé : {{ \App\Support\Money::format($totals[$ambassador->id]['paid']) }}</span>
                            </td>
                            <td data-label="Mois gratuit">{{ AmbassadorProgram::monthLabel($ambassador, $grant) }}</td>
                            <td data-label="Séances du jour">
                                {{ $seen['days'] }} {{ $seen['days'] > 1 ? 'jours' : 'jour' }} sur les {{ config('platform.ambassadors.activity_days') }} derniers
                                @if ($seen['last'])
                                    <br><span class="muted small">Dernière : {{ $seen['last']->translatedFormat('j F Y') }}</span>
                                @endif
                            </td>
                            <td data-label="Accord d'un parent">{{ $ambassador->parental_consent_at ? 'Reçu le '.$ambassador->parental_consent_at->format('d/m/Y') : '—' }}</td>
                            <td data-label="Actions">
                                <div class="ambassador-actions">
                                    @if (AmbassadorProgram::canRenew($ambassador))
                                        <form method="POST" action="{{ route('admin.ambassadors.renew', $ambassador) }}" {!! Confirmation::attributes('ambassador.renew') !!}>
                                            @csrf
                                            <input type="hidden" name="from" value="list">
                                            <button type="submit" class="btn btn-accent"><x-icon name="refresh" /> Renouveler 1 mois</button>
                                        </form>
                                    @endif
                                    @if ($ambassador->isActive())
                                        <form method="POST" action="{{ route('admin.ambassadors.remove', $ambassador) }}" {!! Confirmation::attributes('ambassador.remove') !!}>
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="from" value="list">
                                            <button type="submit" class="btn btn-light"><x-icon name="user-off" /> Retirer</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.ambassador', $ambassador->user) }}" {!! Confirmation::attributes('ambassador.appoint') !!}>
                                            @csrf
                                            <input type="hidden" name="from" value="list">
                                            <button type="submit" class="btn btn-outline"><x-icon name="award" /> Réintégrer</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Commissions à payer : versées par Mobile Money, puis « Commission payée ». Annulation seulement pour triche. --}}
    <section class="card next-step" id="commissions" aria-labelledby="commissions-title">
        <h2 id="commissions-title">Commissions à payer : {{ \App\Support\Money::format((int) $dueCommissions->sum('amount')) }}</h2>
        <p class="muted small">
            {{ config('platform.ambassadors.commission_percent') }} % du prix de la formule, sur le premier paiement validé d'un inscrit, dans les {{ config('platform.ambassadors.commission_days') }} jours suivant son inscription.
            Vérifiez les signaux avant de payer.
        </p>
        @forelse ($dueCommissions as $commission)
            <div class="commission-row">
                <p>
                    <strong>{{ \App\Support\Money::format($commission->amount) }}</strong> pour
                    <a href="{{ route('admin.users.show', $commission->ambassador->user) }}">{{ $commission->ambassador->user->name }}</a> ({{ $commission->ambassador->code }}),
                    grâce à <a href="{{ route('admin.users.show', $commission->user) }}">{{ $commission->user->name }}</a> :
                    formule {{ $commission->plan_name }}, {{ $commission->source === 'manual' ? 'ajoutée à la main' : 'paiement validé' }} le {{ $commission->created_at->format('d/m/Y') }}.
                    @if ($commission->note)
                        <br><span class="muted small">{{ $commission->note }}</span>
                    @endif
                </p>
                @php($signals = [...($commission->user->referral?->flagLabels() ?? []), ...$commission->flagLabels()])
                @if ($signals !== [])
                    <ul class="referral-flags">
                        @foreach ($signals as $label)
                            <li>À vérifier : {{ $label }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="ambassador-actions">
                    <form method="POST" action="{{ route('admin.commissions.pay', $commission) }}" {!! Confirmation::attributes('commission.pay') !!}>
                        @csrf
                        <input type="hidden" name="from" value="commissions">
                        <button type="submit" class="btn btn-accent"><x-icon name="check-circle" /> Commission payée</button>
                    </form>
                    <form method="POST" action="{{ route('admin.commissions.cancel', $commission) }}" class="commission-cancel" {!! Confirmation::attributes('commission.cancel') !!}>
                        @csrf
                        <input type="hidden" name="from" value="commissions">
                        <label for="reason-{{ $commission->id }}" class="sr-only">Motif de l'annulation</label>
                        <input id="reason-{{ $commission->id }}" name="reason" type="text" maxlength="200" required placeholder="Motif (triche)">
                        <button type="submit" class="btn btn-light"><x-icon name="close-circle" /> Annuler</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="muted">Aucune commission à payer.</p>
        @endforelse
    </section>
</x-layout>
