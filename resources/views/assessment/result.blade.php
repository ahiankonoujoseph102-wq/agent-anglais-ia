@php
    use App\Support\Asset;

    $teacher = config('avatar.model.name');
    // Niveau affiché : celui de l'évaluation (recommandé) ; un autre niveau choisi est signalé.
    $foundRank = $found ? $rank[$found->value] : null;
@endphp

<x-layout title="Votre résultat" :description="'Votre niveau d\'anglais après l\'évaluation gratuite avec '.$teacher.'.'">
    <section class="result-hero rise" aria-labelledby="niveau">
        <div class="result-teacher">
            <img src="{{ Asset::url('images/teacher-joe.webp') }}" width="560" height="560" alt="{{ $teacher }}, votre professeur d'anglais">
        </div>
        <div class="result-main">
            @if ($found)
                <span class="eyebrow">Évaluation terminée</span>
                <h1 id="niveau">Votre niveau : <span class="result-level">{{ $found->value }}</span></h1>
                <p class="result-label">{{ $found->label() }}</p>
                @if ($assessment->feedback)
                    <p class="result-feedback">{{ $assessment->feedback }}</p>
                @endif

                <div class="result-voice" data-level-message>
                    <button type="button" class="btn btn-lg" data-level-play aria-pressed="false">
                        <span data-play-label><x-icon name="play" /> Écouter {{ $teacher }}</span>
                        <span data-pause-label hidden><x-icon name="pause" /> Mettre en pause</span>
                    </button>
                    <audio preload="none" data-level-audio data-src="{{ route('assessment.voice') }}"></audio>
                    <p class="muted small" data-level-status role="status" hidden></p>
                </div>
                <details class="result-transcript">
                    <summary>Lire le message de {{ $teacher }}</summary>
                    <p>{{ $messageText }}</p>
                </details>
            @else
                <span class="eyebrow">Évaluation terminée</span>
                <h1 id="niveau">Nous n'avons pas pu mesurer votre niveau</h1>
                <p class="result-feedback">L'évaluation s'est arrêtée avant que {{ $teacher }} ait assez entendu votre anglais. Choisissez ci-dessous le niveau qui vous ressemble, ou écrivez-nous sur WhatsApp depuis la page « Aide ».</p>
            @endif
        </div>
    </section>

    @if ($hasSubscription)
        <div class="card next-card reveal">
            <span class="icon-bubble icon-bubble-primary"><x-icon name="mic" /></span>
            <div>
                <h2>Votre accès est ouvert</h2>
                <p>Retrouvez votre programme et votre séance du jour dans votre espace.</p>
            </div>
            <a class="btn btn-accent" href="{{ route('dashboard') }}">Aller à mon espace <x-icon name="arrow-right" /></a>
        </div>
    @endif

    <section class="section-tight" aria-labelledby="formules">
        <span class="eyebrow">Votre prochaine étape</span>
        <h2 id="formules">Commencez votre programme {{ $chosen?->value ?? $found?->value }} dès aujourd'hui</h2>
        <p class="muted">
            Chaque jour, une séance orale avec {{ $teacher }}, à votre niveau : il vous fait parler, corrige vos erreurs
            et les reprend aux séances suivantes. Plus vous commencez tôt, plus vite l'anglais devient une habitude.
        </p>
        @include('assessment.plans')
        <p class="muted small next-step">Paiement par Mobile Money depuis votre téléphone. Vous choisissez ensuite vos jours de séance.</p>
    </section>

    @if ($canChoose)
        <section class="section-tight" aria-labelledby="autre-niveau">
            <details class="card level-picker" @if ($errors->has('level')) open @endif>
                <summary><h2 id="autre-niveau">Choisir un autre niveau</h2></summary>
                <p class="muted">
                    Votre programme sera construit au niveau choisi.
                    @if ($found) Le niveau recommandé est celui de votre évaluation. @endif
                </p>
                <form method="POST" action="{{ route('assessment.level') }}" class="level-choice">
                    @csrf
                    @method('PUT')
                    <div class="level-options">
                        @foreach ($levels as $level)
                            @php($higher = $foundRank !== null && $rank[$level->value] > $foundRank)
                            <label class="level-option">
                                <input type="radio" name="level" value="{{ $level->value }}" @checked(($chosen ?? $found) === $level) @if ($higher) data-higher @endif>
                                <span class="level-option-body">
                                    <strong>{{ $level->value }}</strong>
                                    <span>{{ $level->label() }}</span>
                                    @if ($found === $level)
                                        <span class="badge badge-accent">Recommandé</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('level') <p class="error">{{ $message }}</p> @enderror
                    <div class="alert alert-warning level-warning" role="note">
                        <x-icon name="alert" />
                        <div><strong>Attention : ce niveau est plus élevé que celui de votre évaluation.</strong> Les séances risquent d'aller trop vite pour vous. Vous pourrez revenir au niveau recommandé tant que votre programme n'est pas construit.</div>
                    </div>
                    <button type="submit" class="btn">Enregistrer ce niveau</button>
                </form>
            </details>
        </section>
    @endif

    <script type="module" src="{{ Asset::url('js/level-message.js') }}"></script>
</x-layout>
