<nav class="tabs" aria-label="Administration">
    <a href="{{ route('admin.payments.pending') }}" @class(['active' => request()->routeIs('admin.payments.pending')]) @if (request()->routeIs('admin.payments.pending')) aria-current="page" @endif>
        Paiements en attente
        <span @class(['badge', 'badge-alert' => $pendingPaymentsCount > 0, 'badge-muted' => $pendingPaymentsCount === 0])>{{ $pendingPaymentsCount }}</span>
    </a>
    <a href="{{ route('admin.payments.index') }}" @class(['active' => request()->routeIs('admin.payments.index')]) @if (request()->routeIs('admin.payments.index')) aria-current="page" @endif>Historique</a>
    <a href="{{ route('admin.inactive.index') }}" @class(['active' => request()->routeIs('admin.inactive.*')]) @if (request()->routeIs('admin.inactive.*')) aria-current="page" @endif>Inactifs</a>
    <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')]) @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>Inscrits</a>
    <a href="{{ route('admin.usage.index') }}" @class(['active' => request()->routeIs('admin.usage.*')]) @if (request()->routeIs('admin.usage.*')) aria-current="page" @endif>Consommation</a>
    <a href="{{ route('admin.settings.edit') }}" @class(['active' => request()->routeIs('admin.settings.*')]) @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif>Réglages</a>
    <a href="{{ route('admin.voices.index') }}" @class(['active' => request()->routeIs('admin.voices.*')]) @if (request()->routeIs('admin.voices.*')) aria-current="page" @endif>Essai des voix</a>
</nav>
