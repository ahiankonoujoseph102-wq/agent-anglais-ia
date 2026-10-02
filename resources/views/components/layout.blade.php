@props(['title' => null, 'description' => null, 'narrow' => false])

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d6b4f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('platform.name') }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a class="brand" href="{{ route('home') }}">{{ config('platform.name') }}</a>
            <nav class="nav" aria-label="Navigation principale">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}">Administration</a>
                    @endif
                    <a href="{{ route('dashboard') }}">Mon espace</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-link">Déconnexion</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Connexion</a>
                    <a class="btn btn-light" href="{{ route('register') }}">Inscription</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        <div class="container {{ $narrow ? 'container-narrow' : '' }}">
            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            {{ $slot }}
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            &copy; {{ date('Y') }} {{ config('platform.name') }} — Apprenez l'anglais avec votre professeur IA.
        </div>
    </footer>
</body>
</html>
