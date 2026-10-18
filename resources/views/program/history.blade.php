@php
    use App\Enums\Skill;
    use App\Support\Duration;
@endphp

<x-layout title="Mes séances">
    <div class="page-head rise">
        <h1>Mes séances</h1>
        <p class="muted">Vos bilans de séance : résumés, devoirs, notes et erreurs à revoir.</p>
    </div>

    <section class="card card-accent rise" style="--i: 1">
        <div class="card-title">
            <span class="icon-bubble icon-bubble-accent"><x-icon name="edit" /></span>
            <h2>Mes erreurs à revoir ({{ $openErrors->count() }})</h2>
        </div>
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
        <article class="card lesson-card reveal">
            <h3>
                {{ $lesson->date->translatedFormat('l j F Y') }}
                <span class="badge badge-muted"><x-icon name="clock" /> {{ Duration::formatPrecise($lesson->used_seconds) }}</span>
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
                <ul class="scores">
                    @foreach (Skill::cases() as $skill)
                        <li>{{ $skill->label() }} {{ $lesson->{$skill->column()} }}/5</li>
                    @endforeach
                </ul>
            @endif
        </article>
    @empty
        <div class="card empty-state">
            <span class="icon-bubble"><x-icon name="mic" /></span>
            <p>Aucune séance pour le moment.</p>
            <div class="actions">
                @if (auth()->user()->activeSubscription && auth()->user()->activeProgram)
                    <a href="{{ route('lesson.show') }}" class="btn btn-accent btn-block"><x-icon name="mic" /> Parler avec {{ config('avatar.model.name') }}</a>
                @else
                    <a href="{{ route('dashboard') }}" class="btn btn-accent btn-block"><x-icon name="arrow-right" /> Aller à mon espace</a>
                @endif
            </div>
        </div>
    @endforelse

    {{ $lessons->links() }}
</x-layout>
