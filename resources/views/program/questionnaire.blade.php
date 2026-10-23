@php
    // « Modifier mes objectifs » (ProgramAnswersController) : même questionnaire, pré-rempli, autres adresses.
    $editing ??= null;
    $action = $editing ? route('program.answers.update', [$editing, $step]) : route('program.questionnaire.store', $step);
    $previous = $editing ? route('program.answers.edit', [$editing, $step - 1]) : route('program.questionnaire', $step - 1);
@endphp

<x-layout :title="$editing ? 'Modifier mes objectifs' : 'Mon programme personnel'" narrow>
    <x-form-aside label="À quoi servent vos réponses">
        <x-stepper :labels="['Objectif', 'Métier', 'Jours', 'Difficultés']" :current="$step" label="Étapes du questionnaire" />

        <div class="step-panel">
        <div class="page-head">
            @if ($editing)
                <h1>Modifier mes objectifs</h1>
                <p class="muted">
                    Niveau {{ $level->value }} ({{ $level->label() }}) — question {{ $step }} sur {{ $steps }}.
                    Vos réponses actuelles sont déjà cochées : changez ce que vous voulez. Votre progression est gardée ; seules les leçons à venir s'adaptent.
                </p>
            @else
                <h1>Construisons votre programme</h1>
                <p class="muted">
                    Niveau {{ $level->value }} ({{ $level->label() }}) — question {{ $step }} sur {{ $steps }}.
                    Vos réponses servent à choisir les situations et le rythme de vos séances.
                </p>
            @endif
        </div>

        <form method="POST" action="{{ $action }}" class="card form-card" novalidate>
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
                <fieldset class="field">
                    <legend>Vous êtes… (facultatif)</legend>
                    <div class="profiles">
                        @foreach ($profiles as $profile)
                            <label class="checkbox option">
                                <input type="radio" name="profile" value="{{ $profile->value }}" @checked(old('profile', $answers['profile'] ?? null) === $profile->value)>
                                {{ $profile->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('profile') <div class="error">{{ $message }}</div> @enderror
                </fieldset>
                <x-input name="occupation" label="Votre métier ou votre situation" :value="$answers['occupation'] ?? null"
                         hint="Par exemple : commerçante au marché, étudiant en droit, enseignante d'anglais, infirmier, chauffeur, comptable…" required />
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

            <button type="submit" class="btn btn-accent btn-block btn-lg">{{ $step < $steps ? 'Continuer' : ($editing ? 'Enregistrer mes objectifs' : 'Construire mon programme') }} <x-icon name="arrow-right" /></button>
        </form>

        @if ($step > 1)
            <p><a class="back-link" href="{{ $previous }}"><x-icon name="arrow-left" /> Question précédente</a></p>
        @endif
        @if ($editing)
            <p><a class="back-link" href="{{ route('program.show') }}"><x-icon name="arrow-left" /> Retour à mon programme, sans rien changer</a></p>
        @endif
        </div>

        <x-slot:aside>
            <h2 class="aside-title"><x-icon name="info" /> À quoi servent vos réponses</h2>
            <ul class="aside-list">
                <li><strong>Votre objectif</strong> : dans chaque unité, les situations qui s'en rapprochent passent en premier.</li>
                <li><strong>Votre métier et vos centres d'intérêt</strong> : les situations proches de votre vie viennent ensuite, et {{ config('avatar.model.name') }} les connaît.</li>
                <li><strong>Vos jours</strong> : une leçon par jour de travail, dans votre calendrier.</li>
                <li><strong>Vos difficultés</strong> : {{ config('avatar.model.name') }} les connaît dès le début de chaque séance.</li>
            </ul>
            <p class="muted small">Vous pourrez changer vos réponses plus tard avec « Modifier mes objectifs ».</p>
        </x-slot:aside>
    </x-form-aside>
</x-layout>
