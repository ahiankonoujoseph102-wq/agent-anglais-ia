<x-layout title="Mes cours" narrow>
    <div class="page-head rise">
        <div class="card-title">
            <span class="icon-bubble icon-bubble-primary"><x-icon name="book" /></span>
            <h1>Mes cours</h1>
        </div>
        {{-- Une phrase : la liste des fichiers acceptés est dans l'état vide et sur la page d'ajout. --}}
        <p class="muted">Un complément facultatif pour vos leçons d'anglais de l'école, de l'université ou de votre formation.</p>
    </div>

    @unless ($access)
        <section class="card card-accent rise" style="--i: 1">
            <div class="card-title">
                <span class="icon-bubble"><x-icon name="lock" /></span>
                <h2>Une formule active est nécessaire</h2>
            </div>
            <p>« Mes cours » est réservé aux apprenants qui ont une formule active (payée, ou accès provisoire de 24 heures après l'envoi de votre capture de paiement).</p>
            <a href="{{ route('payment.plans') }}" class="btn btn-accent btn-block btn-lg btn-plan">Choisir ma formule <x-icon name="arrow-right" /></a>
        </section>
    @else
        {{-- L'action d'abord : « Ajouter une leçon » (sans leçon : l'état vide, avec son bouton), puis le temps du jour. --}}
        @if ($lessons->total() > 0)
            <a href="{{ route('courses.create') }}" class="btn btn-accent btn-block btn-lg courses-add rise"><x-icon name="plus" /> Ajouter une leçon</a>
        @else
            <div class="card empty-state rise">
                <x-teacher-face size="72" />
                <p><strong>Vous n'avez pas encore ajouté de leçon.</strong></p>
                <p class="muted">{{ \App\Support\CourseFiles::offer() }}.</p>
                <div class="actions">
                    <a href="{{ route('courses.create') }}" class="btn btn-accent btn-block"><x-icon name="plus" /> Ajouter une leçon</a>
                </div>
            </div>
        @endif

        <section class="card rise" style="--i: 1">
            <p class="course-counter" data-course-counter>
                <x-icon name="clock" />
                <span>{{ \App\Support\DailyTime::remainingSentence($counter['remaining']) }}</span>
            </p>
            <p class="muted small">Votre temps du jour est partagé librement entre la séance orale et « Mes cours ». Une séance avec {{ config('avatar.model.name') }} dure {{ \App\Support\Duration::format((int) config('platform.courses.max_session_minutes') * 60) }} au plus, jamais plus que le temps qu'il vous reste. Votre programme et votre séance orale du jour restent l'essentiel.</p>
        </section>

        @if ($lessons->total() > 0)
            <h2 class="next-step">Mes leçons</h2>

            @foreach ($lessons as $lesson)
                <article class="card course-card reveal">
                    <div class="course-card-body">
                        <h3><a href="{{ route('courses.show', $lesson) }}">{{ $lesson->title }}</a></h3>
                        <p class="muted small">
                            {{ $lesson->created_at->translatedFormat('j F Y') }} · {{ $lesson->pagesLabel() }}
                            @if ($lesson->isPdf()) · PDF @endif
                        </p>
                    </div>
                    <div class="course-card-actions">
                        <a href="{{ route('courses.show', $lesson) }}" class="btn btn-outline"><x-icon name="arrow-right" /> Ouvrir</a>
                        <form method="POST" action="{{ route('courses.destroy', $lesson) }}" {!! \App\Support\Confirmation::attributes('course.delete') !!}>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline"><x-icon name="trash" /> Supprimer</button>
                        </form>
                    </div>
                </article>
            @endforeach

            {{ $lessons->links() }}
        @endif
    @endunless

    <div class="form-footer">
        <p><a class="back-link" href="{{ route('dashboard') }}"><x-icon name="arrow-left" /> Retour à mon espace</a></p>
    </div>
</x-layout>
