@php
    use App\Support\Duration;
    use App\Support\Live\LiveProviders;
    use App\Support\Live\Transcripts;
@endphp

<x-layout :title="'Conversation de '.$user->name">
    @include('admin.nav')

    <p><a href="{{ route('admin.users.show', $user) }}#conversations"><x-icon name="arrow-left" /> {{ $user->name }}</a></p>

    {{-- En haut : type de séance, date (heure de Lomé), durée, fournisseur et verdict. --}}
    <section class="card conversation-head" aria-labelledby="conversation-title">
        <h1 id="conversation-title">{{ $label }}</h1>
        <ul class="conversation-facts">
            <li><x-icon name="calendar" /> {{ $conversation->started_at->timezone('Africa/Lome')->translatedFormat('j F Y à H:i') }}</li>
            <li><x-icon name="clock" /> {{ Duration::formatPrecise((int) $conversation->used_seconds) }}</li>
            <li><x-icon name="mic" /> {{ LiveProviders::label($conversation->liveProvider()) }}</li>
            <li><x-icon name="user" /> {{ $conversation->teacher()->name() }}</li>
            <li><x-icon name="check" /> {{ $verdict ?? 'Aucun verdict' }}</li>
        </ul>
        {{-- Délais de réponse de Teacher Joe : de la fin de la parole de l'apprenant à son premier son. --}}
        @if ($delays['count'] > 0)
            <p class="conversation-delays">
                Délai de réponse : médian <strong>{{ Transcripts::seconds($delays['median']) }}</strong>,
                le plus long <strong @class(['chat-delay-slow' => $delays['max'] > $slowMs])>{{ Transcripts::seconds($delays['max']) }}</strong>
                ({{ $delays['count'] }} {{ $delays['count'] > 1 ? 'réponses mesurées' : 'réponse mesurée' }}).
            </p>
        @else
            <p class="conversation-delays muted">Délai de réponse : aucune réponse mesurée pour cette séance.</p>
        @endif
        <p class="muted small">Texte de la conversation, réplique par réplique, à l'heure où la parole a commencé (heure de Lomé). La voix n'est jamais enregistrée ; ce texte et les mesures sont effacés automatiquement après {{ (int) config('platform.transcripts.keep_days') }} jours.</p>
    </section>

    @if ($lines->isEmpty())
        <div class="card empty-state"><span class="icon-bubble"><x-icon name="list" /></span><p>Aucune réplique enregistrée pour cette séance.</p></div>
    @else
        <ol class="chat" aria-label="Conversation">
            @foreach ($timeline as $item)
                @if (isset($item['event']))
                    {{-- Événement de la séance : petite ligne grise. --}}
                    <li class="chat-event">
                        <time datetime="{{ $item['event']->happened_at->toIso8601String() }}">{{ $item['event']->happened_at->timezone('Africa/Lome')->format('H:i:s') }}</time>
                        {{ $item['event']->label() }}@if ($item['event']->detail) : {{ $item['event']->detail }}@endif
                    </li>
                @else
                    @php($line = $item['line'])
                    <li @class(['chat-bubble', 'chat-learner' => $line->isLearner(), 'chat-teacher' => ! $line->isLearner()])>
                        <p class="chat-meta">
                            <strong>{{ $line->isLearner() ? $user->name : $conversation->teacher()->name() }}</strong>
                            <time datetime="{{ $line->spoken_at->toIso8601String() }}">{{ $line->spoken_at->timezone('Africa/Lome')->format('H:i:s') }}</time>
                        </p>
                        <p class="chat-text">{{ $line->text }}</p>
                        @if (($delay = $line->delayMs()) !== null)
                            <p @class(['chat-delay', 'chat-delay-slow' => $delay > $slowMs])>répond en {{ Transcripts::seconds($delay) }}</p>
                        @endif
                    </li>
                @endif
            @endforeach
        </ol>
    @endif
</x-layout>
