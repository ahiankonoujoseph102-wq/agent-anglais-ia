{{--
    Gabarit minimal, sans session ni information de compte : page hors connexion
    (gardée en cache par le service worker) et erreurs graves (500, 503).
--}}
@props(['title'])

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ config('platform.brand.theme_color') }}">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ config('platform.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/app.css') }}">
</head>
<body data-icon-style="{{ \App\Support\IconStyle::current() }}">
    <header class="site-header">
        <div class="container"><x-logo /></div>
    </header>
    <main id="contenu">
        <div class="container">{{ $slot }}</div>
    </main>
    <footer class="site-footer-simple">
        <div class="container"><a href="{{ route('privacy') }}">Confidentialité</a></div>
    </footer>
</body>
</html>
