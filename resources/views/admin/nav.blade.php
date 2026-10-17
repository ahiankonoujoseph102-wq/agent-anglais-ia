<nav class="tabs" aria-label="Administration">
    <a href="{{ route('admin.payments.pending') }}" @class(['active' => request()->routeIs('admin.payments.pending')])>
        Paiements en attente
        <span @class(['badge', 'badge-alert' => $pendingPaymentsCount > 0, 'badge-muted' => $pendingPaymentsCount === 0])>{{ $pendingPaymentsCount }}</span>
    </a>
    <a href="{{ route('admin.payments.index') }}" @class(['active' => request()->routeIs('admin.payments.index')])>Historique</a>
    <a href="{{ route('admin.inactive.index') }}" @class(['active' => request()->routeIs('admin.inactive.*')])>Inactifs</a>
    <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')])>Inscrits</a>
    <a href="{{ route('admin.usage.index') }}" @class(['active' => request()->routeIs('admin.usage.*')])>Consommation</a>
    <a href="{{ route('admin.settings.edit') }}" @class(['active' => request()->routeIs('admin.settings.*')])>Réglages</a>
    <a href="{{ route('admin.voices.index') }}" @class(['active' => request()->routeIs('admin.voices.*')])>Essai des voix</a>
</nav>
