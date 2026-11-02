{{-- Relance d'un inscrit sans formule payée (App\Mail\FollowUpMail) : une colonne, couleurs de la marque, un bouton. --}}
@php
    $whatsapp = \App\Support\SiteSettings::whatsapp();
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#0E2A5C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;">
    <tr><td align="center" style="padding:16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
            <tr><td style="background:#0E2A5C;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:bold;border-bottom:4px solid #FF7A1A;">{{ config('platform.name') }}</td></tr>
            <tr><td style="padding:20px;">
                <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#0E2A5C;">Bonjour{{ $firstName ? ' '.$firstName : '' }},</h1>
                <p style="margin:0 0 12px;font-size:17px;line-height:1.5;font-weight:bold;">{{ $title }}</p>
                @foreach ($lines as $line)
                    <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">{{ $line }}</p>
                @endforeach
                @if ($stars !== [])
                    <p style="margin:4px 0 6px;font-size:15px;line-height:1.5;">Touchez une étoile pour commencer :</p>
                    <p style="margin:0 0 12px;font-size:34px;line-height:1.2;letter-spacing:6px;">
                        @foreach ($stars as $index => $starUrl)
                            <a href="{{ $starUrl }}" title="{{ $index + 1 }} sur 5" style="color:#FF7A1A;text-decoration:none;">&#9733;</a>
                        @endforeach
                    </p>
                @endif
                <a href="{{ $url }}" style="display:block;text-align:center;background:#FF7A1A;color:#0E2A5C;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 18px;border-radius:12px;margin-top:8px;">{{ $button }}</a>
                @if ($links !== [])
                    <p style="margin:18px 0 0;font-size:15px;line-height:1.7;">
                        @foreach ($links as $link)
                            <a href="{{ $link['url'] }}" style="color:#0E2A5C;font-weight:bold;">{{ $link['label'] }}</a>@if (! $loop->last)<br>@endif
                        @endforeach
                    </p>
                @endif
                @if ($review)
                    <p style="margin:20px 0 0;font-size:15px;line-height:1.5;">{{ \Illuminate\Support\Str::before($review, route('reviews.edit')) }}<a href="{{ $reviewUrl }}" style="color:#0E2A5C;font-weight:bold;">Donner mon avis</a>{{ \Illuminate\Support\Str::after($review, route('reviews.edit')) }}</p>
                @endif
                <p style="margin:20px 0 0;font-size:15px;line-height:1.5;">{{ $teacherName }} et l'équipe {{ config('platform.name') }}</p>
                <p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#51607F;">
                    Vous recevez ce message parce que vous avez créé un compte sur {{ config('platform.name') }}.@if (filled($whatsapp)) Une question : WhatsApp {{ $whatsapp }}.@endif
                    <a href="{{ $stopUrl }}" style="color:#51607F;">Ne plus recevoir ces e-mails</a>
                </p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
