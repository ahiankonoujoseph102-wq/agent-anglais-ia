<x-layout title="Paiements en attente">
    @include('admin.nav')

    <h1>Paiements en attente ({{ $payments->count() }})</h1>
    <p class="muted">
        Accès provisoires compris : l'apprenant suit déjà ses séances quand toutes les règles sont remplies.
        Validez pour rendre la formule définitive (aucun jour perdu), refusez pour couper l'accès aussitôt.
    </p>

    @forelse ($payments as $payment)
        @include('admin.payments.card', ['payment' => $payment, 'actions' => true])
    @empty
        <div class="card">Aucun paiement en attente.</div>
    @endforelse
</x-layout>
