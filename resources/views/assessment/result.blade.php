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
                @include('assessment.result-next')
                {{-- Partage volontaire du niveau réellement trouvé (App\Support\ShareLinks) : rien ne part sans ce toucher. --}}
                <div class="result-share">
                    <a class="btn btn-outline" href="{{ \App\Support\ShareLinks::levelUrl($found) }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Partager mon niveau sur WhatsApp</a>
                    <p class="muted small">Vous choisissez à qui l'envoyer dans WhatsApp.</p>
                </div>
                @if ($assessment->feedback)
                    <div class="result-opinion">
                        <h2 class="result-opinion-title">L'avis de {{ $teacher }}</h2>
                        <p class="result-feedback">{{ $assessment->feedback }}</p>
                    </div>
                @endif

                {{-- Préparé à part : @json coupe son expression aux virgules. --}}
                @php($voiceWait = config('platform.speech.messages') + ['poll_seconds' => config('platform.speech.wait_poll_seconds'), 'late_seconds' => config('platform.speech.late_after_seconds'), 'give_up_seconds' => config('platform.speech.give_up_after_seconds')])
                <div class="result-voice" data-level-message
                     data-status-url="{{ route('assessment.voice.status') }}"
                     data-voice-wait="{{ Asset::url('js/voice-wait.js') }}"
                     data-wait='@json($voiceWait)'>
                    <button type="button" class="btn btn-outline" data-level-play aria-pressed="false">
                        <span data-play-label><x-icon name="play" /> Écouter {{ $teacher }}</span>
                        <span data-pause-label hidden><x-icon name="pause" /> Mettre en pause</span>
                    </button>
                    <audio preload="none" data-level-audio data-src="{{ route('assessment.voice') }}"></audio>
                    <p class="muted small" data-level-status role="status" hidden></p>
                </div>
                <details class="result-transcript" data-level-text>
                    <summary>Lire le message de {{ $teacher }}</summary>
                    <p>{{ $messageText }}</p>
                </details>
            @else
                <span class="eyebrow">Évaluation terminée</span>
                <h1 id="niveau">Nous n'avons pas pu mesurer votre niveau</h1>
                @php($helpWhatsapp = \App\Support\SiteSettings::contact()['whatsapp'] ?? null)
                <p class="result-feedback">L'évaluation s'est arrêtée avant que {{ $teacher }} ait assez entendu votre anglais.</p>
                @include('assessment.result-next')
                @if ($helpWhatsapp)
                    <p class="muted small result-help">Une question ? Notre équipe vous répond sur WhatsApp.</p>
                    {{-- Évaluation interrompue : écrire directement à l'équipe (numéro WhatsApp des Réglages, sinon de la configuration). --}}
                    <a class="btn btn-whatsapp result-whatsapp" href="{{ \App\Support\WhatsApp::url($helpWhatsapp, 'Bonjour '.config('platform.name').', mon évaluation gratuite s\'est arrêtée avant la fin. ') }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Écrire sur WhatsApp</a>
                @endif
            @endif
        </div>
    </section>

    {{--
        Sans abonnement : « Voici votre première leçon », le tableau de la première leçon du niveau (unité 1, leçon 1 ;
        App\Support\Curriculum\LessonBoards::firstLesson), juste au-dessus des formules. Affichage seulement.
    --}}
    @if ($firstLesson)
        <section class="section-tight first-lesson reveal" aria-labelledby="premiere-lecon">
            <span class="eyebrow">Niveau {{ $lessonLevel->value }}</span>
            <h2 id="premiere-lecon">Voici votre première leçon avec {{ $teacher }}</h2>
            <p class="muted">Au tableau, les phrases et les mots de votre première séance. {{ $teacher }} vous les enseigne un par un, vous les répétez, puis vous les utilisez dans une conversation.</p>
            <div class="board board-preview">
                <div class="board-head">
                    <span class="board-label"><x-icon name="list" /> Tableau</span>
                    <span class="board-phase">Leçon 1</span>
                </div>
                <div class="board-body">
                    <p class="board-title">{{ $firstLesson['title'] }}</p>
                    @if ($firstLesson['phrases'] !== [])
                        <p class="board-heading">Phrases clés</p>
                        <ul class="board-list">
                            @foreach (array_slice($firstLesson['phrases'], 0, 4) as $phrase)
                                <li class="board-item">
                                    <span class="board-en" lang="en">{{ $phrase['en'] }}</span>
                                    @if ($phrase['fr'] ?? $phrase['use'] ?? null)
                                        <span class="board-fr">{{ $phrase['fr'] ?? $phrase['use'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($firstLesson['vocabulary'] !== [])
                        <p class="board-heading">Vocabulaire</p>
                        <ul class="board-words">
                            @foreach (array_slice($firstLesson['vocabulary'], 0, 6) as $word)
                                <li class="board-item">
                                    <span class="board-en" lang="en">{{ $word['en'] }}</span>
                                    @if ($word['fr'])
                                        <span class="board-fr">{{ $word['fr'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- Accès déjà ouvert : formules repliées sous « Voir les formules ». Sans abonnement : bien visibles. --}}
    @if ($hasSubscription)
        <details class="plans-fold reveal">
            <summary class="btn btn-outline btn-block">Voir les formules</summary>
    @endif
    <section class="section-tight" aria-labelledby="formules">
        <span class="eyebrow">Votre prochaine étape</span>
        <h2 id="formules">Commencez votre programme {{ $chosen?->value ?? $found?->value }} dès aujourd'hui</h2>
        <p class="muted">
            Chaque jour, une séance orale avec {{ $teacher }}, à votre niveau : il vous fait parler, corrige vos erreurs
            et les reprend aux séances suivantes. Plus vous commencez tôt, plus vite l'anglais devient une habitude.
        </p>
        @include('assessment.plans')
        <p class="muted small next-step"><x-icon name="book" /> Chaque formule comprend aussi l'aide à vos cours d'anglais (« Mes cours »), en plus de l'oral.</p>
        <p class="muted small next-step">Paiement par Mobile Money depuis votre téléphone. Vous choisissez ensuite vos jours de séance.</p>
    </section>
    @if ($hasSubscription)
        </details>
    @endif

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
                    <button type="submit" class="btn btn-outline">Enregistrer ce niveau</button>
                </form>
            </details>
        </section>
    @endif

    <x-invite-friend class="card section-tight reveal" />

    <script type="module" src="{{ Asset::url('js/level-message.js') }}"></script>
</x-layout>
