{{--
    TABLEAU de la séance du jour (App\Support\Curriculum\LessonBoards) : étape en cours (Leçon, Pratique, Conversation,
    Bilan) et éléments qui s'allument quand Teacher Joe en parle (public/js/lesson-board.js). Téléphone : sous Teacher Joe,
    replié (la ligne du haut montre l'étape et l'élément en cours) ; ordinateur et téléphone couché : dans la colonne de
    droite, ouvert. Traductions : visibles en A1 et A2, discrètes en B1, masquées ensuite (case « Voir les traductions »).
--}}
@props(['board', 'translations' => 'show'])

<details id="board" class="board" data-translations="{{ $translations }}" data-phase="lesson">
    <summary class="board-head">
        <span class="board-label"><x-icon name="list" /> Tableau</span>
        <span class="board-phase" data-board-phase>Leçon</span>
        <span class="board-now" data-board-now aria-live="polite"></span>
    </summary>
    <div class="board-body">
        <h2 class="board-title" lang="en">{{ $board['title'] }}</h2>

        @if ($translations !== 'show')
            <label class="board-toggle"><input type="checkbox" data-board-translations> Voir les traductions</label>
        @endif

        @if ($board['phrases'] !== [])
            <h3 class="board-heading">Phrases clés</h3>
            <ul class="board-list">
                @foreach ($board['phrases'] as $i => $phrase)
                    <li class="board-item" data-board-item="p{{ $i }}" data-kind="phrase" data-say="{{ $phrase['en'] }}">
                        <span class="board-en" lang="en">{{ $phrase['en'] }}</span>
                        @if ($phrase['fr'])
                            <span class="board-fr">{{ $phrase['fr'] }}</span>
                        @elseif (! empty($phrase['use']))
                            <span class="board-fr">{{ $phrase['use'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($board['vocabulary'] !== [])
            <h3 class="board-heading">Vocabulaire</h3>
            <ul class="board-words">
                @foreach ($board['vocabulary'] as $i => $word)
                    <li class="board-item board-word" data-board-item="w{{ $i }}" data-kind="word" data-say="{{ $word['en'] }}">
                        <span class="board-en" lang="en">{{ $word['en'] }}</span>
                        @if ($word['fr'])
                            <span class="board-fr">{{ $word['fr'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($board['verbs'] !== [])
            <h3 class="board-heading">Verbes</h3>
            <ul class="board-list">
                @foreach ($board['verbs'] as $i => $verb)
                    <li class="board-item" data-board-item="b{{ $i }}" data-kind="verb" data-say="{{ $verb['forms'] }}">
                        <span class="board-en" lang="en">{{ $verb['forms'] }}</span>
                        @if ($verb['fr'])
                            <span class="board-fr">{{ $verb['fr'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($board['rule']['en'] || $board['rule']['fr'])
            <h3 class="board-heading">La règle</h3>
            <p class="board-item board-rule" @if ($board['rule']['en']) data-board-item="r" data-kind="phrase" data-say="{{ $board['rule']['en'] }}" @endif>
                @if ($board['rule']['en'])
                    <span class="board-en" lang="en">{{ $board['rule']['en'] }}</span>
                @endif
                @if ($board['rule']['fr'])
                    <span class="board-fr">{{ $board['rule']['fr'] }}</span>
                @endif
            </p>
        @endif
    </div>
</details>
