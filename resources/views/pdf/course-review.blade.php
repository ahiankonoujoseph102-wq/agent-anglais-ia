{{--
    Document personnel de révision (« Mes cours »), converti en PDF par dompdf (App\Support\CourseReviewPdf).
    Couleurs de la marque écrites ici (le PDF ne lit pas public/css/tokens.css) : bleu nuit #0E2A5C, orange #FF7A1A,
    orange foncé #B25512 sur fond clair. Police Sora (resources/pdf, OFL). Aucune marque d'intelligence artificielle.
--}}
@php
    // Signé par le professeur de la séance (CourseReviewPdf::html).
    $teacher ??= config('avatar.model.name');
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @font-face { font-family: 'Sora'; font-weight: normal; src: url('{{ $fonts['regular'] }}') format('truetype'); }
    @font-face { font-family: 'Sora'; font-weight: bold; src: url('{{ $fonts['bold'] }}') format('truetype'); }
    @page { margin: 118px 46px 78px 46px; }
    body { font-family: 'Sora', 'DejaVu Sans', sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1E2A3B; }
    header { position: fixed; top: -100px; left: -46px; right: -46px; height: 84px; background: #0E2A5C; color: #ffffff; padding: 14px 46px 0; }
    header img { height: 34px; }
    header .who { position: absolute; right: 46px; top: 14px; text-align: right; font-size: 9pt; line-height: 1.45; }
    header .who strong { font-size: 10.5pt; }
    header .band { position: absolute; left: 0; right: 0; bottom: 0; height: 5px; background: #FF7A1A; }
    footer { position: fixed; bottom: -60px; left: 0; right: 0; height: 44px; border-top: 1px solid #D6DEEC; padding-top: 7px; font-size: 8pt; color: #5A6B85; }
    footer .page { position: absolute; right: 0; top: 7px; }
    footer .page:after { content: counter(page); }
    h1 { font-size: 19pt; line-height: 1.2; color: #0E2A5C; margin: 0 0 4px; }
    .subtitle { color: #5A6B85; margin: 0 0 14px; }
    .intro { background: #EEF2F9; border-left: 4px solid #0E2A5C; padding: 9px 12px; margin: 0 0 16px; }
    h2 { font-size: 13pt; color: #0E2A5C; margin: 20px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #FF7A1A; }
    h3 { font-size: 11pt; color: #0E2A5C; margin: 12px 0 4px; }
    .num { color: #B25512; }
    p { margin: 0 0 6px; }
    .example { margin: 2px 0 2px 12px; }
    .en { font-weight: bold; color: #0E2A5C; }
    .fr { color: #5A6B85; }
    .box { border: 1px solid #D6DEEC; border-radius: 6px; padding: 8px 12px; margin: 0 0 8px; page-break-inside: avoid; }
    .label { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #B25512; }
    table { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
    th { background: #0E2A5C; color: #ffffff; text-align: left; font-size: 9pt; padding: 6px 8px; }
    td { border-bottom: 1px solid #D6DEEC; padding: 6px 8px; vertical-align: top; font-size: 9.5pt; }
    td.wrong { color: #8A2B2B; }
    td.right { color: #0E2A5C; font-weight: bold; }
    .remember { background: #FFF1E6; border-left: 4px solid #FF7A1A; padding: 8px 12px; margin: 0 0 6px; page-break-inside: avoid; }
    ol.items { margin: 4px 0 8px 18px; padding: 0; }
    ol.items li { margin: 0 0 8px; }
    .line { color: #A0AEC4; }
    .answers { page-break-before: always; }
    .muted { color: #5A6B85; font-size: 9pt; }
</style>
</head>
<body>
<header>
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ config('platform.name') }}">
    @else
        <strong style="font-size: 16pt">{{ config('platform.name') }}</strong>
    @endif
    <div class="who">
        <strong>{{ $learner }}</strong><br>
        {{ $date }}<br>
        {{ $lessonTitle }}@if ($level) · niveau {{ $level }}@endif
    </div>
    <div class="band"></div>
</header>
<footer>
    {{ $site }}@if ($whatsapp) · WhatsApp {{ $whatsapp }}@endif<br>
    Document personnel de révision, préparé par {{ $teacher }}.
    <span class="page">Page </span>
</footer>

<main>
    <h1>{{ $content['title'] }}</h1>
    <p class="subtitle">Document personnel de révision · {{ $lessonTitle }}</p>
    <p class="intro">Relisez ce document après votre séance avec {{ $teacher }}, dites les exemples à voix haute, puis refaites l'exercice de la fin seul, avant de regarder les solutions. C'est un document de révision, pour vous.</p>

    <h2><span class="num">1.</span> La leçon, expliquée simplement</h2>
    @foreach ($content['lesson'] as $point)
        <div class="box">
            <h3>{{ $point['heading'] }}</h3>
            <p>{{ $point['explanation'] }}</p>
            @foreach ($point['examples'] as $example)
                <p class="example"><span class="en" lang="en">{{ $example['en'] }}</span>@if ($example['fr']) <span class="fr">— {{ $example['fr'] }}</span>@endif</p>
            @endforeach
        </div>
    @endforeach

    @if ($content['exercises'] !== [])
        <h2><span class="num">2.</span> Les exercices travaillés pendant la séance</h2>
        @foreach ($content['exercises'] as $exercise)
            <div class="box">
                <p><span class="label">Consigne</span><br>{{ $exercise['instruction'] }}</p>
                @if ($exercise['method'])
                    <p><span class="label">La démarche</span><br>{{ $exercise['method'] }}</p>
                @endif
                <p><span class="label">La bonne réponse</span><br><span class="en" lang="en">{{ $exercise['answer'] }}</span></p>
                @if ($exercise['explanation'])
                    <p class="fr">{{ $exercise['explanation'] }}</p>
                @endif
            </div>
        @endforeach
    @endif

    @if ($content['errors'] !== [])
        <h2><span class="num">{{ $content['exercises'] !== [] ? '3.' : '2.' }}</span> Vos erreurs pendant la séance</h2>
        <table>
            <tr><th style="width: 32%">Ce que vous avez dit</th><th style="width: 32%">La bonne forme</th><th>Pourquoi</th></tr>
            @foreach ($content['errors'] as $error)
                <tr>
                    <td class="wrong" lang="en">{{ $error['said'] }}</td>
                    <td class="right" lang="en">{{ $error['correct'] }}</td>
                    <td>{{ $error['explanation'] }}</td>
                </tr>
            @endforeach
        </table>
        <p class="muted">Se tromper fait partie de l'apprentissage : {{ $teacher }} revient sur ces points aux séances suivantes.</p>
    @endif

    <h2>À retenir</h2>
    @foreach ($content['phrases'] as $phrase)
        <p class="remember"><span class="en" lang="en">{{ $phrase['en'] }}</span>@if ($phrase['fr'])<br><span class="fr">{{ $phrase['fr'] }}</span>@endif</p>
    @endforeach

    <h2>À refaire seul</h2>
    <p>{{ $content['practice']['instruction'] }}</p>
    <ol class="items">
        @foreach ($content['practice']['items'] as $item)
            <li><span lang="en">{{ $item }}</span><br><span class="line">………………………………………………………………</span></li>
        @endforeach
    </ol>

    @if ($content['practice']['answers'] !== [])
        <div class="answers">
            <h2>Solutions de l'exercice « À refaire seul »</h2>
            <p class="muted">À regarder seulement après avoir essayé.</p>
            <ol class="items">
                @foreach ($content['practice']['answers'] as $answer)
                    <li lang="en">{{ $answer }}</li>
                @endforeach
            </ol>
        </div>
    @endif
</main>
</body>
</html>
