<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $alert->title }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F6FB;font-family:Arial,Helvetica,sans-serif;color:#0E2A5C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FB;">
    <tr><td align="center" style="padding:16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden;">
            <tr><td style="background:#0E2A5C;padding:16px 20px;color:#ffffff;font-size:18px;font-weight:bold;">{{ config('platform.name') }}</td></tr>
            <tr><td style="padding:20px;">
                <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#0E2A5C;">{{ $alert->title }}</h1>
                <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">{{ $alert->body }}</p>
                @if ($alert->details !== [])
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;font-size:15px;">
                        @foreach ($alert->details as [$label, $value])
                            <tr>
                                <td style="padding:6px 0;border-top:1px solid #E3E8F3;color:#51607F;width:42%;vertical-align:top;">{{ $label }}</td>
                                <td style="padding:6px 0;border-top:1px solid #E3E8F3;font-weight:bold;vertical-align:top;">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
                <a href="{{ $alert->url }}" style="display:block;text-align:center;background:#FF7A1A;color:#0E2A5C;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 18px;border-radius:12px;">{{ $alert->buttonLabel }}</a>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
