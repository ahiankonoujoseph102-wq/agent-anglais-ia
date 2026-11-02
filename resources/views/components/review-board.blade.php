{{--
    Tableau d'une séance du jour, gardé pour réviser (« Mes séances ») : les mêmes phrases, mots, verbes, règle et
    piège que pendant la séance (App\Support\Curriculum\LessonBoards::for, jamais d'appel), traductions toujours
    visibles, sans rien qui s'allume. Replié ; ouvert quand l'adresse vise la séance (lien de « Mon espace »).
--}}
@props(['board', 'open' => false])

<details class="board board-review" @if ($open) open @endif>
    <summary class="board-head">
        <span class="board-label"><x-icon name="list" /> Le tableau de cette séance</span>
    </summary>
    <div class="board-body">
        <h4 class="board-title" lang="en">{{ $board['title'] }}</h4>

        @if ($board['phrases'] !== [])
            <h5 class="board-heading">Phrases clés</h5>
            <ul class="board-list">
                @foreach ($board['phrases'] as $phrase)
                    <li class="board-item">
                        <span class="board-en" lang="en">{{ $phrase['en'] }}</span>
                        @if ($phrase['fr'] || ! empty($phrase['use']))
                            <span class="board-fr">{{ $phrase['fr'] ?: $phrase['use'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($board['vocabulary'] !== [])
            <h5 class="board-heading">Vocabulaire</h5>
            <ul class="board-words">
                @foreach ($board['vocabulary'] as $word)
                    <li class="board-item">
                        <span class="board-en" lang="en">{{ $word['en'] }}</span>
                        @if ($word['fr'])<span class="board-fr">{{ $word['fr'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($board['verbs'] !== [])
            <h5 class="board-heading">Verbes</h5>
            <ul class="board-list">
                @foreach ($board['verbs'] as $verb)
                    <li class="board-item">
                        <span class="board-en" lang="en">{{ $verb['forms'] }}</span>
                        @if ($verb['fr'])<span class="board-fr">{{ $verb['fr'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($board['rule']['en'] || $board['rule']['fr'])
            <h5 class="board-heading">La règle</h5>
            <p class="board-item">
                @if ($board['rule']['en'])<span class="board-en" lang="en">{{ $board['rule']['en'] }}</span>@endif
                @if ($board['rule']['fr'])<span class="board-fr">{{ $board['rule']['fr'] }}</span>@endif
            </p>
        @endif

        @if (! empty($board['pitfall']))
            <h5 class="board-heading"><x-icon name="alert" /> Attention, piège</h5>
            <p class="board-pitfall">{{ $board['pitfall'] }}</p>
        @endif
    </div>
</details>
