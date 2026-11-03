{{--
    « Réviser à voix haute » (App\Http\Controllers\Program\PracticeController) : un élément du tableau à la fois
    (phrase clé, mot, verbe), à écouter, réécouter plus lentement, répéter, enregistrer et comparer
    (public/js/practice.js). L'enregistrement reste dans le navigateur. Sans JavaScript : la liste à lire.
--}}
@php
    $kinds = ['phrase' => 'Phrase clé', 'word' => 'Mot', 'verb' => 'Verbe'];
    $count = count($items);
@endphp

<x-layout title="Réviser à voix haute">
    <div class="page-head rise">
        <h1>Réviser à voix haute</h1>
        <p class="muted small">Écoutez, répétez, enregistrez-vous et comparez avec {{ $teacher->name() }}.</p>
    </div>

    <section class="card practice rise" style="--i: 1" id="practice"
        data-practice
        data-audio-url="{{ route('practice.audio', [$lesson, 0]) }}"
        data-status-url="{{ route('practice.status', $lesson) }}"
        data-ready='@json($ready)'
        data-slow-rate="{{ (float) config('platform.practice.slow_rate', 0.75) }}"
        data-record-seconds="{{ (int) config('platform.practice.record_seconds', 12) }}"
        data-teacher="{{ $teacher->name() }}">

        <p class="practice-progress" data-practice-progress hidden>
            <span data-practice-count>1 sur {{ $count }}</span>
            <span class="practice-bar" aria-hidden="true"><span data-practice-bar style="--p: {{ round(100 / $count) }}%"></span></span>
        </p>

        <ol class="practice-list">
            @foreach ($items as $index => $item)
                <li class="practice-item" data-practice-item="{{ $index }}">
                    <span class="badge badge-muted">{{ $kinds[$item['kind']] ?? 'Phrase' }}</span>
                    <p class="practice-en" lang="en">{{ $item['en'] }}</p>
                    @if ($item['fr'])
                        <p class="practice-fr">{{ $item['fr'] }}</p>
                    @endif
                </li>
            @endforeach
        </ol>

        <div class="practice-controls" data-practice-controls hidden>
            <div class="practice-row">
                <button type="button" class="btn btn-outline" data-practice-listen><x-icon name="headphones" /> Écouter</button>
                <button type="button" class="btn btn-outline" data-practice-slow><x-icon name="hourglass" /> Lentement</button>
            </div>
            <div class="practice-row">
                <button type="button" class="btn btn-outline practice-record" data-practice-record><x-icon name="mic" /> <span data-practice-record-label>M'enregistrer</span></button>
                <button type="button" class="btn btn-outline" data-practice-compare disabled aria-label="Comparer : {{ $teacher->name() }}, puis moi"><x-icon name="refresh" /> Comparer</button>
            </div>
            <button type="button" class="btn btn-link practice-mine" data-practice-mine disabled><x-icon name="play" /> Réécouter seulement ma voix</button>
            <p class="muted small practice-note" data-practice-note aria-live="polite"></p>

            <div class="practice-nav">
                <button type="button" class="btn btn-link" data-practice-prev disabled><x-icon name="arrow-left" /> Précédent</button>
                <button type="button" class="btn btn-accent btn-next" data-practice-next>Suivant <x-icon name="arrow-right" /></button>
            </div>
        </div>

        <div class="practice-done" data-practice-done hidden tabindex="-1">
            <h2>Bravo, révision terminée !</h2>
            <p>Redites ces phrases demain, sans regarder : c'est comme ça qu'on les garde.</p>
            <div class="actions">
                <a href="{{ route('dashboard') }}" class="btn btn-accent btn-lg btn-block btn-next"><x-icon name="home" /> Revenir à mon espace</a>
                <button type="button" class="btn btn-outline btn-block" data-practice-restart><x-icon name="refresh" /> Recommencer</button>
            </div>
        </div>

        <noscript><p class="muted small">Lisez chaque élément à voix haute. Pour écouter la voix de {{ $teacher->name() }}, activez JavaScript.</p></noscript>
    </section>

    <details class="card rise" style="--i: 2">
        <summary><strong>Comment bien réviser</strong></summary>
        <ol>
            <li>Écoutez l'élément, puis répétez-le à voix haute deux ou trois fois.</li>
            <li>Difficile ? Touchez « Lentement », puis répétez morceau par morceau.</li>
            <li>Enregistrez-vous, puis touchez « Comparer » : écoutez le rythme et les sons qui changent.</li>
            <li>Dites la phrase dans une situation à vous : c'est ce que {{ $teacher->name() }} vous fera faire à la prochaine séance.</li>
        </ol>
        <p class="muted small">Votre enregistrement reste sur votre téléphone : il n'est jamais envoyé. Cette révision ne prend rien sur votre temps du jour.</p>
    </details>

    <p><a href="{{ route('lessons.history') }}#seance-{{ $lesson->id }}"><x-icon name="arrow-left" /> Revenir à Mes séances</a></p>

    <script type="module" src="{{ \App\Support\Asset::url('js/practice.js') }}"></script>
</x-layout>
