<x-layout title="Vidéos">
    @include('admin.nav')

    <h1>Vidéos de {{ config('avatar.model.name') }}</h1>
    <p>
        Chaque jour, {{ $perDay }} vidéos verticales de leçon d'anglais, prêtes pour TikTok et la chaîne WhatsApp :
        le site choisit le sujet, écrit le script et prépare la voix ; GitHub fabrique ensuite la vidéo.
    </p>

    @if ($blocked)
        <div class="alert alert-error" role="alert"><x-icon name="alert" /><div><strong>Fabrication bloquée.</strong> {{ $blocked }}</div></div>
    @endif

    <section class="card" id="reglages-videos" aria-labelledby="reglages-videos-titre">
        <h2 id="reglages-videos-titre">Fin de chaque vidéo</h2>
        <p class="muted small">Toujours dans cet ordre, dit par {{ config('avatar.model.name') }} et sous-titré : la question du jour (une par vidéo, pour les commentaires), puis :</p>
        <ol>
            <li>« {{ $subscribe }} »</li>
            <li>@if ($closing) « {{ $closing }} » (adresse affichée en gros) @else <em>l'invitation à évaluer son niveau gratuitement sur le site, dès que l'adresse est renseignée ci-dessous</em> @endif</li>
        </ol>
        <form method="POST" action="{{ route('admin.videos.site') }}" class="video-site">
            @csrf
            @method('PUT')
            <x-input name="site" label="Adresse du site dans les vidéos" :value="old('site', $site)" placeholder="talkami.laravel.cloud" hint="Sans « https:// ». Dite et affichée à la fin de chaque vidéo. Champ vide : la fabrication est bloquée." />
            <button type="submit" class="btn btn-accent">Enregistrer l'adresse</button>
        </form>
    </section>

    <section class="card card-soft">
        <h2>État</h2>
        <ul>
            <li>Jeton de GitHub (VIDEO_UPLOAD_TOKEN) : <strong>{{ $tokenConfigured ? 'réglé' : 'non réglé' }}</strong>@unless ($tokenConfigured) <span class="muted">(sans lui, aucune vidéo n'est ajoutée chaque matin et GitHub est refusé)</span>@endunless</li>
            <li>Voix des nouvelles vidéos : <strong>{{ $voices === [] ? 'aucun fournisseur avec une clé' : implode(', puis ', $voices) }}</strong></li>
            <li>Sujets encore jamais traités : <strong>{{ $remaining }}</strong></li>
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
        @if ($redoable > 0)
            <form method="POST" action="{{ route('admin.videos.redo-today') }}" class="video-prepare">
                @csrf
                <button type="submit" class="btn btn-light"><x-icon name="refresh" /> Refaire les vidéos d'aujourd'hui ({{ $redoable }})</button>
            </form>
        @endif
        <p class="muted small">
            Script et voix prêts en quelques minutes (état « Voix prête »). Ensuite, sur GitHub : Actions, « Vidéos de
            {{ config('avatar.model.name') }} », Run workflow. Comptez environ 12 minutes par vidéo.
        </p>
    </section>

    <section class="card" id="essai-voix" aria-labelledby="essai-voix-titre">
        <h2 id="essai-voix-titre">Essai comparatif des voix</h2>
        <p class="muted small">Le même passage, dit par chaque voix. Touchez « Écouter » (la première écoute prend quelques secondes), puis choisissez la voix des prochaines vidéos.</p>
        <details>
            <summary>Lire le passage</summary>
            <p>{{ $trialText }}</p>
        </details>
        <ul class="voice-trials">
            @foreach ($trialVoices as $trial)
                <li class="voice-trial" data-voice-trial>
                    <div>
                        <strong>{{ $trial['voice'] }}</strong> <span class="muted small">{{ $trial['label'] }}</span>
                        @if ($trial['chosen'])<span class="badge badge-accent">Choisie</span>@endif
                    </div>
                    @if ($trial['available'])
                        <div class="voice-actions">
                            <button type="button" class="btn btn-light" data-voice-sample="{{ route('admin.videos.voice-sample', ['provider' => $trial['provider'], 'voice' => $trial['voice']]) }}"><x-icon name="headphones" /> Écouter</button>
                            @unless ($trial['chosen'])
                                <form method="POST" action="{{ route('admin.videos.voice-choose') }}">
                                    @csrf
                                    <input type="hidden" name="voice" value="{{ $trial['provider'] }}:{{ $trial['voice'] }}">
                                    <button type="submit" class="btn btn-light">Choisir cette voix</button>
                                </form>
                            @endunless
                        </div>
                        <audio controls preload="none" hidden data-voice-audio></audio>
                        <p class="muted small" data-voice-note hidden role="status"></p>
                    @else
                        <p class="muted small">{{ $trial['message'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
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
                @if ($video->lips_correlation !== null || $video->loudness_lufs !== null)
                    <p class="small video-measures">
                        @if ($video->lips_correlation !== null)Lèvres : accord {{ number_format($video->lips_correlation, 2, ',', ' ') }}@endif
                        @if ($video->lips_correlation !== null && $video->loudness_lufs !== null) · @endif
                        @if ($video->loudness_lufs !== null)Son : {{ number_format($video->loudness_lufs, 1, ',', ' ') }} LUFS, crête {{ number_format($video->true_peak_db ?? 0, 1, ',', ' ') }} dB @endif
                    </p>
                @endif
                @if ($video->error)
                    <p class="muted small">Dernière erreur : {{ $video->error }}</p>
                @endif

                @if ($video->video_path)
                    @if ($video->status !== \App\Models\LessonVideo::READY)
                        <p class="muted small">Ancienne version, visible jusqu'à l'arrivée de la nouvelle.</p>
                    @endif
                    <video class="video-preview" controls preload="none" playsinline src="{{ route('admin.videos.file', $video) }}"></video>
                    <div class="voice-actions">
                        <a class="btn btn-accent" href="{{ route('admin.videos.file', ['video' => $video, 'telecharger' => 1]) }}" download="{{ $video->downloadName() }}"><x-icon name="download" /> Télécharger</a>
                    </div>
                @endif

                @if ($video->status === \App\Models\LessonVideo::READY)
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
                @endif

                @if (in_array($video->status, [\App\Models\LessonVideo::READY, \App\Models\LessonVideo::FAILED], true))
                    <div class="voice-actions video-redo">
                        <form method="POST" action="{{ route('admin.videos.redo', $video) }}">
                            @csrf
                            <button type="submit" class="btn btn-light"><x-icon name="refresh" /> Refaire la vidéo</button>
                        </form>
                        @if ($video->status === \App\Models\LessonVideo::FAILED)
                            <form method="POST" action="{{ route('admin.videos.retry', $video) }}">
                                @csrf
                                <button type="submit" class="btn btn-accent"><x-icon name="refresh" /> Réessayer</button>
                            </form>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach

        {{ $videos->links() }}
    </section>

    <script type="module" src="{{ \App\Support\Asset::url('js/copy.js') }}"></script>
    <script type="module" src="{{ \App\Support\Asset::url('js/admin-videos.js') }}"></script>
</x-layout>
