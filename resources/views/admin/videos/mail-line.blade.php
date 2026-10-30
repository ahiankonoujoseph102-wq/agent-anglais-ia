{{-- État de l'envoi par e-mail d'une vidéo de promotion ou du Club (App\Support\Videos\VideoMailer). --}}
@php
    $when = fn ($date, $format) => $date?->timezone(config('app.timezone'))->locale('fr')->translatedFormat($format);
@endphp
@if ($video->status === \App\Models\LessonVideo::READY)
    @if ($video->mail_status === \App\Models\LessonVideo::MAIL_SENT)
        <p class="small video-mail">
            <x-icon name="check-circle" />
            Envoyée par e-mail le {{ $when($video->mailed_at, 'j F à H \h i') }}{{ $video->purged_at ? ' ; effacée du stockage le '.$when($video->purged_at, 'j F') : '' }}.
            {{ $video->mail_error }}
        </p>
    @elseif ($video->mail_status === \App\Models\LessonVideo::MAIL_FAILED)
        <p class="small video-mail">E-mail en échec : {{ $video->mail_error }}</p>
        @can('admin-route', 'admin.videos.mail')
            <form method="POST" action="{{ route('admin.videos.mail', $video) }}">
                @csrf
                <button type="submit" class="btn btn-light"><x-icon name="refresh" /> Renvoyer l'e-mail</button>
            </form>
        @endcan
    @elseif ($video->mail_status === null)
        <p class="muted small video-mail">E-mail en préparation{{ $video->mail_error ? ' (nouvel essai bientôt : '.$video->mail_error.')' : '' }}.</p>
    @endif
@endif
