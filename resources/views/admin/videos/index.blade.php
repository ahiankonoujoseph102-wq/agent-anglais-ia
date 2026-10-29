<x-layout title="Vidéos">
    @include('admin.nav')

    <h1>Vidéos de {{ config('avatar.model.name') }}</h1>
    <p>
        Chaque jour, {{ $perDay }} {{ $perDay > 1 ? 'vidéos verticales' : 'vidéo verticale' }} de leçon d'anglais, prêtes pour TikTok et la chaîne WhatsApp :
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
            <x-input name="site" label="Adresse du site dans les vidéos" :value="old('site', $site)" placeholder="talkami.net" hint="Sans « https:// ». Dite et affichée à la fin de chaque vidéo. Champ vide : la fabrication est bloquée." />
            <button type="submit" class="btn btn-accent">Enregistrer l'adresse</button>
        </form>
    </section>

    <section class="card" id="videos-par-jour" aria-labelledby="videos-par-jour-titre">
        <h2 id="videos-par-jour-titre">Vidéos de promotion par jour</h2>
        <p class="muted small">
            Préparées chaque matin, puis fabriquées par GitHub. Dépôt GitHub public : 2 par jour. Dépôt privé (minutes
            comptées) : choisissez 1.
        </p>
        <form method="POST" action="{{ route('admin.videos.per-day') }}" class="video-prepare">
            @csrf
            @method('PUT')
            <label for="videos-per-day">Vidéos de promotion par jour</label>
            <select id="videos-per-day" name="per_day">
                @foreach ($perDayChoices as $choice)
                    <option value="{{ $choice }}" @selected($choice === $perDay)>{{ $choice }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-light">Enregistrer</button>
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
                    <option value="{{ $count }}" @selected($count === $perDay)>{{ $count }} vidéo{{ $count > 1 ? 's' : '' }}</option>
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
            {{ config('avatar.model.name') }} », Run workflow. Comptez environ 8 minutes de fabrication par vidéo quand le dépôt est public, environ 15 quand il est privé.
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

    {{-- Catalogue des écrans pour un montage (tools/presentation/catalogue.mjs) : une vidéo muette et la voix à part. --}}
    <section class="card" id="catalogue" aria-labelledby="catalogue-titre">
        <h2 id="catalogue-titre">Catalogue des écrans (pour un montage)</h2>
        <p class="muted small">
            Une seule vidéo muette de tous les écrans du site de démonstration (1080 × 1920, 30 images par seconde), séparés par une
            seconde de noir, et la voix de {{ config('avatar.model.name') }} du passage où il parle, dans un fichier à part.
            Fabriqué sur GitHub : Actions, « Catalogue des écrans », Run workflow.
        </p>
        @if ($catalogue)
            <p>
                Fait le {{ \Illuminate\Support\Carbon::parse($catalogue['made_at'])->timezone(config('app.timezone'))->translatedFormat('j F Y à H \h i') }}
                · {{ \App\Support\Duration::formatPrecise((int) round($catalogue['duration'])) }}
                · {{ number_format($catalogue['size_bytes'] / 1048576, 0, ',', ' ') }} mégaoctets
            </p>
            <div class="voice-actions">
                <a class="btn btn-accent" href="{{ route('admin.videos.catalogue', 'video') }}"><x-icon name="download" /> Télécharger la vidéo du catalogue</a>
                <a class="btn btn-light" href="{{ route('admin.videos.catalogue', 'voix') }}"><x-icon name="download" /> Télécharger la voix de {{ config('avatar.model.name') }}</a>
            </div>
            @if ($catalogue['voice_start'] !== null)
                <p class="muted small">La voix se place à {{ \App\Support\Videos\ScreenCatalogue::clock($catalogue['voice_start']) }} dans la vidéo (début du passage où {{ config('avatar.model.name') }} parle).</p>
            @endif
            <details>
                <summary>Index des passages ({{ count($catalogue['passages']) }})</summary>
                <ol class="catalogue-index">
                    @foreach ($catalogue['passages'] as $passage)
                        <li><strong>{{ \App\Support\Videos\ScreenCatalogue::clock($passage['start']) }} à {{ \App\Support\Videos\ScreenCatalogue::clock($passage['end']) }}</strong> · {{ $passage['title'] }}</li>
                    @endforeach
                </ol>
            </details>
        @else
            <p class="muted small">Pas encore fabriqué.</p>
        @endif
    </section>

    <section id="presentations" aria-labelledby="presentations-titre">
        <h2 id="presentations-titre">Vidéos de présentation</h2>
        <div class="card card-soft">
            <p>
                Une grande vidéo et six courtes : {{ config('avatar.model.name') }} présente le site, l'écran défile à côté de lui.
                L'écran est enregistré sur un site de DÉMONSTRATION fabriqué par GitHub (compte fictif, aucune donnée réelle,
                aucun vrai paiement), jamais sur le site en ligne.
            </p>
            <ol class="small">
                <li>Touchez « Préparer les vidéos pas encore prêtes » : la voix est prête en quelques minutes (« Voix prête »). Une vidéo déjà prête n'est jamais refaite par ce bouton : utilisez « Refaire cette vidéo » sous elle.</li>
                <li>Sur GitHub : Actions, « Vidéos de présentation », Run workflow (environ une heure pour toutes ; « nouvelles seulement », « ce soir seulement » ou « publicité seulement » pour ne pas refaire les autres).</li>
                <li>Revenez ici : « Télécharger pour TikTok » (pleine qualité) ou « Télécharger pour WhatsApp » (plus légère).</li>
            </ol>
            <form method="POST" action="{{ route('admin.videos.presentations.request') }}" class="video-prepare">
                @csrf
                <button type="submit" class="btn btn-accent"><x-icon name="play" /> Préparer les vidéos pas encore prêtes</button>
            </form>
        </div>

        @foreach ($presentations as $presentation)
            <article class="card video-card" id="presentation-{{ $presentation->key }}">
                <div class="card-title">
                    <h3>{{ $presentation->title }}</h3>
                </div>
                <p class="muted small">
                    @if ($presentation->duration_seconds){{ \App\Support\Duration::formatPrecise($presentation->duration_seconds) }}@endif
                    @if ($presentation->size_bytes) · TikTok {{ number_format($presentation->size_bytes / 1048576, 1, ',', ' ') }} mégaoctets @endif
                    @if ($presentation->light_size_bytes) · WhatsApp {{ number_format($presentation->light_size_bytes / 1048576, 1, ',', ' ') }} mégaoctets @endif
                </p>
                @if (in_array($presentation->key, \App\Support\Videos\SiteVideos::keys(), true))
                    {{-- Page publique « Comment ça marche » : version légère tirée de la version WhatsApp (workflow « Vidéos du site »). --}}
                    <p class="muted small">
                        @if ($presentation->web_path)
                            Sur la page « <a href="{{ route('how') }}#{{ $presentation->key }}">Comment ça marche</a> » : version du site {{ number_format($presentation->web_size_bytes / 1048576, 1, ',', ' ') }} mégaoctets.
                        @else
                            Page « Comment ça marche » : version du site pas encore faite (GitHub, Actions, « Vidéos du site », Run workflow, une fois cette vidéo prête).
                        @endif
                    </p>
                @endif
                <p>
                    <span @class(['badge', 'badge-accent' => $presentation->status === \App\Models\LessonVideo::READY, 'badge-alert' => $presentation->status === \App\Models\LessonVideo::FAILED])>{{ $presentation->statusLabel() }}</span>
                </p>
                @if ($presentation->lips_correlation !== null || $presentation->loudness_lufs !== null)
                    <p class="small video-measures">
                        @if ($presentation->lips_correlation !== null)Lèvres : accord {{ number_format($presentation->lips_correlation, 2, ',', ' ') }}@endif
                        @if ($presentation->lips_correlation !== null && $presentation->loudness_lufs !== null) · @endif
                        @if ($presentation->loudness_lufs !== null)Son : {{ number_format($presentation->loudness_lufs, 1, ',', ' ') }} LUFS, crête {{ number_format($presentation->true_peak_db ?? 0, 1, ',', ' ') }} dB @endif
                    </p>
                @endif
                @if ($presentation->error)
                    <p class="muted small">Dernière erreur : {{ $presentation->error }}</p>
                @endif

                @if ($presentation->video_path)
                    @if ($presentation->status !== \App\Models\LessonVideo::READY)
                        <p class="muted small">Ancienne version, visible jusqu'à l'arrivée de la nouvelle.</p>
                    @endif
                    <video class="video-preview" controls preload="none" playsinline src="{{ route('admin.videos.presentations.file', ['presentation' => $presentation, 'version' => 'whatsapp']) }}"></video>
                    <div class="voice-actions">
                        <a class="btn btn-accent" href="{{ route('admin.videos.presentations.file', ['presentation' => $presentation, 'telecharger' => 1]) }}" download="{{ $presentation->downloadName() }}"><x-icon name="download" /> {{ $presentation->isAd() ? 'Télécharger pour Meta et TikTok' : 'Télécharger pour TikTok' }}</a>
                        @if ($presentation->light_path)
                            <a class="btn btn-light" href="{{ route('admin.videos.presentations.file', ['presentation' => $presentation, 'version' => 'whatsapp', 'telecharger' => 1]) }}" download="{{ $presentation->downloadName(true) }}"><x-icon name="download" /> Télécharger pour WhatsApp</a>
                        @endif
                    </div>
                @endif

                @if ($presentation->status === \App\Models\LessonVideo::READY && $presentation->caption)
                    <h4>Texte de publication</h4>
                    <p class="alert alert-info small"><x-icon name="info" /> {{ $reminder }}</p>
                    <textarea class="video-caption" rows="6" readonly aria-label="Texte de publication">{{ $presentation->caption }}</textarea>
                    <button type="button" class="btn btn-light" data-copy="{{ $presentation->caption }}" data-copied="Texte copié"><x-icon name="copy" /> Copier le texte</button>
                @endif

                @if ($presentation->status !== \App\Models\LessonVideo::RENDERING)
                    {{-- Une seule vidéo, sans attendre les autres (sa voix d'abord, puis « Run workflow » sur GitHub). --}}
                    <form method="POST" action="{{ route('admin.videos.presentations.request') }}" class="voice-actions video-redo">
                        @csrf
                        <input type="hidden" name="key" value="{{ $presentation->key }}">
                        @if (in_array($presentation->status, [\App\Models\LessonVideo::READY, \App\Models\LessonVideo::FAILED], true))
                            <button type="submit" class="btn btn-light"><x-icon name="refresh" /> Refaire cette vidéo</button>
                        @else
                            <button type="submit" class="btn btn-light"><x-icon name="play" /> Préparer cette vidéo seulement</button>
                        @endif
                    </form>
                @endif
            </article>
        @endforeach
    </section>

    <script type="module" src="{{ \App\Support\Asset::url('js/copy.js') }}"></script>
    <script type="module" src="{{ \App\Support\Asset::url('js/admin-videos.js') }}"></script>
</x-layout>
