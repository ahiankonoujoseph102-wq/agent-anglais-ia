{{-- E-mail au fondateur d'une vidéo de promotion ou du Club (App\Mail\VideoReadyMail) : description, texte à publier, pièce jointe. --}}
@php
    $teacher = $video->teacher()->name();
    $date = $video->day->locale('fr')->translatedFormat('l j F Y');
    $topic = $video->isClub() ? null : \App\Support\Videos\VideoTopics::find((string) $video->topic_key);
    $question = $video->script['question']['spoken'] ?? null;
    $duration = $video->duration_seconds ? \App\Support\Duration::formatPrecise($video->duration_seconds) : null;
    $size = fn (?int $bytes) => $bytes ? number_format($bytes / 1048576, 1, ',', ' ').' mégaoctets' : null;
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $video->isClub() ? 'Vidéo du Club' : 'Vidéo de promotion' }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#0E2A5C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;">
    <tr><td align="center" style="padding:16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;">
            <tr><td style="background:#0E2A5C;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:bold;border-bottom:4px solid #FF7A1A;">{{ config('platform.name') }} · {{ $video->isClub() ? 'Vidéo du Club des abonnés' : 'Vidéo de promotion' }}</td></tr>
            <tr><td style="padding:20px;">
                <h1 style="margin:0 0 8px;font-size:21px;line-height:1.3;">{{ $video->title }}</h1>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.5;color:#51607F;">{{ ucfirst($date) }} · {{ $teacher }}@if ($video->level) · niveau {{ $video->level }}@endif @if ($duration) · {{ $duration }}@endif</p>

                <h2 style="margin:0 0 6px;font-size:17px;">Description</h2>
                <p style="margin:0 0 6px;font-size:15px;line-height:1.5;">
                    @if ($video->isClub())
                        Vidéo du jour pour le groupe WhatsApp du Club des abonnés. Thème : {{ $video->themeLabel() ?? $video->title }}.
                    @else
                        Vidéo de promotion pour TikTok et la chaîne WhatsApp.@if ($topic && filled($topic['angle'])) Ce qu'elle enseigne : {{ $topic['angle'] }}@endif
                    @endif
                </p>
                @if ($question)
                    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">Question du jour (à la fin de la vidéo) : « {{ $question }} »</p>
                @endif

                <h2 style="margin:16px 0 6px;font-size:17px;">Texte à publier avec la vidéo</h2>
                <div style="margin:0 0 16px;padding:12px 14px;background:#F4F6FB;border-left:4px solid #FF7A1A;border-radius:8px;font-size:15px;line-height:1.55;white-space:pre-line;">{{ $video->caption }}</div>
                @unless ($video->isClub())
                    <p style="margin:0 0 16px;font-size:13px;line-height:1.5;color:#51607F;">{{ config('videos.ai_label_reminder') }}</p>
                @endunless

                <h2 style="margin:16px 0 6px;font-size:17px;">La vidéo</h2>
                @if ($withAttachment)
                    <p style="margin:0 0 10px;font-size:15px;line-height:1.5;">En pièce jointe : version légère ({{ $size($video->mail_size_bytes) ?? 'moins de 4 mégaoctets' }}), prête pour WhatsApp et TikTok.</p>
                @else
                    <p style="margin:0 0 10px;font-size:15px;line-height:1.5;">Pas de pièce jointe pour cette vidéo : téléchargez-la avec le bouton ci-dessous.</p>
                @endif
                @if ($link)
                    <a href="{{ $link }}" style="display:block;text-align:center;background:#FF7A1A;color:#0E2A5C;text-decoration:none;font-weight:bold;font-size:16px;padding:14px 18px;border-radius:12px;">Télécharger en pleine qualité ({{ $size($video->size_bytes) ?? 'MP4' }})</a>
                    @if ($keepUntil)
                        <p style="margin:8px 0 0;font-size:13px;line-height:1.5;color:#51607F;">Lien valable jusqu'au {{ $keepUntil->timezone(config('app.timezone'))->locale('fr')->translatedFormat('j F Y à H \h i') }} ; ensuite la vidéo est effacée du stockage du site. Gardez cet e-mail : la pièce jointe reste.</p>
                    @endif
                @else
                    <p style="margin:0;font-size:13px;line-height:1.5;color:#51607F;">La vidéo est maintenant effacée du stockage du site : gardez cet e-mail.</p>
                @endif
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
