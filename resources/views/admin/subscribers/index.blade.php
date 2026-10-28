@php
    use App\Support\Admin\Subscribers;
    use App\Support\Money;
    use App\Support\PhoneNumber;
@endphp
<x-layout title="Abonnés payants">
    @include('admin.nav')

    <h1>Abonnés payants</h1>
    <p class="muted">Les ambassadeurs et les autres accès offerts sont comptés à part, plus bas : ils ne faussent pas les chiffres des payants.</p>

    <div class="stats">
        <div class="stat">
            <div class="label">Abonnés payants actifs</div>
            <div class="value">{{ $totals['paid'] }}</div>
            <div class="detail">
                @foreach ($totals['by_plan'] as $plan)
                    {{ $plan['name'] }} : {{ $plan['count'] }}@if (! $loop->last) · @endif
                @endforeach
                @if ($totals['provisional'] > 0)
                    <br>dont {{ $totals['provisional'] }} en accès provisoire (paiement à vérifier)
                @endif
            </div>
        </div>
        <div class="stat">
            <div class="label">Encaissé en {{ $monthLabel }}</div>
            <div class="value">{{ $monthTotal }}</div>
            <div class="detail">paiements validés ce mois-ci</div>
        </div>
        <div class="stat">
            <div class="label">À part (gratuit)</div>
            <div class="value">{{ $totals['ambassador'] + $totals['granted'] }}</div>
            <div class="detail">Ambassadeurs : {{ $totals['ambassador'] }} · Accès offerts : {{ $totals['granted'] }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.subscribers.index') }}" class="card filters next-step">
        <div class="field">
            <label for="statut">Abonnements</label>
            <select id="statut" name="statut">
                @foreach (Subscribers::FILTERS as $value => $label)
                    <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="tri">Tri par date de fin</label>
            <select id="tri" name="tri">
                @foreach (Subscribers::SORTS as $value => $label)
                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn">Filtrer</button>
    </form>

    <p class="muted small">Le bouton WhatsApp ouvre un message de relance déjà rédigé quand la formule se termine dans moins de {{ $reminderDays }} jours ou qu'elle est terminée ; sinon, une simple salutation.</p>

    @foreach (Subscribers::GROUPS as $group => $title)
        @php
            $rows = $groups[$group];
        @endphp
        <section class="subscribers-group" id="{{ $group === 'paid' ? 'payants' : ($group === 'ambassador' ? 'ambassadeurs' : 'acces-offerts') }}">
            <h2>{{ $title }} ({{ $rows->count() }})</h2>

            @if ($rows->isEmpty())
                <div class="card empty-state"><span class="icon-bubble"><x-icon name="users" /></span><p>Aucun abonnement dans cette liste.</p></div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Prénom</th>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Formule</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Jours restants</th>
                            <th>Montant payé</th>
                            <th>Provenance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $subscription)
                            @php
                                [$firstName, $lastName] = Subscribers::names($subscription->user->name);
                                $days = Subscribers::daysLeft($subscription);
                                $paid = Subscribers::paid($subscription);
                                $soon = Subscribers::endsSoon($subscription);
                            @endphp
                            <tr>
                                <td data-label="Prénom"><a href="{{ route('admin.users.show', $subscription->user) }}">{{ $firstName !== '' ? $firstName : $subscription->user->name }}</a></td>
                                <td data-label="Nom">{{ $lastName !== '' ? $lastName : '—' }}</td>
                                <td data-label="Téléphone"><span class="nowrap">{{ PhoneNumber::display($subscription->user->phone) }}</span></td>
                                <td data-label="Formule">
                                    {{ $subscription->plan_name }}
                                    @if ($subscription->isProvisional())
                                        <span class="badge badge-muted">Accès provisoire</span>
                                    @endif
                                </td>
                                <td data-label="Début">{{ $subscription->starts_at->format('d/m/Y') }}</td>
                                <td data-label="Fin">{{ $subscription->ends_at->format('d/m/Y') }}</td>
                                <td data-label="Jours restants">
                                    @if ($days === null)
                                        <span class="muted">Terminé</span>
                                    @elseif ($days === 0)
                                        <span class="badge badge-alert">Se termine aujourd'hui</span>
                                    @else
                                        <span @class(['badge badge-alert' => $soon])>{{ $days }} {{ $days === 1 ? 'jour' : 'jours' }}</span>
                                    @endif
                                </td>
                                <td data-label="Montant payé">
                                    @if ($paid === null)
                                        Gratuit
                                    @else
                                        <span class="nowrap">{{ Money::format($paid['amount']) }}</span>
                                        @if ($paid['pending'])
                                            <br><span class="muted small">en vérification</span>
                                        @endif
                                    @endif
                                </td>
                                <td data-label="Provenance">{{ Subscribers::origin($subscription) }}</td>
                                <td>
                                    <a class="btn btn-whatsapp" href="{{ Subscribers::whatsappUrl($subscription) }}" target="_blank" rel="noopener">
                                        {{ $group !== 'ambassador' && ($soon || $days === null) ? 'Relancer sur WhatsApp' : 'Écrire sur WhatsApp' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
</x-layout>
