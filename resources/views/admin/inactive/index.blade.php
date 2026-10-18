<x-layout title="Apprenants inactifs">
    @include('admin.nav')

    <h1>Apprenants inactifs ({{ $learners->count() }})</h1>
    <p class="muted">Abonnés sans séance depuis {{ $days }} jours ou plus. Le bouton ouvre WhatsApp avec un message d'encouragement déjà rédigé.</p>

    @if ($learners->isEmpty())
        <div class="card empty-state"><span class="icon-bubble"><x-icon name="users" /></span><p>Aucun apprenant inactif. Tout le monde travaille !</p></div>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Apprenant</th>
                    <th>Dernière séance</th>
                    <th>Programme</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($learners as $learner)
                    <tr>
                        <td data-label="Apprenant"><a href="{{ route('admin.users.show', $learner) }}">{{ $learner->name }}</a><br><span class="muted small">{{ $learner->phone }}</span></td>
                        <td data-label="Dernière séance">
                            @if ($learner->lessons_max_started_at)
                                {{ \Illuminate\Support\Carbon::parse($learner->lessons_max_started_at)->format('d/m/Y') }}
                            @else
                                Jamais
                            @endif
                            ({{ \App\Http\Controllers\Admin\InactiveLearnerController::inactiveDays($learner) }} j)
                        </td>
                        <td data-label="Programme">
                            @if ($learner->activeProgram)
                                {{ $learner->activeProgram->level->value }} · {{ $learner->activeProgram->goal->label() }}
                            @else
                                Pas encore construit
                            @endif
                        </td>
                        <td>
                            <a class="btn btn-whatsapp" href="{{ route('admin.inactive.whatsapp', $learner) }}" target="_blank" rel="noopener">Encourager sur WhatsApp</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layout>
