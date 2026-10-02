<x-layout title="Inscrits">
    <h1>Inscrits</h1>
    <p class="muted">{{ $total }} compte(s) au total.</p>

    <form method="GET" action="{{ route('admin.users.index') }}" class="search" role="search">
        <input id="q" type="search" aria-label="Rechercher un inscrit" name="q" value="{{ $search }}" placeholder="Nom ou numéro">
        <button type="submit" class="btn">Rechercher</button>
    </form>

    @if ($users->isEmpty())
        <div class="card">Aucun inscrit{{ $search !== '' ? ' ne correspond à cette recherche' : ' pour le moment' }}.</div>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Téléphone</th>
                    <th>Niveau</th>
                    <th>Formule</th>
                    <th>Rôle</th>
                    <th>Inscription</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td data-label="Nom">{{ $user->name }}</td>
                        <td data-label="Téléphone"><a href="tel:{{ $user->phone }}">{{ $user->phone }}</a></td>
                        <td data-label="Niveau">
                            @if ($user->level)
                                <span class="badge">{{ $user->level->value }}</span>
                            @else
                                <span class="badge badge-muted">—</span>
                            @endif
                        </td>
                        <td data-label="Formule">{{ $user->activeSubscription?->plan_name ?? '—' }}</td>
                        <td data-label="Rôle">{{ $user->role->label() }}</td>
                        <td data-label="Inscription">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $users->links() }}
    @endif
</x-layout>
