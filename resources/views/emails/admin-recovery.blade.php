{{-- Lien de récupération de l'administrateur (App\Mail\AdminRecoveryMail) : une colonne, couleurs de la marque. --}}
@php
    $platform = config('platform.name');
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Retrouver l'accès administrateur</title>
</head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#0E2A5C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;">
    <tr><td align="center" style="padding:16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
            <tr><td style="background:#0E2A5C;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:bold;border-bottom:4px solid #FF7A1A;">{{ $platform }} · Administration</td></tr>
            <tr><td style="padding:20px;">
                <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#0E2A5C;">Retrouver l'accès administrateur</h1>
                <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Compte administrateur : <strong>{{ $admin->name }}</strong></p>
                <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">Numéro de téléphone du compte (pour vous connecter ensuite) : <strong>{{ \App\Support\PhoneNumber::display($admin->phone) }}</strong>@if (filled($admin->email))<br>Adresse e-mail du compte : <strong>{{ $admin->email }}</strong>@endif</p>
                <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">Touchez le bouton, puis « Me connecter ». Le site vous demandera aussitôt de choisir un nouveau mot de passe.</p>
                <a href="{{ $link }}" style="display:block;text-align:center;background:#FF7A1A;color:#0E2A5C;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 18px;border-radius:12px;">Retrouver mon accès</a>
                <p style="margin:20px 0 0;font-size:14px;line-height:1.5;color:#51607F;">Ce lien ne sert qu'une fois et ne marche que pendant {{ $minutes }} minutes. Ne le transférez à personne. Si vous n'avez rien demandé, ignorez cet e-mail : votre mot de passe actuel ne change pas.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
