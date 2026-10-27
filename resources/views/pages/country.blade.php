{{--
    Page « Apprendre l'anglais » d'un pays (textes : resources/content/pays.php, App\Support\CountryPage).
    Moyens de paiement et prix : configuration. Questions affichées = données structurées FAQPage.
--}}
@php
    use App\Support\Navigation;
    use App\Support\Seo;

    $teacher = config('avatar.model.name');
    $minutes = config('platform.assessment.max_minutes');
@endphp

<x-layout :title="$page->title" :description="$page->description" :share-image="$page->shareImage()" :share-alt="$page->heading">
    <section class="section page-hero" aria-labelledby="pays-titre">
        <div class="students reveal">
            <figure class="photo-frame"><x-photo :name="$page->photo" /></figure>
            <div>
                <span class="eyebrow"><x-icon name="pin" /> {{ $page->name }}</span>
                <h1 id="pays-titre">{{ $page->heading }}</h1>
                <p class="lead">{{ $page->lead }}</p>
                <div class="actions actions-row">
                    <a class="btn btn-accent btn-lg btn-block" href="{{ Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
                    <a class="btn btn-outline btn-block" href="{{ route('plans') }}">Voir les formules</a>
                </div>
                <p class="muted small next-step">{{ config('platform.name') }} accueille les francophones de tous les pays. Cette page présente les moyens de paiement de ce pays.</p>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="pays-pourquoi">
        <div class="section-head reveal">
            <span class="eyebrow">{{ config('platform.name') }} {{ $page->in }} {{ $page->name }}</span>
            <h2 id="pays-pourquoi">Votre cours d'anglais en ligne {{ $page->in }} {{ $page->name }}</h2>
        </div>
        <div class="features">
            @foreach ($page->sections as $section)
                <article class="card feature reveal" style="--i: {{ $loop->index }}">
                    <span @class(['icon-bubble', 'icon-bubble-primary' => $loop->first])><x-icon :name="$section['icon']" /></span>
                    <div>
                        <h3>{{ $section['title'] }}</h3>
                        <p>{{ $section['text'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="section" aria-labelledby="pays-formules">
        <div class="section-head reveal">
            <span class="eyebrow">Prix en francs CFA</span>
            <h2 id="pays-formules">Prix des formules {{ $page->in }} {{ $page->name }}</h2>
            <p class="muted">L'évaluation orale de {{ $minutes }} minutes est gratuite. Ensuite, choisissez votre formule : les prix sont les mêmes dans tous les pays.</p>
            @if ($page->country)
                <p class="country-pay"><strong>Moyens de paiement {{ $page->in }} {{ $page->name }} :</strong>
                    @foreach ($page->country->operators as $operator)<span class="tag">{{ $operator->name }}</span> @endforeach
                </p>
            @endif
        </div>
        <div class="plans">
            @foreach ($plans as $plan)
                <x-plan-card :plan="$plan" :href="auth()->check() ? route('payment.create', $plan->key) : route('register')" class="reveal" style="--i: {{ $loop->index }}" />
            @endforeach
        </div>
    </section>

    <section class="section" aria-labelledby="pays-questions">
        <div class="section-head reveal">
            <span class="eyebrow">Vos questions</span>
            <h2 id="pays-questions">Questions fréquentes {{ $page->in }} {{ $page->name }}</h2>
        </div>
        @php(ob_start())
        <div class="faq reveal">
            @foreach ($page->faq as $item)
                <details>
                    <summary><x-icon name="help" /><span>{{ $item['question'] }}</span></summary>
                    <p>{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
        @php($faqHtml = ob_get_clean())
        {!! $faqHtml !!}
        @push('head')
            {!! Seo::jsonLd(Seo::faq($faqHtml)) !!}
        @endpush
    </section>

    <section class="section" aria-labelledby="pays-autres">
        <div class="section-head reveal">
            <h2 id="pays-autres">{{ config('platform.name') }} dans les autres pays</h2>
        </div>
        <x-country-links :pages="$countryPages" :current="$page->slug" />
        <p class="muted small next-step"><a href="{{ route('how') }}">Voir {{ $teacher }} en vidéo : comment ça marche</a></p>
        <p class="muted small"><a href="{{ route('about') }}">Qui sommes-nous ? L'histoire de {{ config('platform.name') }} et de son fondateur</a></p>
    </section>
</x-layout>
