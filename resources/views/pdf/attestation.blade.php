{{--
    Attestation de niveau, convertie en PDF par dompdf (App\Support\AttestationPdf). Couleurs de la marque écrites
    ici (le PDF ne lit pas public/css/tokens.css) : bleu nuit #0E2A5C, orange #FF7A1A, orange foncé #B25512.
    Police Sora (resources/pdf, OFL). Toujours « attestation », jamais « certificat » ni « diplôme ».
--}}
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @font-face { font-family: 'Sora'; font-weight: normal; src: url('{{ $fonts['regular'] }}') format('truetype'); }
    @font-face { font-family: 'Sora'; font-weight: bold; src: url('{{ $fonts['bold'] }}') format('truetype'); }
    @page { margin: 0; }
    body { font-family: 'Sora', 'DejaVu Sans', sans-serif; margin: 0; color: #1E2A3B; text-align: center; }
    .top { background: #0E2A5C; color: #ffffff; padding: 34px 0 28px; }
    .top img { height: 46px; }
    .top .tagline { font-size: 10pt; margin-top: 8px; color: #C9D4E8; }
    .band { height: 7px; background: #FF7A1A; }
    .frame { margin: 34px 56px 0; border: 1.5px solid #D6DEEC; border-radius: 10px; padding: 34px 36px 30px; }
    .eyebrow { font-size: 10pt; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #B25512; }
    h1 { font-size: 19pt; line-height: 1.2; color: #0E2A5C; margin: 8px 0 28px; }
    .small { font-size: 11pt; color: #5A6B85; margin: 0; }
    .name { font-size: 26pt; font-weight: bold; color: #0E2A5C; margin: 6px 0 14px; }
    .level { display: inline-block; margin: 8px 0 18px; padding: 10px 26px; border-radius: 10px; background: #FFF1E6; border: 2px solid #FF7A1A; font-size: 24pt; font-weight: bold; color: #0E2A5C; }
    .text { font-size: 10.5pt; line-height: 1.6; color: #1E2A3B; margin: 0 20px 18px; }
    .date { font-size: 11pt; font-weight: bold; color: #0E2A5C; margin: 0; }
    .verify { margin: 30px 56px 0; background: #EEF2F9; border-left: 5px solid #0E2A5C; border-radius: 6px; padding: 14px 18px; text-align: left; }
    .verify .label { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .6px; color: #B25512; }
    .verify .code { font-size: 15pt; font-weight: bold; color: #0E2A5C; margin: 3px 0 4px; }
    .verify .url { font-size: 8.5pt; color: #5A6B85; }
    .contact { position: absolute; left: 56px; right: 56px; bottom: 34px; font-size: 8pt; line-height: 1.5; color: #5A6B85; border-top: 1px solid #D6DEEC; padding-top: 8px; }
</style>
</head>
<body>
<div class="top">
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ $platform }}">
    @else
        <div style="font-size: 22pt; font-weight: bold">{{ $platform }}</div>
    @endif
    <div class="tagline">{{ $tagline }}</div>
</div>
<div class="band"></div>

<div class="frame">
    <div class="eyebrow">{{ $platform }}</div>
    <h1>ATTESTATION DE NIVEAU EN ANGLAIS</h1>
    <p class="small">Nous attestons que</p>
    <div class="name">{{ $learner }}</div>
    <p class="small">a validé le niveau</p>
    <div class="level">{{ $level }} — {{ $levelLabel }}</div>
    <p class="text">{{ $text }}</p>
    <p class="date">Délivrée le {{ $date }}.</p>
</div>

<div class="verify">
    <div class="label">Code de vérification</div>
    <div class="code">{{ $code }}</div>
    <div class="url">Vérifier cette attestation : {{ $verifyUrl }}</div>
</div>

<div class="contact">
    {{ $contactLine }}
    @if ($email)<br>{{ $email }}@endif
</div>
</body>
</html>
