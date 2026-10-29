{{-- E-mail du document de révision (App\Mail\CourseReviewMail) : une colonne, couleurs de la marque, PDF joint. --}}
@php
    // Signé par le professeur de la séance (Teacher Joe ou Teacher Rose).
    $teacher = ($review->session?->teacher() ?? \App\Support\Teachers\Teachers::default())->name();
    $firstName = \Illuminate\Support\Str::of((string) $review->user?->name)->before(' ')->limit(30, '');
    $lessonTitle = $review->lesson?->title ?? 'votre leçon';
    $date = ($review->session?->ended_at ?? $review->prepared_at ?? now())->timezone(config('app.timezone'))->locale('fr')->translatedFormat('j F Y');
    $url = $review->course_lesson_id ? route('courses.show', $review->course_lesson_id).'#documents' : route('lessons.history').'#documents';
    $whatsapp = \App\Support\SiteSettings::whatsapp();
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre document de révision</title>
</head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#0E2A5C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;">
    <tr><td align="center" style="padding:16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
            <tr><td style="background:#0E2A5C;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:bold;border-bottom:4px solid #FF7A1A;">{{ config('platform.name') }}</td></tr>
            <tr><td style="padding:20px;">
                <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#0E2A5C;">Bonjour {{ $firstName }},</h1>
                <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Votre document de révision de la séance du {{ $date }}, sur « {{ $lessonTitle }} », est prêt : vous le trouverez en pièce jointe.</p>
                <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Relisez-le, dites les exemples à voix haute, puis refaites l'exercice de la fin seul, avant de regarder les solutions.</p>
                <a href="{{ $url }}" style="display:block;text-align:center;background:#FF7A1A;color:#0E2A5C;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 18px;border-radius:12px;">Ouvrir Mes cours</a>
                <p style="margin:20px 0 0;font-size:13px;line-height:1.5;color:#51607F;">Document personnel de révision, préparé par {{ $teacher }}. Vous recevez cet e-mail parce que votre adresse est enregistrée dans « Mon compte » ; vous pouvez l'y retirer à tout moment.@if (filled($whatsapp)) Une question : WhatsApp {{ $whatsapp }}.@endif</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
