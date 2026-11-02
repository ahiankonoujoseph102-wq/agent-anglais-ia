<x-layout title="Relances">
    @include('admin.nav')

    <h1>Relances des inscrits</h1>
    <p class="muted">
        Inscrits qui n'ont pas encore de formule payée ({{ $limit }} plus récents). Les e-mails partent tout seuls, une fois par étape
        (entre 8 heures et 20 heures) ; pour ceux qui n'ont pas d'adresse e-mail, touchez « Écrire sur WhatsApp » : le message est déjà écrit.
        Une relance WhatsApp par étape suffit : n'insistez pas.
    </p>
    @unless ($enabled)
        <div class="alert alert-warning" role="status"><x-icon name="alert" /><div>Les e-mails de relance sont désactivés (LEARNER_FOLLOW_UPS).</div></div>
    @endunless
    <p class="muted small">E-mails de relance envoyés aujourd'hui : {{ $sentToday }} · en attente : {{ $pending }}</p>
    <p class="muted small">
        Relances pour choisir une formule (après l'essai, et anciens abonnés dont la formule est finie : trois relances espacées, puis plus rien) :
        {{ config('platform.follow_ups.sales.per_day') }} au plus par jour d'envoi, un jour sur {{ config('platform.follow_ups.sales.every_days') }}.
        Aujourd'hui : {{ $salesToday }} · en attente : {{ $salesPending }} · anciens abonnés relancés sur 30 jours : {{ $lapsedSent }}.
        Chaque heure, un résumé des e-mails partis vers les élèves est envoyé à l'adresse des alertes.
    </p>

    <nav class="tabs" aria-label="Étapes">
        <a href="{{ route('admin.follow-ups.index') }}" @class(['active' => $filter === null])>Tous ({{ $counts->sum() }})</a>
        @foreach ($labels as $key => $label)
            <a href="{{ route('admin.follow-ups.index', ['etape' => $key]) }}" @class(['active' => $filter === $key])>{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
        @endforeach
    </nav>

    @if ($rows->isEmpty())
        <div class="card empty-state"><span class="icon-bubble"><x-icon name="users" /></span><p>Personne à relancer pour le moment.</p></div>
    @else
        <table class="table">
            <thead>
                <tr><th>Inscrit</th><th>Étape</th><th>E-mail</th><th>WhatsApp</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php($user = $row['user'])
                    <tr>
                        <td data-label="Inscrit">
                            <a href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a><br>
                            <span class="muted small">{{ \App\Support\PhoneNumber::display($user->phone) }} · inscrit le {{ $user->created_at->format('d/m/Y') }}</span>
                        </td>
                        <td data-label="Étape">{{ $labels[$row['stage']] }}</td>
                        <td data-label="E-mail">
                            @if ($user->follow_up_opt_out_at)
                                Ne veut plus d'e-mails
                            @elseif (blank($user->email))
                                Pas d'adresse
                            @elseif ($row['mail'])
                                {{ match ($row['mail']->status) { 'sent' => 'Envoyé le '.$row['mail']->sent_at->format('d/m/Y'), 'pending' => 'En attente', 'failed' => 'En échec', default => 'Rien à envoyer' } }}
                            @else
                                Rien encore
                            @endif
                        </td>
                        <td data-label="WhatsApp">
                            @if ($row['whatsapp'])
                                <span class="muted small">Relancé le {{ $row['whatsapp']->sent_at->format('d/m/Y') }}</span><br>
                            @endif
                            <a class="btn btn-whatsapp" href="{{ route('admin.follow-ups.whatsapp', $user) }}" target="_blank" rel="noopener">Écrire sur WhatsApp</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layout>
