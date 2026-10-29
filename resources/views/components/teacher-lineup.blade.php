{{--
    Les professeurs proposés (config/teachers.php, « ready »), sur les pages publiques. Rien quand un seul professeur
    est proposé (retour à Teacher Joe seul) : les pages redeviennent exactement celles d'avant.
    compact : une seule phrase (pages pays, guides, formules) ; sinon une section avec le portrait de chacun (accueil).
--}}
@props(['compact' => false])

@php
    use App\Support\Asset;
    use App\Support\Teachers\Teachers;

    $teachers = Teachers::choosable();
    $count = [2 => 'Deux', 3 => 'Trois', 4 => 'Quatre'][count($teachers)] ?? (string) count($teachers);
    // Faits vérifiés dans le code : choix dans « Mon espace » (TeacherChoiceController), changement possible chaque jour
    // (jamais pendant une séance), même programme ; l'évaluation gratuite se fait avec le professeur par défaut.
    $choice = 'Vous choisissez votre professeur dans « Mon espace », et vous pouvez en changer chaque jour.';
    $assessment = 'L\'évaluation gratuite se passe avec '.Teachers::default()->name().'.';
@endphp

@if (Teachers::hasChoice())
    @if ($compact)
        <p {{ $attributes->merge(['class' => 'teacher-lineup-line']) }}>
            <x-icon name="users" />
            <span>{{ $count }} professeurs au choix, {{ Teachers::names() }} : même méthode et même programme. {{ $choice }}</span>
        </p>
    @else
        <section {{ $attributes->merge(['class' => 'section band teacher-lineup']) }} aria-labelledby="professeurs">
            <div class="section-head reveal">
                <span class="eyebrow">Vos professeurs</span>
                <h2 id="professeurs">{{ Teachers::names() }} : à vous de choisir</h2>
                <p class="muted">Même méthode, même programme, mêmes formules : seuls la voix et le visage changent.</p>
            </div>
            <ul class="lineup">
                @foreach ($teachers as $teacher)
                    <li class="card lineup-card reveal reveal-side" style="--i: {{ $loop->index }}">
                        <span class="lineup-face"><img src="{{ Asset::url($teacher->portrait()) }}" width="96" height="96" loading="lazy" alt="{{ $teacher->name() }}, {{ $teacher->theTeacher() }} d'anglais de {{ config('platform.name') }}"></span>
                        <div>
                            <h3>{{ $teacher->name() }}</h3>
                            <p>{{ $teacher->publicLine() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="muted small reveal lineup-note">{{ $choice }} {{ $assessment }}</p>
        </section>
    @endif
@endif
