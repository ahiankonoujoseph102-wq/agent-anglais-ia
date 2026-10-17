@php
    $content = $course->content;
    $grammar = $content['grammar'] ?? [];
    $vocabulary = $content['vocabulary'] ?? [];
    $exercises = $content['exercises'] ?? [];
    $teacher = config('avatar.model.name');
@endphp

<x-layout :title="$course->title" narrow>
    <p><a class="back-link" href="{{ route('courses.index') }}"><x-icon name="arrow-left" /> Mes cours</a></p>

    <div class="page-head rise">
        <h1>{{ $course->title }}</h1>
        <p class="muted small">
            Ajoutée le {{ $course->created_at->translatedFormat('j F Y') }} · {{ $course->pagesLabel() }}@if ($course->isPdf()) · PDF @endif
        </p>
    </div>

    <section class="card card-accent rise" style="--i: 1">
        <div class="card-title">
            <span class="icon-bubble icon-bubble-primary"><x-icon name="mic" /></span>
            <h2>Travailler cette leçon avec {{ $teacher }}</h2>
        </div>
        <p>Il vous demande ce que vous voulez : comprendre toute la leçon, poser une question précise ou travailler un exercice. Il vous guide, vous répond en français simple et vous fait pratiquer l'anglais à l'oral.</p>

        <p class="course-counter">
            <x-icon name="clock" />
            <span>Leçons aujourd'hui : <strong>{{ $counter['used'] }} sur {{ $counter['limit'] }}</strong></span>
        </p>

        @if ($counter['reached'])
            <div class="alert alert-info" role="status">
                <x-icon name="info" />
                <div>Vous avez fait vos {{ $counter['limit'] }} leçons du jour. Revenez demain : votre séance orale du jour reste disponible.</div>
            </div>
        @else
            <a href="{{ route('courses.session', $course) }}" class="btn btn-accent btn-block btn-lg"><x-icon name="mic" /> Parler avec {{ $teacher }}</a>
            <p class="muted small next-step">
                Séance de {{ \App\Support\Duration::format((int) config('platform.courses.session_minutes') * 60) }} au plus, en complément de votre séance orale du jour.
            </p>
        @endif
    </section>

    <section class="card rise" style="--i: 2">
        <h2>Les pages de la leçon</h2>
        <ul class="course-pages">
            @foreach ($course->pages as $page)
                <li>
                    @if ($page->isPdf())
                        <a href="{{ route('courses.page', [$course, $page]) }}" class="btn btn-light" target="_blank" rel="noopener"><x-icon name="file-text" /> Ouvrir le PDF</a>
                    @else
                        <a href="{{ route('courses.page', [$course, $page]) }}" target="_blank" rel="noopener" class="proof-thumb">
                            <img src="{{ route('courses.page', [$course, $page]) }}" alt="Page {{ $page->position }} de la leçon" loading="lazy">
                        </a>
                        <span class="muted small">Page {{ $page->position }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="muted small">Ces pages restent privées : vous seul et notre équipe pouvez les voir.</p>
    </section>

    <section class="card rise" style="--i: 3">
        <h2>Ce que {{ $teacher }} a lu</h2>
        @if (filled($content['summary'] ?? null))
            <p>{{ $content['summary'] }}</p>
        @endif

        @if ($grammar !== [])
            <details class="course-details">
                <summary>Points de grammaire ({{ count($grammar) }})</summary>
                @foreach ($grammar as $point)
                    <div class="course-point">
                        <strong lang="en">{{ $point['point'] }}</strong>
                        @if (filled($point['explanation'] ?? null))<p>{{ $point['explanation'] }}</p>@endif
                        @foreach ($point['examples'] ?? [] as $example)
                            <p class="muted small" lang="en">{{ $example }}</p>
                        @endforeach
                    </div>
                @endforeach
            </details>
        @endif

        @if ($vocabulary !== [])
            <details class="course-details">
                <summary>Vocabulaire ({{ count($vocabulary) }})</summary>
                <ul class="course-words">
                    @foreach ($vocabulary as $word)
                        <li><span lang="en">{{ $word['english'] }}</span>@if (filled($word['french'] ?? null)) — {{ $word['french'] }}@endif</li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if ($exercises !== [])
            <details class="course-details">
                <summary>Exercices ({{ count($exercises) }})</summary>
                <ol class="course-words">
                    @foreach ($exercises as $exercise)
                        <li>{{ $exercise }}</li>
                    @endforeach
                </ol>
            </details>
        @endif

        @if ($grammar === [] && $vocabulary === [] && $exercises === [] && blank($content['summary'] ?? null))
            <p class="muted">{{ $teacher }} a lu le texte de vos pages.</p>
        @endif
    </section>

    <section class="card rise" style="--i: 4">
        <h2>Supprimer cette leçon</h2>
        <p class="muted small">La leçon et ses fichiers sont effacés pour toujours.</p>
        <form method="POST" action="{{ route('courses.destroy', $course) }}" {!! \App\Support\Confirmation::attributes('course.delete') !!}>
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline"><x-icon name="trash" /> Supprimer</button>
        </form>
    </section>
</x-layout>
