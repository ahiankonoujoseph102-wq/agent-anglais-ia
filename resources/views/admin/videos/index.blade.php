<x-layout title="Vidéos">
    @include('admin.nav')

    <h1>Vidéos de {{ config('avatar.model.name') }}</h1>
    <p>
        Chaque jour, {{ $perDay }} vidéos verticales de leçon d'anglais, prêtes pour TikTok et la chaîne WhatsApp :
        le site choisit le sujet, écrit le script et prépare la voix ; GitHub fabrique ensuite la vidéo.
    </p>

    <section class="card card-soft">
        <h2>État</h2>
        <ul>
            <li>Jeton de GitHub (VIDEO_UPLOAD_TOKEN) : <strong>{{ $tokenConfigured ? 'réglé' : 'non réglé' }}</strong>@unless ($tokenConfigured) <span class="muted">(sans lui, aucune vidéo n'est ajoutée chaque matin et GitHub est refusé)</span>@endunless</li>
            <li>Voix : <strong>{{ $voices === [] ? 'aucun fournisseur avec une clé' : implode(', puis ', $voices) }}</strong></li>
            <li>Sujets encore jamais traités : <strong>{{ $remaining }}</strong></li>
            <li>Fin de chaque vidéo : « {{ $closing }} »</li>
        </ul>

        <form method="POST" action="{{ route('admin.videos.prepare') }}" class="video-prepare">
            @csrf
            <label for="video-count">Préparer des vidéos maintenant</label>
            <select id="video-count" name="count">
                @foreach ([1, 2, 3] as $count)
                    <option value="{{ $count }}" @selected($count === 3)>{{ $count }} vidéo{{ $count > 1 ? 's' : '' }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-accent"><x-icon name="play" /> Préparer</button>
        </form>
        <p class="muted small">
            Script et voix prêts en quelques minutes (état « Voix prête »). Ensuite, sur GitHub : Actions, « Vidéos de
            {{ config('avatar.model.name') }} », Run workflow. Les vidéos arrivent ici environ 10 minutes par vidéo plus tard.
        </p>
    </section>

    <section id="videos">
        <h2>Vidéos</h2>

        @if ($videos->isEmpty())
            <div class="card empty-state"><span class="icon-bubble"><x-icon name="play" /></span><p>Aucune vidéo pour le moment. Touchez « Préparer » ci-dessus.</p></div>
        @endif

        @foreach ($videos as $video)
            <article class="card video-card" id="video-{{ $video->id }}">
                <div class="card-title">
                    <h3>{{ $video->title }}</h3>
                </div>
                <p class="muted small">
                    {{ $video->day->translatedFormat('l j F Y') }} · vidéo {{ $video->slot }} · niveau {{ $video->level }}
                    @if ($video->duration_seconds) · {{ \App\Support\Duration::formatPrecise($video->duration_seconds) }} @endif
                    @if ($video->size_bytes) · {{ number_format($video->size_bytes / 1048576, 1, ',', ' ') }} mégaoctets @endif
                </p>
                <p>
                    <span @class(['badge', 'badge-accent' => $video->status === \App\Models\LessonVideo::READY, 'badge-alert' => $video->status === \App\Models\LessonVideo::FAILED])>{{ $video->statusLabel() }}</span>
                    @if ($video->published_at)<span class="badge">Publiée</span>@endif
                </p>
                @if ($video->error)
                    <p class="muted small">Dernière erreur : {{ $video->error }}</p>
                @endif

                @if ($video->status === \App\Models\LessonVideo::READY)
                    <video class="video-preview" controls preload="none" playsinline src="{{ route('admin.videos.file', $video) }}"></video>
                    <div class="voice-actions">
                        <a class="btn btn-accent" href="{{ route('admin.videos.file', ['video' => $video, 'telecharger' => 1]) }}" download="{{ $video->downloadName() }}"><x-icon name="download" /> Télécharger</a>
                    </div>

                    <h4>Texte de publication</h4>
                    <p class="alert alert-info small"><x-icon name="info" /> {{ $reminder }}</p>
                    <textarea class="video-caption" rows="6" readonly aria-label="Texte de publication">{{ $video->caption }}</textarea>
                    <button type="button" class="btn btn-light" data-copy="{{ $video->caption }}" data-copied="Texte copié"><x-icon name="copy" /> Copier le texte</button>

                    <form method="POST" action="{{ route('admin.videos.publish', $video) }}" class="video-published">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="page" value="{{ $videos->currentPage() }}">
                        <label><input type="checkbox" name="published" value="1" @checked($video->published_at) data-autosubmit> Publiée</label>
                        <button type="submit" class="btn btn-light" data-autosubmit-fallback>Enregistrer</button>
                    </form>
                @elseif ($video->status === \App\Models\LessonVideo::FAILED)
                    <form method="POST" action="{{ route('admin.videos.retry', $video) }}">
                        @csrf
                        <button type="submit" class="btn btn-accent"><x-icon name="refresh" /> Réessayer</button>
                    </form>
                @endif
            </article>
        @endforeach

        {{ $videos->links() }}
    </section>

    <script type="module" src="{{ \App\Support\Asset::url('js/copy.js') }}"></script>
    <script type="module" src="{{ \App\Support\Asset::url('js/admin-videos.js') }}"></script>
</x-layout>
