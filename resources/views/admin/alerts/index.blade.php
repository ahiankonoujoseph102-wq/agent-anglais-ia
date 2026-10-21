<x-layout title="Alertes">
    @include('admin.nav')

    <h1>Alertes de paiement</h1>
    <p>
        Dès qu'une capture de paiement arrive, qu'un accès provisoire va se terminer sans validation, ou qu'un apprenant
        d'un pays sans paiement en ligne vous écrit sur WhatsApp, vous êtes prévenu par une notification sur votre
        téléphone et par e-mail.
    </p>

    @if ($result = session('alert_result'))
        <div @class(['alert', 'alert-success' => $result['ok'], 'alert-error' => ! $result['ok']]) role="status">
            <x-icon :name="$result['ok'] ? 'check-circle' : 'alert'" />
            <div>{{ $result['text'] }}</div>
        </div>
    @endif

    <section class="card" id="notifications" data-alerts data-public-key="{{ $publicKey }}" data-subscribe-url="{{ route('admin.alerts.subscribe') }}" data-unsubscribe-url="{{ route('admin.alerts.unsubscribe') }}">
        <h2>Notifications sur le téléphone</h2>
        <p>
            État :
            <strong>{{ $pushReady ? 'réglées' : 'non réglées' }}</strong>
            @unless ($pushReady)
                <span class="muted">(les variables VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY et VAPID_SUBJECT manquent : voir le guide de mise en ligne)</span>
            @endunless
        </p>
        <p>Téléphones abonnés : <strong>{{ $devices->count() }}</strong></p>
        @if ($devices->isNotEmpty())
            <ul>
                @foreach ($devices as $device)
                    <li>
                        {{ $device->device ?? 'Appareil' }}
                        <span class="muted">({{ $device->user?->name }}, ajouté le {{ $device->created_at->timezone(config('app.timezone'))->translatedFormat('j F Y') }}@if ($device->last_sent_at), dernière notification le {{ $device->last_sent_at->timezone(config('app.timezone'))->translatedFormat('j F Y \à H \h i') }}@endif)</span>
                        <form method="POST" action="{{ route('admin.alerts.destroy', $device) }}" class="inline-form">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-light">Retirer</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="alert alert-error" data-alerts-note hidden role="status"></p>

        <div class="voice-actions">
            @if ($pushReady)
                <button type="button" class="btn btn-accent" data-alerts-enable hidden><x-icon name="alert" /> Recevoir les alertes sur ce téléphone</button>
                <button type="button" class="btn btn-light" data-alerts-disable hidden>Arrêter les alertes sur ce téléphone</button>
            @endif
            <form method="POST" action="{{ route('admin.alerts.test-push') }}">
                @csrf
                <button type="submit" class="btn btn-light"><x-icon name="play" /> Envoyer une alerte d'essai</button>
            </form>
        </div>
        <p class="muted small">
            Sur Android, installez d'abord l'application {{ config('platform.name') }} (bouton « Installer l'application »), ouvrez-la,
            puis touchez « Recevoir les alertes sur ce téléphone » et acceptez la permission.
        </p>
    </section>

    <section class="card" id="email">
        <h2>E-mail</h2>
        <p>
            État :
            <strong>{{ $mailReady ? 'réglé ('.$emails.' adresse'.($emails > 1 ? 's' : '').')' : 'non réglé' }}</strong>
            @unless ($mailReady)
                <span class="muted">(il faut ADMIN_ALERT_EMAIL, MAIL_FROM_ADDRESS et les réglages SMTP : seules les notifications fonctionnent pour l'instant)</span>
            @endunless
        </p>
        <form method="POST" action="{{ route('admin.alerts.test-mail') }}">
            @csrf
            <button type="submit" class="btn btn-light"><x-icon name="mail" /> Envoyer un e-mail d'essai</button>
        </form>
        <p class="muted small">Le premier e-mail envoyé depuis une adresse Gmail peut arriver dans les courriers indésirables : ouvrez-le et touchez « Pas du spam » une fois.</p>
    </section>

    <section class="card" id="documents-revision">
        <h2>E-mails des documents de révision</h2>
        <p>
            Envoi aux élèves : <strong>{{ $mailQuota['configured'] ? 'réglé' : 'non réglé' }}</strong>
            @unless ($mailQuota['configured'])
                <span class="muted">(MAIL_FROM_ADDRESS et les réglages SMTP : les documents restent téléchargeables dans « Mes cours »)</span>
            @endunless
        </p>
        <p>
            Aujourd'hui : <strong>{{ $mailQuota['used'] }} {{ $mailQuota['used'] > 1 ? 'e-mails' : 'e-mail' }} sur {{ $mailQuota['limit'] }}</strong> (documents, invitations et alertes ensemble) ·
            documents envoyés : {{ $reviewMail['sent'] }}, en échec : {{ $reviewMail['failed'] }}, non envoyés (limite) : {{ $reviewMail['quota'] }}.
        </p>
        <p class="muted small">Au plus {{ $mailQuota['reviews'] }} e-mails de documents par jour : les {{ $mailQuota['limit'] - $mailQuota['reviews'] }} derniers restent gardés pour vos alertes. Au-delà, le document reste téléchargeable et vous êtes prévenu une fois dans la journée.</p>
    </section>

    @if ($invitations['total'] > 0)
        <section class="card" id="invitations">
            <h2>Invitations à refaire l'évaluation</h2>
            <p>
                Envoyées : <strong>{{ $invitations['sent'] }}</strong> ·
                restantes : <strong>{{ $invitations['pending'] }}</strong> ·
                en échec : {{ $invitations['failed'] }} ·
                sans adresse e-mail : {{ $invitations['no_email'] }} ·
                aujourd'hui : {{ $invitations['today'] }}
            </p>
            <p class="muted small">Après une remise à zéro (app:reset-learners --notify). Envoyées chaque heure, dans la même marge que les documents de révision : les e-mails gardés pour vos alertes ne sont jamais pris. Les comptes sans adresse e-mail ne reçoivent rien.</p>
        </section>
    @endif

    <section class="card" id="journal">
        <h2>Dernières alertes</h2>
        @if ($recent->isEmpty())
            <p class="muted">Aucune alerte pour l'instant.</p>
        @else
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Alerte</th><th>Canal</th><th>Résultat</th></tr>
                </thead>
                <tbody>
                    @foreach ($recent as $alert)
                        <tr>
                            <td>{{ $alert->created_at->timezone(config('app.timezone'))->translatedFormat('j F Y \à H \h i') }}</td>
                            <td>{{ $alert->title }}<br><span class="muted small">{{ \App\Models\AdminAlert::KINDS[$alert->kind] ?? $alert->kind }}</span></td>
                            <td>{{ \App\Models\AdminAlert::CHANNELS[$alert->channel] ?? $alert->channel }}</td>
                            <td>
                                @if ($alert->status === \App\Models\AdminAlert::SENT)
                                    Envoyée
                                @else
                                    <strong>Échec</strong> : {{ $alert->reason }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <script type="module" src="{{ \App\Support\Asset::url('js/admin-alerts.js') }}"></script>
</x-layout>
