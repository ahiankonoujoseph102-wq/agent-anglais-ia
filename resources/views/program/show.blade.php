@php
    use App\Enums\ProgramStatus;
@endphp

<x-layout title="Mon programme">
    <h1>Mon programme {{ $program->level->value }}</h1>
    <p class="muted">{{ $levelTitle }} · objectif : {{ \Illuminate\Support\Str::lcfirst($program->goal->label()) }} · {{ $program->occupation }}</p>

    @if (! empty($program->content['intro']))
        <div class="card"><p>{{ $program->content['intro'] }}</p></div>
    @endif

    <div class="stats">
        <div class="stat">
            <div class="label">Progression du niveau</div>
            <div class="value">{{ $progress }} %</div>
            <div class="progress" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progress }}%"></span></div>
        </div>
        <div class="stat">
            <div class="label">Unité en cours</div>
            @if ($current)
                <div class="value">{{ $current->unit_number }} / {{ count($units) }}</div>
                <div class="detail">{{ $units[$current->unit_number]->title }} — leçon {{ $current->lesson_number }} : {{ $current->focusLabel() }}</div>
            @else
                <div class="value">Terminé</div>
                <div class="detail">Les 12 unités sont faites</div>
            @endif
        </div>
        <div class="stat">
            <div class="label">Mes jours de travail</div>
            <div class="value small-value">{{ ucfirst($program->studyDaysLabel()) }}</div>
            <div class="detail">{{ $program->study_time ? 'vers '.str_replace(':', ' h ', $program->study_time) : '' }}</div>
        </div>
    </div>

    @if ($program->status === ProgramStatus::LevelTestDue)
        <div class="alert alert-success next-step">
            <strong>Bravo, vous avez terminé les 12 unités !</strong> Passez le bilan oral de fin de niveau avec le professeur.
            <a class="btn btn-block next-step" href="{{ route('level-test.show') }}">Passer mon bilan de fin de niveau</a>
        </div>
    @elseif ($current)
        <section class="card next-step">
            <h2>Prochaine leçon</h2>
            <p>
                <strong>Unité {{ $current->unit_number }} · {{ $units[$current->unit_number]->title }}</strong><br>
                Leçon {{ $current->lesson_number }} : {{ $current->focusLabel() }}<br>
                Situation : {{ $current->situation()->title }}
                @if ($current->highlighted) <span class="badge" title="Liée à votre objectif ou à votre métier">pour vous</span> @endif
            </p>
            <a class="btn" href="{{ route('lesson.show') }}">Commencer la séance</a>
        </section>
    @endif

    <h2 class="next-step">Mon calendrier</h2>
    @if ($upcoming->isEmpty())
        <div class="card">Aucune leçon à venir.</div>
    @else
        <ul class="calendar card">
            @foreach ($upcoming as $lesson)
                <li @class(['today' => $lesson->scheduled_on?->isToday()])>
                    <span class="date">{{ $lesson->scheduled_on?->translatedFormat('D j M') ?? '—' }}</span>
                    <span>Unité {{ $lesson->unit_number }}, leçon {{ $lesson->lesson_number }} — {{ $lesson->situation()->title }}
                        @if ($lesson->highlighted) <span class="badge">pour vous</span> @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="next-step">Les 12 unités du niveau {{ $program->level->value }}</h2>
    <ol class="units">
        @foreach ($units as $number => $unit)
            @php
                $unitLessons = $byUnit[$number] ?? collect();
                $done = $unitLessons->filter->isCompleted()->count();
            @endphp
            <li @class(['card', 'unit-done' => $done === $unitLessons->count() && $done > 0, 'unit-current' => $current?->unit_number === $number])>
                <a href="{{ route('program.unit', $number) }}"><strong>{{ $number }}. {{ $unit->title }}</strong></a>
                <span class="muted small">{{ $done }} / {{ $unitLessons->count() }} leçons</span>
            </li>
        @endforeach
    </ol>
</x-layout>
