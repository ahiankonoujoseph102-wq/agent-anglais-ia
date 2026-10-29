{{-- Bloc « Club des abonnés » de la page « Vidéos » : la vidéo du jour du groupe WhatsApp des abonnés payants, à
     publier À LA MAIN (le site ne publie rien dans un groupe). Visible aussi par un assistant. --}}
<section class="card" id="club" aria-labelledby="club-titre">
    <h2 id="club-titre">Club des abonnés</h2>
    <p class="muted small">
        @php
            // Professeur de la vidéo d'aujourd'hui si elle existe déjà, sinon celui du jour (un jour chacun).
            $todayTeacher = ($clubVideos->first(fn ($video) => $video->day->isToday())?->teacher() ?? \App\Support\Videos\VideoTeachers::forDay(today()))->name();
        @endphp
        Une vidéo par jour pour le groupe WhatsApp des abonnés payants, dite par {{ \App\Support\Teachers\Teachers::names() }}{{ \App\Support\Teachers\Teachers::hasChoice() ? ' (un jour chacun)' : '' }}.
        Thème d'aujourd'hui : <strong>{{ $clubTheme['label'] }}</strong>. Professeur d'aujourd'hui : <strong>{{ $todayTeacher }}</strong>. Abonnés payants actifs : {{ $clubMembers }}.
        Le site ne publie rien : téléchargez la vidéo, publiez-la dans le groupe avec le texte ci-dessous, puis cochez « Publiée ».
    </p>

    @if (! $onlyClub && $clubToday === null)
        <form method="POST" action="{{ route('admin.videos.club.prepare') }}" class="video-prepare">
            @csrf
            <button type="submit" class="btn btn-light"><x-icon name="play" /> Préparer la vidéo du Club d'aujourd'hui</button>
        </form>
    @endif

    @if ($clubVideos->isEmpty())
        <p class="muted small">Aucune vidéo du Club pour le moment : celle d'aujourd'hui est ajoutée toute seule dans la minute, dès qu'il y a au moins un abonné payant actif.</p>
    @endif

    @foreach ($clubVideos as $video)
        <article class="video-card club-video" id="club-{{ $video->id }}">
            <h3>{{ $video->themeLabel() }} · {{ $video->day->translatedFormat('l j F Y') }} · {{ $video->teacher()->name() }}</h3>
            <p class="muted small">
                {{ $video->title }}
                @if ($video->duration_seconds) · {{ \App\Support\Duration::formatPrecise($video->duration_seconds) }} @endif
                @if ($video->size_bytes) · {{ number_format($video->size_bytes / 1048576, 1, ',', ' ') }} mégaoctets @endif
            </p>
            <p>
                <span @class(['badge', 'badge-accent' => $video->status === \App\Models\LessonVideo::READY, 'badge-alert' => $video->status === \App\Models\LessonVideo::FAILED])>{{ $video->statusLabel() }}</span>
                @if ($video->published_at)<span class="badge">Publiée</span>@endif
            </p>
            @include('admin.videos.old-address', ['video' => $video])
            @if ($video->error && ! $onlyClub)
                <p class="muted small">Dernière erreur : {{ $video->error }}</p>
            @endif

            @if ($video->video_path)
                <video class="video-preview" controls preload="none" playsinline src="{{ route('admin.videos.file', $video) }}"></video>
                <div class="voice-actions">
                    <a class="btn btn-accent" href="{{ route('admin.videos.file', ['video' => $video, 'telecharger' => 1]) }}" download="{{ $video->downloadName() }}"><x-icon name="download" /> Télécharger pour WhatsApp</a>
                </div>
            @elseif ($video->status !== \App\Models\LessonVideo::FAILED)
                <p class="muted small">La vidéo arrive : script et voix préparés par le site, puis fabriquée par GitHub en premier.</p>
            @endif

            @if ($video->caption)
                <h4>Texte à publier avec la vidéo</h4>
                <textarea class="video-caption" rows="5" readonly aria-label="Texte à publier avec la vidéo">{{ $video->caption }}</textarea>
                <button type="button" class="btn btn-light" data-copy="{{ $video->caption }}" data-copied="Texte copié"><x-icon name="copy" /> Copier le texte</button>
            @endif

            @if ($video->status === \App\Models\LessonVideo::READY)
                <form method="POST" action="{{ route('admin.videos.publish', $video) }}" class="video-published">
                    @csrf
                    @method('PUT')
                    <label><input type="checkbox" name="published" value="1" @checked($video->published_at) data-autosubmit> Publiée</label>
                    <button type="submit" class="btn btn-light" data-autosubmit-fallback>Enregistrer</button>
                </form>
            @endif

            @if (! $onlyClub && in_array($video->status, [\App\Models\LessonVideo::READY, \App\Models\LessonVideo::FAILED], true))
                <div class="voice-actions video-redo">
                    <form method="POST" action="{{ route('admin.videos.redo', $video) }}">
                        @csrf
                        <button type="submit" class="btn btn-light"><x-icon name="refresh" /> Refaire la vidéo (nouveau script)</button>
                    </form>
                    @if ($video->status === \App\Models\LessonVideo::FAILED)
                        <form method="POST" action="{{ route('admin.videos.retry', $video) }}">
                            @csrf
                            <button type="submit" class="btn btn-light">Réessayer</button>
                        </form>
                    @endif
                </div>
            @endif
        </article>
    @endforeach
</section>
