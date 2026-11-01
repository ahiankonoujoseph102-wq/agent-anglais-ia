@php
    use App\Support\Admin\LearnerProgress;
    use App\Support\Duration;
@endphp

<x-layout title="Suivi des élèves">
    @include('admin.nav')

    <h1>Suivi des élèves</h1>
    <p class="muted">Élèves avec un accès en cours. Aujourd'hui = jour de Lomé, comme le temps du jour. Touchez un nom pour voir toute sa progression et son travail.</p>

    <div class="follow-counts">
        <div class="card"><strong>{{ $counts['done'] }}</strong> séance faite aujourd'hui</div>
        <div class="card"><strong>{{ $counts['in_progress'] }}</strong> séance en cours</div>
        <div class="card"><strong>{{ $counts['not_yet'] }}</strong> pas encore de séance<br><span class="muted small">dont {{ $counts['not_yet_working'] }} un jour de travail</span></div>
        <div class="card"><strong>{{ $counts['total'] }}</strong> élèves suivis</div>
    </div>

    <form method="GET" action="{{ route('admin.follow.index') }}" class="search" role="search">
        <select name="aujourdhui" aria-label="Séance d'aujourd'hui">
            <option value="">Tous les élèves</option>
            @foreach ($filters as $value => $label)
                <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Afficher</button>
    </form>

    @if ($rows->isEmpty())
        <div class="card empty-state"><span class="icon-bubble"><x-icon name="users" /></span><p>Aucun élève{{ $filter ? ' dans cette liste' : ' avec un accès en cours' }} pour le moment.</p></div>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Élève</th>
                    <th>Aujourd'hui</th>
                    <th>Temps utilisé aujourd'hui</th>
                    <th>Dernière séance</th>
                    <th>Série</th>
                    <th>Programme</th>
                    <th>Jours manqués (7 jours)</th>
                    <th>Erreurs à revoir</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php($today = $row['today'])
                    <tr>
                        <td data-label="Élève">
                            <a href="{{ route('admin.users.show', $row['user']).'#progression' }}">{{ $row['user']->name }}</a>
                            <br><span class="muted small">{{ $row['user']->activeSubscription?->plan_name }} · {{ $row['teacher'] }}@if ($row['user']->level) · {{ $row['user']->level->value }}@endif</span>
                            @if ($row['user']->activeSubscription?->isAmbassadorMonth())
                                <span class="badge badge-grant">Ambassadeur</span>
                            @elseif ($row['user']->activeSubscription?->isGranted())
                                <span class="badge badge-grant">Accès offert</span>
                            @elseif ($row['user']->activeSubscription?->isProvisional())
                                <span class="badge">Accès provisoire</span>
                            @endif
                        </td>
                        <td data-label="Aujourd'hui">
                            <strong @class(['today-done' => $today['status'] === LearnerProgress::DONE, 'today-none' => $today['status'] === LearnerProgress::NONE])>{{ $today['label'] }}</strong>
                            @if ($today['working_day'] === true)
                                <br><span class="muted small">Jour de travail{{ $today['study_time'] ? ', séance prévue à '.$today['study_time'] : '' }}</span>
                            @elseif ($today['working_day'] === false)
                                <br><span class="muted small">Jour de repos choisi</span>
                            @else
                                <br><span class="muted small">Programme pas encore construit</span>
                            @endif
                        </td>
                        <td data-label="Temps utilisé aujourd'hui">{{ $today['used_seconds'] > 0 ? Duration::formatPrecise($today['used_seconds']) : 'Rien encore' }} sur {{ Duration::formatPrecise($today['daily_seconds']) }}</td>
                        <td data-label="Dernière séance">{{ $row['last'] ? $row['last']->timezone('Africa/Lome')->format('d/m/Y H:i') : 'Jamais' }}</td>
                        <td data-label="Série">{{ $row['streak'] > 0 ? $row['streak'].' '.($row['streak'] > 1 ? 'jours' : 'jour') : 'Aucune' }}</td>
                        <td data-label="Programme">
                            @if ($row['progress'] !== null)
                                {{ $row['lessons_done'] }} leçons sur {{ $row['lessons_total'] }} ({{ $row['progress'] }} %)
                                <div class="progress-line" aria-hidden="true"><span style="width: {{ $row['progress'] }}%"></span></div>
                                @if ($row['current'])
                                    <span class="muted small">{{ $row['current'] }}</span>
                                @endif
                            @else
                                <span class="muted">Pas encore de programme</span>
                            @endif
                        </td>
                        <td data-label="Jours manqués (7 jours)">{{ $row['missed'] }}</td>
                        <td data-label="Erreurs à revoir">{{ $row['open_errors'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layout>
