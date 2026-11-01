{{-- Fin d'une formule payée (App\Mail\SubscriptionEndedMail) : bilan de l'élève (faits seulement), suite, avis. Une colonne, couleurs de la marque. --}}
@php
    $whatsapp = \App\Support\SiteSettings::whatsapp();
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre bilan</title>
</head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#0E2A5C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;">
    <tr><td align="center" style="padding:16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
            <tr><td style="background:#0E2A5C;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:bold;border-bottom:4px solid #FF7A1A;">{{ config('platform.name') }}</td></tr>
            <tr><td style="padding:20px;">
                <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#0E2A5C;">Bonjour{{ $firstName ? ' '.$firstName : '' }},</h1>
                <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Votre formule {{ $planName }} s'est terminée le {{ $endedOn }}. Voici votre bilan.</p>
                <ul style="margin:0 0 12px;padding-left:20px;font-size:16px;line-height:1.5;">
                    @foreach ($lines as $line)
                        <li style="margin:0 0 6px;">{{ $line }}</li>
                    @endforeach
                </ul>
                <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">{{ $encouragement }}@if ($methods) Le paiement se fait en quelques minutes depuis votre téléphone, avec {{ $methods }}.@endif</p>
                <a href="{{ $url }}" style="display:block;text-align:center;background:#FF7A1A;color:#0E2A5C;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 18px;border-radius:12px;">Continuer avec une nouvelle formule</a>
                @if ($review)
                    <p style="margin:20px 0 0;font-size:15px;line-height:1.5;">{{ \Illuminate\Support\Str::before($review, route('reviews.edit')) }}<a href="{{ $reviewUrl }}" style="color:#0E2A5C;font-weight:bold;">Donner mon avis</a>{{ \Illuminate\Support\Str::after($review, route('reviews.edit')) }}</p>
                @endif
                <p style="margin:20px 0 0;font-size:15px;line-height:1.5;">{{ $teacherName }}</p>
                <p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#51607F;">Vous recevez ce message parce que votre formule à {{ config('platform.name') }} vient de se terminer. Rien n'est prélevé automatiquement : vous choisissez de continuer, ou non.@if (filled($whatsapp)) Une question : WhatsApp {{ $whatsapp }}.@endif</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
