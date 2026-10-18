@props(['title' => null, 'description' => null, 'narrow' => false, 'install' => false, 'focus' => false, 'indexable' => null])

@php
    use App\Support\Asset;
    use App\Support\Seo;

    $user = auth()->user();
    $isAdminPage = request()->is('admin', 'admin/*');
    // Barre du bas (téléphone) : apprenant connecté, hors écran de conversation (focus).
    $tabbar = $user && ! $isAdminPage && ! $focus && ! $user->must_change_password;
    $tab = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'is-active' : null;
    // Référencement : seules les pages publiques (App\Support\Seo::INDEXABLE_ROUTES) sont indexées.
    $indexable ??= Seo::indexable();
    $pageTitle = $title ? $title.' · '.config('platform.name') : config('platform.name').' · '.config('platform.brand.tagline');
    $pageDescription = $description ?: config('platform.brand.tagline');
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ config('platform.brand.theme_color') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    @if ($indexable)
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ Seo::canonical() }}">
        {{-- Partage (WhatsApp, Facebook, X) : titre, description et image aux couleurs de la marque. --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('platform.name') }}">
        <meta property="og:locale" content="fr_FR">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ Seo::canonical() }}">
        <meta property="og:image" content="{{ Seo::url(Seo::SHARE_IMAGE) }}">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ config('platform.name') }} : {{ config('platform.brand.tagline') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $pageTitle }}">
        <meta name="twitter:description" content="{{ $pageDescription }}">
        <meta name="twitter:image" content="{{ Seo::url(Seo::SHARE_IMAGE) }}">
    @else
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="manifest" href="{{ route('manifest') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('icons/favicon-96.png') }}" type="image/png" sizes="96x96">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ config('platform.brand.short_name') ?: config('platform.name') }}">
    <link rel="preload" href="{{ asset('fonts/sora-2.000-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ Asset::url('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ Asset::url('css/app.css') }}">
    <script>document.documentElement.classList.add('js');</script>
    <script type="module" src="{{ Asset::url('js/app.js') }}" data-sw="{{ asset('sw.js') }}"></script>
    @stack('head')
</head>
<body @class(['has-tabbar' => $tabbar, 'admin' => $isAdminPage, 'is-admin-user' => $user?->isAdmin()]) data-icon-style="{{ \App\Support\IconStyle::current() }}">
    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <header class="site-header">
        <div class="container">
            <x-logo />
            @if ($user)
                @php
                    // Liens du compte connecté : la même liste dans le menu (téléphone, tablette)
                    // et dans la navigation complète (ordinateur, 1200 pixels et plus).
                    $links = array_values(array_filter([
                        $user->isAdmin() ? ['route' => 'admin.payments.pending', 'active' => 'admin.*', 'icon' => 'shield', 'label' => 'Administration', 'badge' => $pendingPaymentsCount] : null,
                        ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home', 'label' => 'Mon espace'],
                        ['route' => 'program.show', 'active' => 'program.*', 'icon' => 'calendar', 'label' => 'Mon programme'],
                        ['route' => 'lessons.history', 'active' => 'lessons.history', 'icon' => 'list', 'label' => 'Mes séances'],
                        ['route' => 'courses.index', 'active' => 'courses.*', 'icon' => 'book', 'label' => 'Mes cours'],
                        ['route' => 'account.edit', 'active' => 'account.*', 'icon' => 'user', 'label' => 'Mon compte'],
                        ['route' => 'contact', 'active' => 'contact', 'icon' => 'bubble', 'label' => 'Aide'],
                    ]));
                @endphp
                <nav class="nav nav-desktop" aria-label="Navigation principale">
                    @foreach ($links as $link)
                        <a href="{{ route($link['route']) }}" @class([$tab($link['active'])]) @if (request()->routeIs($link['active'])) aria-current="page" @endif>
                            <x-icon :name="$link['icon']" /> {{ $link['label'] }}
                            @if (($link['badge'] ?? 0) > 0)
                                <span class="badge badge-alert" title="Paiements en attente">{{ $link['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-link"><x-icon name="logout" /> Déconnexion</button>
                    </form>
                </nav>

                {{-- Téléphone et tablette : un seul bouton « Menu » (<details> : fonctionne sans JavaScript, au clavier). --}}
                <details class="menu" data-menu>
                    <summary class="menu-button" aria-label="Ouvrir le menu">
                        <x-icon name="menu" /> <span>Menu</span>
                        @if ($user->isAdmin() && $pendingPaymentsCount > 0)
                            <span class="badge badge-alert" title="Paiements en attente">{{ $pendingPaymentsCount }}</span>
                        @endif
                    </summary>
                    <nav class="menu-panel" aria-label="Navigation principale">
                        @foreach ($links as $link)
                            <a href="{{ route($link['route']) }}" @class([$tab($link['active'])]) @if (request()->routeIs($link['active'])) aria-current="page" @endif>
                                <x-icon :name="$link['icon']" /> <span>{{ $link['label'] }}</span>
                                @if (($link['badge'] ?? 0) > 0)
                                    <span class="badge badge-alert">{{ $link['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"><x-icon name="logout" /> <span>Déconnexion</span></button>
                        </form>
                    </nav>
                </details>
            @else
                <nav class="nav" aria-label="Navigation principale">
                    <a href="{{ route('login') }}" @class([$tab('login')]) @if (request()->routeIs('login')) aria-current="page" @endif>Connexion</a>
                    <a class="nav-cta" href="{{ route('register') }}">Inscription</a>
                </nav>
            @endif
        </div>
    </header>

    <main id="contenu">
        <div class="container {{ $narrow ? 'container-narrow' : '' }}">
            @if (session('status'))
                {{-- Message de confirmation en haut de la page, qui disparaît tout seul (animation CSS,
                     même sans JavaScript) : plus long à lire, plus longtemps affiché. --}}
                <div class="toast" role="status" data-toast
                     style="--toast-delay: {{ min(10, 4 + intdiv(mb_strlen((string) session('status')), 25)) }}s">
                    <x-icon name="check-circle" />
                    <div>{{ session('status') }}</div>
                    <button type="button" class="toast-close" data-toast-close aria-label="Fermer le message" hidden><x-icon name="close" /></button>
                </div>
            @endif

            @if (isset($errors) && $errors->any())
                {{-- Formulaire renvoyé avec des erreurs : le premier point à corriger, en haut de l'écran ;
                     le focus va sur le premier champ en erreur (autofocus de <x-input>, public/js/app.js). --}}
                <div class="toast toast-error" role="alert" data-toast style="--toast-delay: 10s">
                    <x-icon name="alert" />
                    <div>
                        @if ($errors->count() > 1)
                            {{ $errors->count() }} points à corriger :
                        @else
                            À corriger :
                        @endif
                        {{ $errors->first() }}
                    </div>
                    <button type="button" class="toast-close" data-toast-close aria-label="Fermer le message" hidden><x-icon name="close" /></button>
                </div>
            @endif

            {{ $slot }}

            @if ($install)
                {{-- Installation de l'application : affiché par public/js/app.js quand c'est possible. --}}
                <div class="install" id="install" hidden>
                    <span class="icon-bubble icon-bubble-primary"><x-icon name="download" /></span>
                    <p data-install-android>
                        <strong>Installer l'application</strong>
                        Ouvrez {{ config('platform.name') }} directement depuis l'écran d'accueil de votre téléphone.
                    </p>
                    <p data-install-ios hidden>
                        <strong>Ajouter à l'écran d'accueil</strong>
                        Sur iPhone : touchez « Partager » <x-icon name="share" label="Partager" />, puis « Sur l'écran d'accueil ».
                    </p>
                    <button type="button" class="btn" data-install-button>Installer</button>
                    <button type="button" class="install-close" data-install-close title="Fermer"><x-icon name="close" label="Fermer" /></button>
                </div>
            @endif
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <x-logo variant="footer" />
                    <p class="footer-tagline">{{ config('platform.brand.tagline') }}</p>
                    <p class="next-step">Apprenez l'anglais à l'oral avec votre professeur IA, depuis votre téléphone.</p>
                </div>
                @php($contact = \App\Support\SiteSettings::contact())
                <ul class="footer-list">
                    @if ($contact['address'])
                        <li><x-icon name="pin" /> <span>{{ $contact['address'] }}</span></li>
                    @endif
                    <li><x-icon name="clock" /> <span>{{ $contact['hours'] }}</span></li>
                    @if ($contact['phones'])
                        <li><x-icon name="phone" />
                            <span>
                                @foreach ($contact['phones'] as $phone)
                                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>@if (! $loop->last) · @endif
                                @endforeach
                            </span>
                        </li>
                    @endif
                    @if ($contact['email'])
                        <li><x-icon name="mail" /> <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
                    @endif
                </ul>
                <ul class="footer-links">
                    @if ($contact['whatsapp'])
                        <li><a href="{{ \App\Support\WhatsApp::url($contact['whatsapp']) }}" target="_blank" rel="noopener">WhatsApp</a></li>
                    @endif
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                    <li><button type="button" class="btn btn-link footer-install" data-install-button hidden>Installer l'application</button></li>
                </ul>
            </div>
            <div class="footer-bottom">
                <p>&copy; {{ date('Y') }} {{ config('platform.name') }} — Apprenez l'anglais avec votre professeur IA.</p>
                @if (config('platform.brand.developer'))
                    <p class="footer-signature">Développé par <strong>{{ config('platform.brand.developer') }}</strong></p>
                @endif
            </div>
        </div>
    </footer>

    @if ($tabbar)
        <nav class="tabbar" aria-label="Navigation de l'espace apprenant">
            <a href="{{ route('dashboard') }}" @class([$tab('dashboard')]) @if (request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="home" /> Mon espace</a>
            <a href="{{ route('program.show') }}" @class([$tab('program.*')]) @if (request()->routeIs('program.*')) aria-current="page" @endif><x-icon name="calendar" /> Programme</a>
            <a href="{{ route('lessons.history') }}" @class([$tab('lessons.history')]) @if (request()->routeIs('lessons.history')) aria-current="page" @endif><x-icon name="list" /> Séances</a>
            <a href="{{ route('contact') }}" @class([$tab('contact')]) @if (request()->routeIs('contact')) aria-current="page" @endif><x-icon name="whatsapp" /> Aide</a>
        </nav>
    @endif
</body>
</html>
