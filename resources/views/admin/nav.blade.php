<nav class="tabs" aria-label="Administration">
    <a href="{{ route('admin.payments.pending') }}" @class(['active' => request()->routeIs('admin.payments.pending')]) @if (request()->routeIs('admin.payments.pending')) aria-current="page" @endif>
        <x-icon name="wallet" /> Paiements en attente
        <span @class(['badge', 'badge-alert' => $pendingPaymentsCount > 0, 'badge-muted' => $pendingPaymentsCount === 0])>{{ $pendingPaymentsCount }}</span>
    </a>
    <a href="{{ route('admin.alerts.index') }}" @class(['active' => request()->routeIs('admin.alerts.*')]) @if (request()->routeIs('admin.alerts.*')) aria-current="page" @endif><x-icon name="alert" /> Alertes</a>
    <a href="{{ route('admin.payments.index') }}" @class(['active' => request()->routeIs('admin.payments.index')]) @if (request()->routeIs('admin.payments.index')) aria-current="page" @endif><x-icon name="history" /> Historique</a>
    <a href="{{ route('admin.inactive.index') }}" @class(['active' => request()->routeIs('admin.inactive.*')]) @if (request()->routeIs('admin.inactive.*')) aria-current="page" @endif><x-icon name="user-off" /> Inactifs</a>
    <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')]) @if (request()->routeIs('admin.users.*')) aria-current="page" @endif><x-icon name="users" /> Inscrits</a>
    <a href="{{ route('admin.videos.index') }}" @class(['active' => request()->routeIs('admin.videos.*')]) @if (request()->routeIs('admin.videos.*')) aria-current="page" @endif><x-icon name="play" /> Vidéos</a>
    <a href="{{ route('admin.usage.index') }}" @class(['active' => request()->routeIs('admin.usage.*')]) @if (request()->routeIs('admin.usage.*')) aria-current="page" @endif><x-icon name="chart" /> Consommation</a>
    <a href="{{ route('admin.settings.edit') }}" @class(['active' => request()->routeIs('admin.settings.*')]) @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif><x-icon name="settings" /> Réglages</a>
    <a href="{{ route('admin.voices.index') }}" @class(['active' => request()->routeIs('admin.voices.*')]) @if (request()->routeIs('admin.voices.*')) aria-current="page" @endif><x-icon name="headphones" /> Essai des voix</a>
    <a href="{{ route('admin.voice-diagnostic.index') }}" @class(['active' => request()->routeIs('admin.voice-diagnostic.*')]) @if (request()->routeIs('admin.voice-diagnostic.*')) aria-current="page" @endif><x-icon name="mic" /> Diagnostic de la voix</a>
    <a href="{{ route('admin.text-diagnostic.index') }}" @class(['active' => request()->routeIs('admin.text-diagnostic.*')]) @if (request()->routeIs('admin.text-diagnostic.*')) aria-current="page" @endif><x-icon name="file-text" /> Diagnostic des fournisseurs</a>
    <a href="{{ route('admin.icons.index') }}" @class(['active' => request()->routeIs('admin.icons.*')]) @if (request()->routeIs('admin.icons.*')) aria-current="page" @endif><x-icon name="palette" /> Aperçu des icônes</a>
</nav>
