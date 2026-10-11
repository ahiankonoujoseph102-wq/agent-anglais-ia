<x-layout title="Historique des paiements">
    @include('admin.nav')

    <h1>Historique des paiements</h1>

    <div class="stats">
        <div class="stat">
            <div class="label">Encaissé en {{ $monthLabel }}</div>
            <div class="value">{{ $monthTotal }}</div>
            <div class="detail">paiements validés ce mois-ci</div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="card filters next-step">
        <div class="field">
            <label for="statut">Statut</label>
            <select id="statut" name="statut">
                <option value="">Tous</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['statut'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="pays">Pays</label>
            <select id="pays" name="pays">
                <option value="">Tous</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->code }}" @selected(($filters['pays'] ?? '') === $country->code)>{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="du">Du</label>
            <input id="du" type="date" name="du" value="{{ $filters['du'] ?? '' }}">
        </div>
        <div class="field">
            <label for="au">Au</label>
            <input id="au" type="date" name="au" value="{{ $filters['au'] ?? '' }}">
        </div>
        <button type="submit" class="btn">Filtrer</button>
    </form>

    @if ($payments->isEmpty())
        <div class="card">Aucun paiement ne correspond à ces critères.</div>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Envoyé le</th>
                    <th>Apprenant</th>
                    <th>Formule</th>
                    <th>Montant</th>
                    <th>Pays / opérateur</th>
                    <th>Référence</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td data-label="Envoyé le">{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                        <td data-label="Apprenant"><a href="{{ route('admin.users.show', $payment->user) }}">{{ $payment->user->name }}</a></td>
                        <td data-label="Formule">{{ $payment->plan_name }}</td>
                        <td data-label="Montant">{{ $payment->formattedAmount() }}</td>
                        <td data-label="Pays / opérateur">{{ ($countries[$payment->country] ?? null)?->name ?? $payment->country }} · {{ $payment->operator_name }}</td>
                        <td data-label="Référence"><code>{{ $payment->reference }}</code> · <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank" rel="noopener">capture</a></td>
                        <td data-label="Statut">
                            <span @class(['badge', 'badge-muted' => $payment->status !== \App\Enums\PaymentStatus::Validated])>{{ $payment->status->label() }}</span>
                            @if ($payment->rejection_reason)
                                <div class="small muted">{{ $payment->rejection_reason }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $payments->links() }}
    @endif
</x-layout>
