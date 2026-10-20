<x-layout title="Mes cours" narrow>
    <div class="page-head rise">
        <div class="card-title">
            <span class="icon-bubble icon-bubble-primary"><x-icon name="book" /></span>
            <h1>Mes cours</h1>
        </div>
        <p class="muted">
            Un complément facultatif : apportez une leçon d'anglais de votre école, de votre université ou de votre formation, et {{ config('avatar.model.name') }}
            vous aide à la comprendre, répond à vos questions et vous guide sur ses exercices.
            Votre programme et votre séance orale du jour restent l'essentiel.
        </p>
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
        <section class="card rise" style="--i: 1">
            <p class="course-counter" data-course-counter>
                <x-icon name="clock" />
                <span>{{ \App\Support\DailyTime::remainingSentence($counter['remaining']) }}</span>
            </p>
            <p class="muted small">Votre temps du jour est partagé librement entre la séance orale et « Mes cours ». Une séance avec {{ config('avatar.model.name') }} dure {{ \App\Support\Duration::format((int) config('platform.courses.max_session_minutes') * 60) }} au plus, jamais plus que le temps qu'il vous reste.</p>
            <a href="{{ route('courses.create') }}" class="btn btn-accent btn-block btn-lg"><x-icon name="plus" /> Ajouter une leçon</a>
        </section>

        <h2 class="next-step">Mes leçons</h2>

        @forelse ($lessons as $lesson)
            <article class="card course-card reveal">
                <div class="course-card-body">
                    <h3><a href="{{ route('courses.show', $lesson) }}">{{ $lesson->title }}</a></h3>
                    <p class="muted small">
                        {{ $lesson->created_at->translatedFormat('j F Y') }} · {{ $lesson->pagesLabel() }}
                        @if ($lesson->isPdf()) · PDF @endif
                    </p>
                </div>
                <div class="course-card-actions">
                    <a href="{{ route('courses.show', $lesson) }}" class="btn btn-light"><x-icon name="arrow-right" /> Ouvrir</a>
                    <form method="POST" action="{{ route('courses.destroy', $lesson) }}" {!! \App\Support\Confirmation::attributes('course.delete') !!}>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline"><x-icon name="trash" /> Supprimer</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card empty-state">
                <span class="icon-bubble"><x-icon name="book" /></span>
                <p><strong>Vous n'avez pas encore ajouté de leçon.</strong></p>
                <p class="muted">Prenez votre cahier ou votre livre d'anglais : une photo suffit.</p>
                <div class="actions">
                    <a href="{{ route('courses.create') }}" class="btn btn-accent btn-block"><x-icon name="plus" /> Ajouter une leçon</a>
                </div>
            </div>
        @endforelse

        {{ $lessons->links() }}
    @endunless

    <div class="form-footer">
        <p><a class="back-link" href="{{ route('dashboard') }}"><x-icon name="arrow-left" /> Retour à mon espace</a></p>
    </div>
</x-layout>
