{{-- Invitation à refaire l'évaluation gratuite (App\Mail\LearnerInvitationMail) : une colonne, couleurs de la marque. --}}
@php
    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
    $firstName = \App\Support\Greeting::firstName((string) $user->name);
    $whatsapp = \App\Support\SiteSettings::whatsapp();
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $teacher }} a été amélioré</title>
</head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#0E2A5C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;">
    <tr><td align="center" style="padding:16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
            <tr><td style="background:#0E2A5C;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:bold;border-bottom:4px solid #FF7A1A;">{{ $platform }}</td></tr>
            <tr><td style="padding:20px;">
                <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#0E2A5C;">Bonjour {{ $firstName }},</h1>
                <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Vous avez déjà rencontré {{ $teacher }}, votre professeur d'anglais sur {{ $platform }}. Depuis, nous l'avons beaucoup amélioré : il parle plus lentement et plus clairement, il s'adapte à votre niveau et il répond plus vite.</p>
                <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Nous vous invitons à refaire votre évaluation orale gratuite. En quelques minutes, {{ $teacher }} vous écoute, vous donne votre niveau et vous montre une première phrase.</p>
                <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Votre compte est toujours là : connectez-vous avec votre numéro de téléphone et votre mot de passe habituels.</p>
                <a href="{{ route('dashboard') }}" style="display:block;text-align:center;background:#FF7A1A;color:#0E2A5C;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 18px;border-radius:12px;">Refaire mon évaluation gratuite</a>
                <p style="margin:20px 0 0;font-size:16px;line-height:1.5;">À très bientôt,<br>L'équipe {{ $platform }}</p>
                @if (filled($whatsapp))
                    <p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#51607F;">Une question ? Écrivez-nous sur WhatsApp : {{ $whatsapp }}.</p>
                @endif
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
