{{--
    Un guide gratuit (App\Support\Guides) : texte dans guides/articles/{slug}.blade.php, puis l'appel à l'évaluation
    gratuite et les guides liés. Données structurées : Article et fil d'Ariane (BreadcrumbList).
--}}
@php
    use App\Support\Seo;

    $article = view($guide->view())->render();
    $minutes = max(1, (int) round(str_word_count(strip_tags($article), 0, 'àâäçéèêëîïôöùûüÿœæÀÂÇÉÈÊÎÔÙÛŒ\'’-') / 200));
@endphp

<x-layout :title="$guide->title" :description="$guide->description">
    <article class="guide" aria-labelledby="guide-titre">
        <nav class="breadcrumb small" aria-label="Fil d'Ariane">
            <a href="{{ route('home') }}">Accueil</a> › <a href="{{ route('guides.index') }}">Guides</a> › <span aria-current="page">{{ $guide->heading }}</span>
        </nav>
        <header class="guide-head rise">
            <h1 id="guide-titre">{{ $guide->heading }}</h1>
            <p class="lead">{{ $guide->lead }}</p>
            <p class="muted small">Publié le {{ $guide->published->translatedFormat('j F Y') }} par {{ config('platform.name') }} · Lecture : {{ $minutes }} {{ $minutes > 1 ? 'minutes' : 'minute' }}</p>
        </header>

        <div class="guide-body">
            {!! $article !!}
        </div>
    </article>

    <x-guide-cta />

    @if ($guide->relatedGuides())
        <section class="section-tight" aria-labelledby="guides-lies">
            <h2 id="guides-lies">À lire aussi</h2>
            <ul class="link-list">
                @foreach ($guide->relatedGuides() as $related)
                    <li><a href="{{ route('guides.show', $related->slug) }}">{{ $related->heading }}</a></li>
                @endforeach
                <li><a href="{{ route('guides.index') }}">Tous les guides gratuits</a></li>
            </ul>
        </section>
    @endif

    @push('head')
        {!! Seo::jsonLd(Seo::article($guide)) !!}
        {!! Seo::jsonLd(Seo::breadcrumbs(['Guides' => route('guides.index', [], false), $guide->heading => route('guides.show', $guide->slug, false)])) !!}
    @endpush
</x-layout>
