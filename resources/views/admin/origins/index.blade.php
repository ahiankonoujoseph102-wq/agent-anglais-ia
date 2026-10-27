{{-- « Provenance » des inscrits (App\Support\Origins) : aucun outil extérieur, seulement ce que le site a vu. --}}
@php
    use App\Support\Origins;

    $rate = fn (int $part, int $whole) => $whole > 0 ? round(100 * $part / $whole).' %' : '—';
@endphp

<x-layout title="Provenance des inscrits">
    @include('admin.nav')

    <h1>Provenance des inscrits</h1>
    <p class="muted">Par où les inscrits sont arrivés sur le site avant de créer leur compte, puis s'ils ont passé l'évaluation et s'ils se sont abonnés (au moins un paiement validé).
        @if ($since) Mesuré depuis le {{ \Illuminate\Support\Carbon::parse($since)->timezone(config('app.timezone'))->translatedFormat('j F Y') }} : les comptes plus anciens n'ont pas de provenance. @endif</p>

    <nav class="tabs origin-periods" aria-label="Période">
        @foreach (\App\Http\Controllers\Admin\OriginController::PERIODS as $key => $value)
            <a href="{{ route('admin.origins.index', ['jours' => $key]) }}" @class(['active' => $period === $key]) @if ($period === $key) aria-current="page" @endif>{{ $value ? $value.' derniers jours' : 'Depuis le début' }}</a>
        @endforeach
    </nav>

    @if ($stats['total'] === 0)
        <div class="card empty-state"><span class="icon-bubble"><x-icon name="users" /></span><p>Aucune inscription mesurée sur cette période.</p></div>
    @else
        <section class="section-tight" aria-labelledby="par-canal">
            <h2 id="par-canal">Par canal ({{ $stats['total'] }} inscrits)</h2>
            <table class="table">
                <thead><tr><th>Canal</th><th>Inscrits</th><th>Évaluation passée</th><th>Abonnés</th></tr></thead>
                <tbody>
                    @foreach ($stats['channels'] as $row)
                        <tr>
                            <td data-label="Canal">{{ Origins::label($row->channel) }}</td>
                            <td data-label="Inscrits">{{ $row->registered }}</td>
                            <td data-label="Évaluation passée">{{ $row->assessed }} ({{ $rate((int) $row->assessed, (int) $row->registered) }})</td>
                            <td data-label="Abonnés">{{ $row->subscribed }} ({{ $rate((int) $row->subscribed, (int) $row->registered) }})</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="muted small">« WhatsApp » et « TikTok » comptent aussi les liens partagés avec utm_source (bouton « Partager mon niveau », liens de vos bios). Beaucoup d'applications n'indiquent pas d'où vient le visiteur : il est alors compté en « Accès direct ou inconnu ».</p>
        </section>

        <section class="section-tight" aria-labelledby="par-page">
            <h2 id="par-page">Par page d'arrivée</h2>
            <table class="table">
                <thead><tr><th>Page</th><th>Inscrits</th><th>Évaluation passée</th><th>Abonnés</th></tr></thead>
                <tbody>
                    @foreach ($stats['pages'] as $row)
                        <tr>
                            <td data-label="Page"><code>{{ $row->path ?? 'inconnue' }}</code></td>
                            <td data-label="Inscrits">{{ $row->registered }}</td>
                            <td data-label="Évaluation passée">{{ $row->assessed }}</td>
                            <td data-label="Abonnés">{{ $row->subscribed }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="section-tight" aria-labelledby="dernieres">
            <h2 id="dernieres">Dernières inscriptions</h2>
            <table class="table">
                <thead><tr><th>Inscrit</th><th>Canal</th><th>Page d'arrivée</th><th>Évaluation</th><th>Abonné</th></tr></thead>
                <tbody>
                    @foreach ($stats['recent'] as $origin)
                        <tr>
                            <td data-label="Inscrit"><a href="{{ route('admin.users.show', $origin->user) }}">{{ $origin->user->name }}</a><br><span class="muted small">{{ $origin->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span></td>
                            <td data-label="Canal">{{ Origins::label($origin->channel) }}@if ($origin->referrer_host && $origin->channel === 'other')<br><span class="muted small">{{ $origin->referrer_host }}</span>@endif @if ($origin->utm_campaign)<br><span class="muted small">{{ $origin->utm_campaign }}</span>@endif</td>
                            <td data-label="Page d'arrivée"><code>{{ $origin->landing_path ?? 'inconnue' }}</code></td>
                            <td data-label="Évaluation">{{ $origin->user->assessed ? 'Oui' : 'Non' }}</td>
                            <td data-label="Abonné">{{ $origin->user->subscribed ? 'Oui' : 'Non' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</x-layout>
