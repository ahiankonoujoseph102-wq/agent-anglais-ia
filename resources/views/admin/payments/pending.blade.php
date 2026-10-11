<x-layout title="Paiements en attente">
    @include('admin.nav')

    <h1>Paiements en attente ({{ $payments->count() }})</h1>

    @forelse ($payments as $payment)
        <article class="card payment" id="paiement-{{ $payment->id }}">
            <div class="payment-grid">
                <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank" rel="noopener" class="proof">
                    <img src="{{ route('admin.payments.proof', $payment) }}" alt="Capture du paiement de {{ $payment->user->name }}" loading="lazy">
                </a>

                <div>
                    <h2><a href="{{ route('admin.users.show', $payment->user) }}">{{ $payment->user->name }}</a></h2>
                    <dl class="summary">
                        <dt>Compte</dt><dd><a href="tel:{{ $payment->user->phone }}">{{ $payment->user->phone }}</a></dd>
                        <dt>Formule</dt><dd>{{ $payment->plan_name }} ({{ $payment->duration_days }} jours)</dd>
                        <dt>Montant attendu</dt><dd><strong>{{ $payment->formattedAmount() }}</strong></dd>
                        <dt>Pays</dt><dd>{{ ($countries[$payment->country] ?? null)?->name ?? $payment->country }}</dd>
                        <dt>Opérateur</dt><dd>{{ $payment->operator_name }} → {{ $payment->recipient_number }}</dd>
                        <dt>Numéro qui a payé</dt><dd>{{ $payment->payer_phone }}</dd>
                        <dt>Référence</dt><dd><code>{{ $payment->reference }}</code></dd>
                        <dt>Envoyé le</dt><dd>{{ $payment->created_at->format('d/m/Y H:i') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="payment-actions">
                <form method="POST" action="{{ route('admin.payments.approve', $payment) }}"
                      onsubmit="return confirm('Valider ce paiement et activer la formule ?')">
                    @csrf
                    <button type="submit" class="btn btn-block">Valider</button>
                </form>

                <form method="POST" action="{{ route('admin.payments.reject', $payment) }}">
                    @csrf
                    @php
                        $bag = $errors->getBag('reject-'.$payment->id);
                    @endphp
                    <label for="reason-{{ $payment->id }}">Motif du refus (visible par l'apprenant)</label>
                    <textarea id="reason-{{ $payment->id }}" name="reason" rows="2" required minlength="5" maxlength="500"
                              @class(['is-invalid' => $bag->has('reason')])>{{ $bag->any() ? old('reason') : '' }}</textarea>
                    @if ($bag->has('reason'))
                        <div class="error">{{ $bag->first('reason') }}</div>
                    @endif
                    <button type="submit" class="btn btn-danger btn-block">Refuser</button>
                </form>
            </div>
        </article>
    @empty
        <div class="card">Aucun paiement en attente.</div>
    @endforelse
</x-layout>
