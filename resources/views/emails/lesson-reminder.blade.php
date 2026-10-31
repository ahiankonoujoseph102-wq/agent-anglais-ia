{{-- Rappel de séance ou de fin d'abonnement (App\Mail\LessonReminderMail) : une colonne, couleurs de la marque, un bouton. --}}
@php
    $whatsapp = \App\Support\SiteSettings::whatsapp();
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre séance d'anglais</title>
</head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#0E2A5C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;">
    <tr><td align="center" style="padding:16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
            <tr><td style="background:#0E2A5C;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:bold;border-bottom:4px solid #FF7A1A;">{{ config('platform.name') }}</td></tr>
            <tr><td style="padding:20px;">
                <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#0E2A5C;">Bonjour{{ $firstName ? ' '.$firstName : '' }},</h1>
                @if ($renew)
                    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Votre abonnement {{ $planName }} se termine demain{{ $endsAt ? ' ('.$endsAt->translatedFormat('j F').')' : '' }}. Renouvelez-le pour continuer vos progrès sans interruption : votre niveau, votre programme et vos séances vous attendent.</p>
                    @if ($methods)
                        <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Le paiement se fait en quelques minutes depuis votre téléphone, avec {{ $methods }}.</p>
                    @else
                        <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Touchez le bouton ci-dessous : nous vous expliquons comment renouveler.</p>
                    @endif
                @elseif ($missed)
                    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Nous ne nous sommes pas vus aujourd'hui pour votre séance de {{ $time }}. Ce n'est pas grave : il vous reste encore du temps aujourd'hui.</p>
                    <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Quelques minutes chaque jour, c'est ce qui fait vraiment progresser. Je vous attends !</p>
                @else
                    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Votre séance d'anglais commence à {{ $time }}, dans {{ $minutes }} minutes.</p>
                    <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Installez-vous dans un coin calme, avec votre téléphone chargé. À tout à l'heure !</p>
                @endif
                <a href="{{ $url }}" style="display:block;text-align:center;background:#FF7A1A;color:#0E2A5C;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 18px;border-radius:12px;">{{ $renew ? 'Renouveler mon abonnement' : 'Commencer ma séance' }}</a>
                <p style="margin:20px 0 0;font-size:15px;line-height:1.5;">{{ $teacherName }}</p>
                @if ($renew)
                <p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#51607F;">Vous recevez ce message parce que votre abonnement à {{ config('platform.name') }} se termine demain. Rien n'est prélevé automatiquement : vous choisissez de renouveler, ou non.@if (filled($whatsapp)) Une question : WhatsApp {{ $whatsapp }}.@endif</p>
                @else
                <p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#51607F;">Vous recevez ce rappel parce que vous êtes abonné à {{ config('platform.name') }} et que votre programme prévoit une séance aujourd'hui à {{ $time }}. Vous pouvez changer cette heure dans « Mon espace », carte « Heure de mes séances ».@if (filled($whatsapp)) Une question : WhatsApp {{ $whatsapp }}.@endif</p>
                @endif
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
