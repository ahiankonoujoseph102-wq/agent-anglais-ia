{{--
    PHRASES UTILES pendant une séance (évaluation, séance du jour, « Mes cours ») : ce que l'élève peut DIRE à
    Teacher Joe pour demander de répéter, de ralentir ou de réexpliquer (config/teacher.php → useful_phrases,
    App\Support\Live\WorkingTogether). À lire seulement : aucun bouton ne parle à la place de l'élève, il s'entraîne
    à le dire. Déplié aux premières séances, replié ensuite (une ligne).
--}}
@props(['open' => false, 'teacherName' => null])

<details id="useful-phrases" class="useful-phrases" @if ($open) open @endif>
    <summary class="useful-head">
        <span class="useful-label"><x-icon name="messages" /> Phrases utiles</span>
        <span class="useful-hint">À dire à voix haute</span>
    </summary>
    <div class="useful-body">
        <p class="useful-intro">Vous pouvez poser vos questions en français et vous avez le droit de vous tromper. Pour demander de l'aide à {{ $teacherName ?? config('avatar.model.name') }}, dites simplement :</p>
        <ul class="useful-list">
            @foreach (\App\Support\Live\WorkingTogether::phrases() as $phrase)
                <li>
                    <span class="useful-en" lang="en">{{ $phrase['en'] }}</span>
                    <span class="useful-fr">{{ $phrase['fr'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</details>
