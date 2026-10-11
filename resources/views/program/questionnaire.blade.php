<x-layout title="Mon programme personnel" narrow>
    <ol class="pay-steps" aria-label="Étapes du questionnaire">
        @foreach (['Objectif', 'Métier', 'Jours', 'Difficultés'] as $i => $label)
            <li @class(['done' => $i + 1 < $step, 'current' => $i + 1 === $step]) @if ($i + 1 === $step) aria-current="step" @endif>
                <span>{{ $i + 1 }}</span> {{ $label }}
            </li>
        @endforeach
    </ol>

    <h1>Construisons votre programme</h1>
    <p class="muted">
        Niveau {{ $level->value }} ({{ $level->label() }}) — question {{ $step }} sur {{ $steps }}.
        Vos réponses servent à choisir les situations et le rythme de vos séances.
    </p>

    <form method="POST" action="{{ route('program.questionnaire.store', $step) }}" class="card" novalidate>
        @csrf

        @if ($step === 1)
            <fieldset class="field">
                <legend>Pourquoi apprenez-vous l'anglais ? (votre objectif principal)</legend>
                @foreach ($goals as $goal)
                    <label class="checkbox option">
                        <input type="radio" name="goal" value="{{ $goal->value }}" @checked(old('goal', $answers['goal'] ?? null) === $goal->value) required>
                        {{ $goal->label() }}
                    </label>
                @endforeach
                @error('goal') <div class="error">{{ $message }}</div> @enderror
            </fieldset>
        @elseif ($step === 2)
            <x-input name="occupation" label="Votre métier ou votre situation" :value="$answers['occupation'] ?? null"
                     hint="Par exemple : commerçante au marché, étudiant en droit, infirmier, chauffeur, comptable…" required autofocus />
            <x-input name="interests" label="Vos centres d'intérêt (facultatif)" :value="$answers['interests'] ?? null"
                     hint="Par exemple : football, musique, cuisine, informatique, religion, voyages." />
        @elseif ($step === 3)
            <fieldset class="field">
                <legend>Quels jours voulez-vous travailler ?</legend>
                <div class="days">
                    @foreach ($days as $number => $day)
                        <label class="checkbox option">
                            <input type="checkbox" name="study_days[]" value="{{ $number }}"
                                   @checked(in_array($number, array_map('intval', old('study_days', $answers['study_days'] ?? [1, 2, 3, 4, 5])), true))>
                            {{ $day }}
                        </label>
                    @endforeach
                </div>
                @error('study_days') <div class="error">{{ $message }}</div> @enderror
            </fieldset>
            <x-input name="study_time" label="À quelle heure ? (facultatif)" type="time" :value="$answers['study_time'] ?? '18:00'" />
        @else
            <fieldset class="field">
                <legend>Qu'est-ce que vous trouvez le plus difficile ? (plusieurs choix possibles)</legend>
                @foreach ($difficulties as $difficulty)
                    <label class="checkbox option">
                        <input type="checkbox" name="difficulties[]" value="{{ $difficulty->value }}"
                               @checked(in_array($difficulty->value, old('difficulties', $answers['difficulties'] ?? []), true))>
                        {{ $difficulty->label() }}
                    </label>
                @endforeach
                @error('difficulties') <div class="error">{{ $message }}</div> @enderror
            </fieldset>
        @endif

        <button type="submit" class="btn btn-block">{{ $step < $steps ? 'Continuer' : 'Construire mon programme' }}</button>
    </form>

    @if ($step > 1)
        <p><a href="{{ route('program.questionnaire', $step - 1) }}">← Question précédente</a></p>
    @endif
</x-layout>
