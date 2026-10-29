@php
    // Long PDF : ce qui a été lu de la partie choisie ; sinon la leçon entière.
    $part = $course->currentPart;
    $content = $part?->content ?? $course->content;
    $outline = $course->content['outline'] ?? [];
    $byParts = $course->readByParts();
    $ready = $course->readyForSession();
    $grammar = $content['grammar'] ?? [];
    $vocabulary = $content['vocabulary'] ?? [];
    $exercises = $content['exercises'] ?? [];
    $teacherOf = \App\Support\Teachers\Teachers::forUser(auth()->user());
    $teacher = $teacherOf->name();
@endphp

<x-layout :title="$course->title" narrow>
    <p><a class="back-link" href="{{ route('courses.index') }}"><x-icon name="arrow-left" /> Mes cours</a></p>

    <div class="page-head rise">
        <h1>{{ $course->title }}</h1>
        <p class="muted small">
            Ajoutée le {{ $course->created_at->translatedFormat('j F Y') }} · {{ $course->pagesLabel() }}@if ($course->isPdf()) · PDF @endif
        </p>
    </div>

    {{--
        PDF : la partie à travailler. Long PDF (plus de :max pages) : obligatoire, elle est lue avant la séance et gardée ;
        PDF court : facultative, Teacher Joe s'y concentre. Les parties déjà travaillées se reprennent sans relire le fichier.
    --}}
    @if ($course->isPdf())
        <section class="card rise course-part" style="--i: 1" id="partie" aria-labelledby="partie-titre">
            <div class="card-title">
                <span class="icon-bubble"><x-icon name="file-text" /></span>
                <h2 id="partie-titre">Quelle partie voulez-vous travailler ?</h2>
            </div>
            @if ($byParts)
                <p>Votre PDF a {{ $course->page_count }} pages. Indiquez les pages ({{ $partLimits['max_pages'] }} au plus à la fois) ou décrivez le passage : {{ $teacher }} le lit, puis se concentre dessus. Vous reviendrez un autre jour pour la suite, sans renvoyer le fichier.</p>
            @else
                <p>Facultatif : {{ $teacher }} a lu toute la leçon. Indiquez une partie pour qu'il se concentre dessus.</p>
            @endif

            @if ($part)
                <p class="course-counter"><x-icon name="check-circle" /> <span>Partie choisie : {{ $part->label() }}</span></p>
            @endif

            @if ($byParts && $outline !== [])
                <details class="course-details">
                    <summary>Le sommaire de votre PDF</summary>
                    @if (filled($course->content['summary'] ?? null))
                        <p>{{ $course->content['summary'] }}</p>
                    @endif
                    <ul class="course-outline">
                        @foreach ($outline as $line)
                            <li><strong>Page {{ $line['page'] }}</strong> {{ $line['content'] }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif

            <form method="POST" action="{{ route('courses.part', $course) }}" class="course-part-form" data-long-submit data-timeout="{{ $partLimits['browser_timeout'] }}" data-busy-label="{{ $byParts ? 'Lecture de ces pages…' : 'Envoi en cours…' }}" novalidate>
                @csrf
                <div class="course-part-pages">
                    <x-input name="from" label="De la page" type="number" inputmode="numeric" min="1" :max="$course->page_count" />
                    <x-input name="to" label="à la page" type="number" inputmode="numeric" min="1" :max="$course->page_count" />
                </div>
                <x-input name="description" label="Ou décrivez le passage (facultatif)" :maxlength="$partLimits['description_max']" placeholder="Par exemple : l'exercice 2, la leçon sur le passé" />
                <p class="error" data-long-submit-error role="alert" hidden></p>
                <button type="submit" class="btn btn-accent btn-block"><x-icon name="book" /> {{ $byParts ? 'Préparer cette partie' : 'Choisir cette partie' }}</button>
                @if ($byParts)
                    <p class="muted small next-step" data-long-submit-waiting hidden>{{ $teacher }} lit ces pages : cela peut prendre jusqu'à une minute. Gardez cette page ouverte.</p>
                @endif
            </form>

            @php($others = $course->parts->filter(fn ($other) => $other->id !== $part?->id && (! $byParts || $other->content !== null)))
            @if ($others->isNotEmpty())
                <details class="course-details">
                    <summary>Parties déjà travaillées ({{ $others->count() }})</summary>
                    <ul class="course-parts">
                        @foreach ($others as $other)
                            <li>
                                <span>{{ ucfirst($other->label()) }}</span>
                                <form method="POST" action="{{ route('courses.part', $course) }}">
                                    @csrf
                                    <input type="hidden" name="part" value="{{ $other->id }}">
                                    <button type="submit" class="btn btn-outline">Reprendre</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>
    @endif

    <section class="card card-accent rise" style="--i: 1" id="travailler" aria-labelledby="travailler-titre">
        <div class="card-title">
            <span class="icon-bubble icon-bubble-primary"><x-icon name="mic" /></span>
            <h2 id="travailler-titre">Travailler cette leçon avec {{ $teacher }}</h2>
        </div>
        <p>{{ ucfirst($teacherOf->pronoun()) }} vous demande ce que vous voulez : comprendre toute la leçon, poser une question précise ou travailler un exercice. {{ ucfirst($teacherOf->pronoun()) }} vous guide, vous répond en français simple et vous fait pratiquer l'anglais à l'oral.</p>

        <p class="course-counter">
            <x-icon name="clock" />
            <span>{{ \App\Support\DailyTime::remainingSentence($counter['remaining']) }}</span>
        </p>

        @if (! $ready)
            <div class="alert alert-info" role="status">
                <x-icon name="info" />
                <div>Choisissez d'abord la partie à travailler, <a href="#partie">juste au-dessus</a>.</div>
            </div>
        @elseif ($counter['exhausted'])
            <div class="alert alert-info" role="status">
                <x-icon name="info" />
                <div>{{ \App\Support\DailyTime::EXHAUSTED }}</div>
            </div>
            @if ($reviewSessions->isNotEmpty())
                {{-- Temps du jour épuisé : la suite est de relire ses documents de révision. --}}
                <a href="#documents" class="btn btn-accent btn-block btn-lg btn-next next-step"><x-icon name="file-text" /> Revoir mes documents de révision</a>
            @endif
        @else
            <a href="{{ route('courses.session', $course) }}" class="btn btn-accent btn-block btn-lg"><x-icon name="mic" /> Parler avec {{ $teacher }}</a>
            <p class="muted small next-step">
                Séance de {{ \App\Support\Duration::format($counter['session']) }} au plus, prise sur votre temps du jour (partagé avec la séance orale).
            </p>
        @endif
    </section>

    @if ($reviewSessions->isNotEmpty())
        <section class="card rise" style="--i: 2" id="documents" aria-labelledby="documents-titre">
            <div class="card-title">
                <span class="icon-bubble"><x-icon name="file-text" /></span>
                <h2 id="documents-titre">Mes documents de révision</h2>
            </div>
            <p class="muted small">Après chaque séance, {{ $teacher }} vous prépare un document à relire et à refaire vous-même : la leçon expliquée, vos erreurs, les phrases à retenir et un petit exercice.</p>
            @if (blank(auth()->user()?->email))
                <p class="muted small review-invite"><x-icon name="mail" /> Ajoutez votre adresse e-mail dans <a href="{{ route('account.edit') }}">« Mon compte »</a> pour recevoir vos documents de révision par e-mail.</p>
            @endif
            <ul class="review-list">
                @foreach ($reviewSessions as $session)
                    <li>
                        <span class="review-date">Séance du {{ $session->ended_at->timezone(config('app.timezone'))->translatedFormat('j F Y') }}</span>
                        <x-course-review :session="$session" />
                    </li>
                @endforeach
            </ul>
        </section>
        <script type="module" src="{{ \App\Support\Asset::url('js/course-review.js') }}"></script>
    @endif

    <section class="card rise" style="--i: 2">
        <h2>Les pages de la leçon</h2>
        <ul class="course-pages">
            @foreach ($course->pages as $page)
                <li>
                    @if ($page->isPdf())
                        <a href="{{ route('courses.page', [$course, $page]) }}" class="btn btn-outline" target="_blank" rel="noopener"><x-icon name="file-text" /> Ouvrir le PDF</a>
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
        <h2>Ce que {{ $teacher }} a lu{{ $part?->content !== null ? ' ('.$part->label().')' : '' }}</h2>
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

        @if ($byParts && $part?->content === null)
            <p class="muted">{{ $teacher }} lira la partie que vous choisirez.</p>
        @elseif ($grammar === [] && $vocabulary === [] && $exercises === [] && blank($content['summary'] ?? null))
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

    @if ($course->isPdf())
        <script type="module" src="{{ \App\Support\Asset::url('js/long-submit.js') }}"></script>
    @endif
</x-layout>
