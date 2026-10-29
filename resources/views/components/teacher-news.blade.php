{{--
    « Nouveau : Teacher Rose est arrivée ! » (« Mon espace », en haut) : pour un abonné qui a déjà fait des séances et
    n'a jamais travaillé avec ce professeur (Teachers::untried). Un seul bouton, en contour (le bouton orange reste celui
    de la prochaine étape) : le choisir tout de suite. « Plus tard » cache l'annonce sur ce téléphone (app.js).
--}}
@props(['user'])
@if ($user->activeSubscription)
    @foreach (\App\Support\Teachers\Teachers::untried($user) as $teacher)
        @php($she = $teacher->pronoun() === 'elle')
        <section class="card teacher-news rise" data-dismissible="teacher-news-{{ $teacher->key }}" aria-labelledby="teacher-news-{{ $teacher->key }}">
            <span class="teacher-news-face"><img src="{{ \App\Support\Asset::url($teacher->portrait()) }}" width="44" height="44" alt=""></span>
            <div>
                <h2 id="teacher-news-{{ $teacher->key }}"><span class="teacher-news-tag">Nouveau</span> {{ $teacher->name() }} est {{ $she ? 'arrivée' : 'arrivé' }} !</h2>
                {{-- Le détail (niveau, programme et erreurs gardés, changer chaque jour) est dans « Votre professeur ». --}}
                <div class="teacher-news-actions">
                    <form method="POST" action="{{ route('teacher.choose') }}">
                        @csrf
                        <input type="hidden" name="teacher" value="{{ $teacher->key }}">
                        <button type="submit" class="btn btn-outline">Essayer avec {{ $she ? 'elle' : 'lui' }}<span class="sr-only"> : {{ $teacher->name() }}</span></button>
                    </form>
                    <button type="button" class="btn-link teacher-news-later" data-dismiss>Plus tard</button>
                </div>
            </div>
        </section>
    @endforeach
@endif
