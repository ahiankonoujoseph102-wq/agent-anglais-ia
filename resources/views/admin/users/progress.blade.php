@php
    use App\Support\Admin\LearnerProgress;
    use App\Support\Duration;

    $row = $progress['row'];
    $today = $row['today'];
    $program = $progress['program'];
    $isAdmin = auth()->user()->isAdmin();
@endphp

{{-- Suivre l'apprenant de près : aujourd'hui, régularité, programme, compétences, erreurs, dernières séances. --}}
<section class="card" id="progression" aria-labelledby="progression-title">
    <h2 id="progression-title">Progression et travail</h2>

    @can('admin-route', 'admin.users.view-as')
        @if (\App\Support\Admin\ViewAs::allowed(auth()->user(), $user))
            <form method="POST" action="{{ route('admin.users.view-as', $user) }}">
                @csrf
                <button type="submit" class="btn btn-light"><x-icon name="eye" /> Voir son espace (lecture seule)</button>
                <p class="muted small">Ses pages, telles qu'il les voit, sans rien pouvoir y modifier, pendant {{ \App\Support\Admin\ViewAs::MINUTES }} minutes au plus.</p>
            </form>
        @endif
    @endcan

    <dl class="summary">
        <dt>Aujourd'hui</dt>
        <dd>
            <strong @class(['today-done' => $today['status'] === LearnerProgress::DONE, 'today-none' => $today['status'] === LearnerProgress::NONE])>{{ $today['label'] }}</strong>
            @if ($today['started_at'])
                (commencée à {{ $today['started_at']->timezone('Africa/Lome')->format('H:i') }})
            @endif
            @if ($today['working_day'] === true)
                · jour de travail{{ $today['study_time'] ? ', séance prévue à '.$today['study_time'] : '' }}
            @elseif ($today['working_day'] === false)
                · jour de repos choisi
            @endif
        </dd>
        @if ($today['daily_seconds'] > 0)
            <dt>Temps utilisé aujourd'hui</dt>
            <dd>{{ $today['used_seconds'] > 0 ? Duration::formatPrecise($today['used_seconds']) : 'Rien encore' }} sur {{ Duration::formatPrecise($today['daily_seconds']) }} (séance du jour : {{ Duration::formatPrecise($today['lesson_seconds']) }}, « Mes cours » : {{ Duration::formatPrecise($today['course_seconds']) }})</dd>
        @endif
        <dt>Dernière séance</dt><dd>{{ $row['last'] ? $row['last']->timezone('Africa/Lome')->format('d/m/Y H:i') : 'Jamais' }}</dd>
        <dt>Série</dt><dd>{{ $row['streak'] > 0 ? $row['streak'].' '.($row['streak'] > 1 ? 'jours de suite' : 'jour') : 'Aucune pour le moment' }}</dd>
        <dt>Séances du jour terminées</dt><dd>{{ $progress['lessons_done'] }}</dd>
        <dt>Jours de travail manqués</dt><dd>{{ $row['missed'] }} sur les 7 derniers jours</dd>
        @if ($program)
            <dt>Jours et heure choisis</dt><dd>{{ ucfirst($program->studyDaysLabel()) }}{{ $today['study_time'] ? ', à '.$today['study_time'] : '' }}</dd>
            <dt>Objectif</dt><dd>{{ $program->goal->label() }}@if ($program->occupation) · {{ $program->occupation }}@endif</dd>
        @endif
    </dl>

    @if ($program)
        <h3>Programme {{ $program->level->value }} : {{ $row['lessons_done'] }} leçons sur {{ $row['lessons_total'] }} ({{ $row['progress'] }} %)</h3>
        <div class="progress-line" aria-hidden="true"><span style="width: {{ $row['progress'] }}%"></span></div>
        @if ($row['current'])
            <p>Leçon en cours : {{ $row['current'] }}</p>
        @endif
        <details>
            <summary>Les unités</summary>
            <ul>
                @foreach ($progress['units'] as $unit)
                    <li @if ($unit['current']) aria-current="step" @endif>Unité {{ $unit['number'] }} : {{ $unit['title'] }} : {{ $unit['done'] }} sur {{ $unit['total'] }}@if ($unit['current']) <span class="badge">en cours</span>@endif</li>
                @endforeach
            </ul>
        </details>
        @if ($progress['upcoming']->isNotEmpty())
            <details>
                <summary>Prochaines leçons prévues</summary>
                <ul>
                    @foreach ($progress['upcoming'] as $next)
                        <li>{{ $next->scheduled_on?->format('d/m/Y') ?? 'Sans date' }} : unité {{ $next->unit_number }}, leçon {{ $next->lesson_number }}, {{ $next->situation()->title }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    @else
        <p class="muted">Pas encore de programme (questionnaire pas encore rempli).</p>
    @endif

    <h3>Compétences (moyenne des 10 dernières séances notées, sur 5)</h3>
    @if (collect($progress['skills'])->filter()->isEmpty())
        <p class="muted">Pas encore de séance notée.</p>
    @else
        <ul>
            @foreach ($progress['skills'] as $label => $average)
                <li>{{ $label }} : {{ $average === null ? '—' : str_replace('.', ',', (string) $average) }}</li>
            @endforeach
        </ul>
    @endif

    <h3>Erreurs à revoir : {{ $progress['errors']->count() }}</h3>
    <p class="muted small">{{ $progress['resolved_errors'] }} {{ $progress['resolved_errors'] > 1 ? 'erreurs corrigées' : 'erreur corrigée' }} (deux fois juste de suite).</p>
    @if ($progress['errors']->isNotEmpty())
        <ul>
            @foreach ($progress['errors'] as $error)
                <li>« {{ $error->wrong }} » → « {{ $error->correct }} »<span class="muted small"> · vue {{ $error->times_seen }} fois</span>@if ($error->explanation)<br><span class="muted small">{{ $error->explanation }}</span>@endif</li>
            @endforeach
        </ul>
    @endif

    <h3>Dernières séances du jour</h3>
    @forelse ($progress['lessons'] as $lesson)
        <div class="lesson-log">
            <p>
                <strong>{{ $lesson->started_at?->timezone('Africa/Lome')->format('d/m/Y H:i') ?? $lesson->date?->format('d/m/Y') }}</strong>
                · {{ Duration::formatPrecise((int) $lesson->used_seconds) }}
                · {{ $lesson->teacher()->name() }}
                · {{ $lesson->status->label() }}
                @if ($lesson->outcome === 'completed')
                    <span class="badge">Leçon terminée</span>
                @elseif ($lesson->outcome === 'repeat')
                    <span class="badge badge-muted">Leçon à reprendre</span>
                @endif
            </p>
            @if ($lesson->programLesson)
                <p class="muted small">Unité {{ $lesson->programLesson->unit_number }}, leçon {{ $lesson->programLesson->lesson_number }}@if ($lesson->topic) : {{ $lesson->topic }}@endif</p>
            @elseif ($lesson->topic)
                <p class="muted small">{{ $lesson->topic }}</p>
            @endif
            @if ($lesson->summary)
                <p><strong>Résumé :</strong> {{ $lesson->summary }}</p>
            @endif
            @if ($lesson->homework)
                <p><strong>Devoir :</strong> {{ $lesson->homework }}</p>
            @endif
            @if ($lesson->reported_at)
                <p class="muted small">Notes : aisance {{ $lesson->score_fluency ?? '—' }}, prononciation {{ $lesson->score_pronunciation ?? '—' }}, grammaire {{ $lesson->score_grammar ?? '—' }}, vocabulaire {{ $lesson->score_vocabulary ?? '—' }}, compréhension {{ $lesson->score_comprehension ?? '—' }} (sur 5)</p>
            @endif
            @if ($isAdmin)
                <p><a href="{{ route('admin.conversations.show', [$user, 'lesson', $lesson->id]) }}">Lire la conversation</a></p>
            @endif
        </div>
    @empty
        <p class="muted">Aucune séance du jour pour le moment.</p>
    @endforelse

    @if ($progress['course_sessions']->isNotEmpty())
        <h3>Dernières séances « Mes cours »</h3>
        <ul>
            @foreach ($progress['course_sessions'] as $session)
                <li>
                    {{ $session->started_at?->timezone('Africa/Lome')->format('d/m/Y H:i') ?? $session->date?->format('d/m/Y') }}
                    · {{ Duration::formatPrecise((int) $session->used_seconds) }}
                    · {{ $session->lesson?->title ?? 'Leçon supprimée' }}
                    @if ($isAdmin && $session->review?->isReady())
                        · <a href="{{ route('courses.review.download', $session) }}">Document de révision</a>
                    @endif
                    @if ($isAdmin)
                        · <a href="{{ route('admin.conversations.show', [$user, 'course', $session->id]) }}">Lire la conversation</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($progress['level_tests']->isNotEmpty() || $progress['attestations']->isNotEmpty())
        <h3>Bilans de niveau et attestations</h3>
        <ul>
            @foreach ($progress['level_tests'] as $test)
                <li>Bilan du {{ $test->started_at?->format('d/m/Y') ?? '—' }} : {{ $test->passed === null ? $test->status->label() : ($test->passed ? 'réussi' : 'à repasser') }}</li>
            @endforeach
            @foreach ($progress['attestations'] as $attestation)
                <li>Attestation {{ $attestation->level->value }} du {{ $attestation->issued_at?->format('d/m/Y') }}</li>
            @endforeach
        </ul>
    @endif
</section>
