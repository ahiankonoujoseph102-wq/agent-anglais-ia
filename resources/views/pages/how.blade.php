{{--
    « Comment ça marche » (publique) : les vidéos de présentation en version « site » (App\Support\Videos\SiteVideos,
    videos.presentations.site), légères, avec les sous-titres dans l'image. Rien n'est chargé tant que la personne ne
    touche pas « Regarder » : l'image d'aperçu est différée, la vidéo n'existe dans la page qu'après le toucher
    (public/js/watch-video.js ; sans JavaScript, le lien ouvre la vidéo). Sans vidéo prête : seulement les étapes.
--}}
@php
    use App\Support\Asset;
    use App\Support\Duration;
    use App\Support\Navigation;

    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
    $minutes = config('platform.assessment.max_minutes');
    $intros = config('videos.presentations.site.intros', []);
@endphp

<x-layout title="Apprendre l'anglais sur téléphone, en vidéo"
          :description="'Voyez en vidéo comment apprendre l\'anglais sur votre téléphone avec '.$teacher.' : l\'évaluation orale gratuite, une séance et les réponses à vos questions.'">
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="video" /></span>
        <h1>Comment ça marche ?</h1>
        <p>Regardez {{ $teacher }} en action avant de créer votre compte.</p>
    </div>
    <x-top-cta />

    @if ($videos->isNotEmpty())
        @push('head')
            @foreach ($videos as $video)
                {!! \App\Support\Seo::jsonLd(\App\Support\Seo::video($video, str_replace([':teacher', ':platform'], [$teacher, $platform], (string) ($intros[$video->key] ?? '')))) !!}
            @endforeach
        @endpush
        @foreach ($videos as $video)
            <section class="section how-video reveal" aria-labelledby="video-{{ $video->key }}" id="{{ $video->key }}">
                <h2 id="video-{{ $video->key }}">{{ $video->title }}</h2>
                @if (! empty($intros[$video->key]))
                    <p>{{ str_replace([':teacher', ':platform'], [$teacher, $platform], $intros[$video->key]) }}</p>
                @endif
                <a class="watch" href="{{ route('how.video', $video->key) }}" data-watch data-title="{{ $video->title }}">
                    <img src="{{ route('how.poster', $video->key) }}" width="{{ config('videos.presentations.site.width') }}" height="{{ config('videos.presentations.site.height') }}" alt="Aperçu de la vidéo « {{ $video->title }} »" loading="lazy" decoding="async">
                    <span class="watch-button"><x-icon name="play" /> Regarder</span>
                </a>
                <p class="muted small">
                    @if ($video->duration_seconds){{ Duration::formatPrecise($video->duration_seconds) }} · @endif
                    environ {{ max(1, (int) round($video->web_size_bytes / 1048576)) }} mégaoctets
                </p>
                @if ($loop->first)
                    <p class="muted small how-data"><x-icon name="info" /> <span>Rien n'est téléchargé avant que vous touchiez « Regarder ».</span></p>
                @endif
            </section>
        @endforeach
    @endif

    <section class="section reveal" aria-labelledby="comment-etapes">
        <h2 id="comment-etapes">En quatre étapes</h2>
        <ol class="how-steps">
            <li>Créez votre compte avec votre nom et votre numéro de téléphone.</li>
            <li>Passez votre évaluation orale gratuite : {{ $minutes }} minutes à l'oral avec {{ $teacher }} pour trouver votre niveau, de A1 à C2.</li>
            <li>Choisissez une formule et payez par Mobile Money.</li>
            <li>Parlez avec {{ \App\Support\Teachers\Teachers::names() }} chaque jour, à votre rythme.</li>
        </ol>
        <div class="cta-actions">
            <a class="btn btn-accent btn-lg" href="{{ Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
            <a class="btn btn-outline" href="{{ route('plans') }}">Voir les formules</a>
        </div>
    </section>

    @if ($videos->isNotEmpty())
        <script type="module" src="{{ Asset::url('js/watch-video.js') }}"></script>
    @endif
</x-layout>
