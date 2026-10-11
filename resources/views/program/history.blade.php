@php
    use App\Enums\Skill;
    use App\Support\Duration;
@endphp

<x-layout title="Mes séances">
    <h1>Mes séances</h1>

    <section class="card">
        <h2>Mes erreurs à revoir ({{ $openErrors->count() }})</h2>
        <p class="muted small">
            Le professeur vous les fait répéter à chaque séance. Une erreur disparaît quand vous dites la bonne forme
            deux séances de suite. Déjà corrigées : {{ $resolvedCount }}.
        </p>
        @forelse ($openErrors as $error)
            <p class="error-item">
                <span class="wrong" lang="en">{{ $error->wrong }}</span> →
                <strong lang="en">{{ $error->correct }}</strong>
                @if ($error->explanation)<br><span class="muted small">{{ $error->explanation }}</span>@endif
                @if ($error->correct_streak > 0)<br><span class="badge">1 correction sur 2</span>@endif
            </p>
        @empty
            <p>Aucune erreur en cours. Bravo !</p>
        @endforelse
    </section>

    <h2 class="next-step">Historique</h2>
    @forelse ($lessons as $lesson)
        <article class="card">
            <h3>
                {{ $lesson->date->translatedFormat('l j F Y') }}
                <span class="muted small">· {{ Duration::formatPrecise($lesson->used_seconds) }}</span>
            </h3>
            @if ($lesson->programLesson)
                <p class="muted small">Unité {{ $lesson->programLesson->unit_number }}, leçon {{ $lesson->programLesson->lesson_number }}
                    @if ($lesson->outcome) · {{ $lesson->outcome === 'completed' ? 'leçon terminée' : 'leçon à reprendre' }} @endif
                </p>
            @endif
            @if ($lesson->summary)
                <p>{{ $lesson->summary }}</p>
            @else
                <p class="muted">Pas de résumé pour cette séance (interrompue avant la fin).</p>
            @endif
            @if ($lesson->homework)
                <p><strong>Devoir :</strong> {{ $lesson->homework }}</p>
            @endif
            @if ($lesson->reported_at)
                <p class="small muted">
                    @foreach (Skill::cases() as $skill)
                        {{ $skill->label() }} {{ $lesson->{$skill->column()} }}/5 @if (! $loop->last) · @endif
                    @endforeach
                </p>
            @endif
        </article>
    @empty
        <div class="card">Aucune séance pour le moment.</div>
    @endforelse

    {{ $lessons->links() }}
</x-layout>
