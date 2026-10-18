@php
    use App\Support\Duration;
    use App\Support\Live\LiveProviders;
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
            <li><x-icon name="check" /> {{ $verdict ?? 'Aucun verdict' }}</li>
        </ul>
        <p class="muted small">Texte de la conversation, réplique par réplique (heure de Lomé). La voix n'est jamais enregistrée ; ce texte est effacé automatiquement après {{ (int) config('platform.transcripts.keep_days') }} jours.</p>
    </section>

    @if ($lines->isEmpty())
        <div class="card empty-state"><span class="icon-bubble"><x-icon name="list" /></span><p>Aucune réplique enregistrée pour cette séance.</p></div>
    @else
        <ol class="chat" aria-label="Conversation">
            @foreach ($lines as $line)
                <li @class(['chat-bubble', 'chat-learner' => $line->isLearner(), 'chat-teacher' => ! $line->isLearner()])>
                    <p class="chat-meta">
                        <strong>{{ $line->isLearner() ? $user->name : config('avatar.model.name') }}</strong>
                        <time datetime="{{ $line->spoken_at->toIso8601String() }}">{{ $line->spoken_at->timezone('Africa/Lome')->format('H:i:s') }}</time>
                    </p>
                    <p class="chat-text">{{ $line->text }}</p>
                </li>
            @endforeach
        </ol>
    @endif
</x-layout>
