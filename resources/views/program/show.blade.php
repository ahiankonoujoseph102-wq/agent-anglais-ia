@php
    use App\Enums\ProgramStatus;
@endphp

<x-layout title="Mon programme">
    <div class="page-head rise">
        <span class="eyebrow"><x-icon name="target" /> Objectif : {{ \Illuminate\Support\Str::lcfirst($program->goal->label()) }}</span>
        <h1>Mon programme {{ $program->level->value }}</h1>
        <p class="muted">{{ $levelTitle }} · {{ $program->occupation }}</p>
        <a class="btn btn-outline" href="{{ route('program.answers.edit', [$program, 1]) }}"><x-icon name="target" /> Modifier mes objectifs</a>
    </div>

    @if (! empty($program->content['intro']))
        <div class="card card-soft rise"><p>{{ $program->content['intro'] }}</p></div>
    @endif

    <div class="stats">
        <div class="stat rise" style="--i: 1">
            <div class="label"><x-icon name="chart" /> Progression du niveau</div>
            <div class="value">{{ $progress }} %</div>
            <div class="progress" role="progressbar" aria-label="Progression du niveau" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progress }}%"></span></div>
        </div>
        <div class="stat rise" style="--i: 2">
            <div class="label"><x-icon name="book" /> Unité en cours</div>
            @if ($current)
                <div class="value">{{ $current->unit_number }} / {{ count($units) }}</div>
                <div class="detail">{{ $units[$current->unit_number]->title }} — leçon {{ $current->lesson_number }} : {{ $current->focusLabel() }}</div>
            @else
                <div class="value">Terminé</div>
                <div class="detail">Les 12 unités sont faites</div>
            @endif
        </div>
        <div class="stat rise" style="--i: 3">
            <div class="label"><x-icon name="calendar" /> Mon rythme</div>
            <div class="value small-value">{{ ucfirst($program->studyDaysLabel()) }}</div>
            <div class="detail">{{ $program->study_time ? 'vers '.str_replace(':', ' h ', $program->study_time) : '' }}</div>
        </div>
    </div>

    @if ($program->status === ProgramStatus::LevelTestDue)
        <div class="card next-card next-step reveal">
            <h2>Bravo, vous avez terminé les 12 unités !</h2>
            <p>Passez le bilan oral de fin de niveau avec le professeur.</p>
            <a class="btn btn-block btn-lg" href="{{ route('level-test.show') }}"><x-icon name="award" /> Passer mon bilan de fin de niveau</a>
        </div>
    @elseif ($current)
        <section class="card next-card next-step reveal">
            <h2>Prochaine leçon</h2>
            <p>
                <strong>Unité {{ $current->unit_number }} · {{ $units[$current->unit_number]->title }}</strong><br>
                Leçon {{ $current->lesson_number }} : {{ $current->focusLabel() }}<br>
                Situation : {{ $current->situation()->title }}
                @if ($current->highlighted) <span class="badge badge-accent" title="Liée à votre objectif ou à votre métier"><x-icon name="sparkle" /> pour vous</span> @endif
            </p>
            <a class="btn btn-lg btn-block" href="{{ route('lesson.show') }}"><x-icon name="mic" /> Commencer la séance</a>
        </section>
    @endif

    <h2 class="next-step reveal">Mon calendrier</h2>
    @if ($upcoming->isEmpty())
        <div class="card empty-state">
            <span class="icon-bubble"><x-icon name="calendar" /></span>
            <p>Aucune leçon à venir.</p>
        </div>
    @else
        <ul class="calendar card reveal">
            @foreach ($upcoming as $lesson)
                <li @class(['today' => $lesson->scheduled_on?->isToday()])>
                    <span class="date">@if ($lesson->scheduled_on){{ $lesson->scheduled_on->translatedFormat('l') }}<br>{{ $lesson->scheduled_on->translatedFormat('j F') }}@else — @endif</span>
                    <span>Unité {{ $lesson->unit_number }}, leçon {{ $lesson->lesson_number }} — {{ $lesson->situation()->title }}
                        @if ($lesson->highlighted) <span class="badge badge-accent">pour vous</span> @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="next-step reveal">Les 12 unités du niveau {{ $program->level->value }}</h2>
    <ol class="units">
        @foreach ($units as $number => $unit)
            @php
                $unitLessons = $byUnit[$number] ?? collect();
                $done = $unitLessons->filter->isCompleted()->count();
            @endphp
            <li @class(['card', 'card-link', 'reveal', 'unit-done' => $done === $unitLessons->count() && $done > 0, 'unit-current' => $current?->unit_number === $number])>
                <a class="unit-link" href="{{ route('program.unit', $number) }}">
                    <span class="unit-number">@if ($done === $unitLessons->count() && $done > 0)<x-icon name="check" />@else{{ $number }}@endif</span>
                    <span class="unit-body">
                        <strong>{{ $number }}. {{ $unit->title }}</strong>
                        <span class="muted small">{{ $done }} / {{ $unitLessons->count() }} leçons</span>
                    </span>
                    <x-icon name="chevron-right" />
                </a>
            </li>
        @endforeach
    </ol>
</x-layout>
