{{-- Formules et prix (configuration), publique : données structurées Service + Offer (App\Support\Seo::offers). --}}
@php
    use App\Support\Navigation;
    use App\Support\Seo;

    $teacher = config('avatar.model.name');
    $minutes = config('platform.assessment.max_minutes');
@endphp

<x-layout title="Formules et prix des cours d'anglais en ligne"
          :description="'Prix des cours d\'anglais en ligne avec '.$teacher.' : '.implode(', ', array_map(fn ($plan) => $plan->name.' '.$plan->formattedPrice(), $plans)).'. Évaluation orale gratuite.'">
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="wallet" /></span>
        <h1>Formules et prix</h1>
        <p>Des formules à la semaine ou au mois, au même prix au Togo, au Bénin, au Burkina Faso et en Côte d'Ivoire. Pour les francophones de tous les pays, en anglais américain, compris partout dans le monde.</p>
    </div>

    <section class="section" aria-label="Les formules">
        <div class="plans">
            @foreach ($plans as $plan)
                <x-plan-card :plan="$plan" :href="auth()->check() ? route('payment.create', $plan->key) : route('register')" class="reveal" style="--i: {{ $loop->index }}" />
            @endforeach
        </div>
        <p class="muted small next-step">Chaque formule donne un temps par jour, à partager entre la séance orale et « Mes cours ». Il revient chaque jour à minuit, heure de Lomé.</p>
    </section>

    <section class="section" aria-labelledby="formules-gratuit">
        <div class="cta-band reveal">
            <h2 id="formules-gratuit">D'abord, une évaluation gratuite</h2>
            <p>{{ $minutes }} minutes à l'oral avec {{ $teacher }} pour connaître votre niveau, de A1 à C2, puis une mini-leçon et vos questions ({{ \App\Models\Assessment::totalMinutes() }} minutes au plus en tout). Une seule par compte, sans paiement.</p>
            <div class="cta-actions">
                <a class="btn btn-accent btn-lg" href="{{ Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="formules-paiement">
        <div class="section-head reveal">
            <span class="eyebrow">Mobile Money</span>
            <h2 id="formules-paiement">Payer depuis votre pays</h2>
            <p class="muted">Vous envoyez le montant exact au numéro affiché, puis la capture d'écran de la confirmation.</p>
        </div>
        <x-country-links :pages="$countryPages" />
        <p class="muted small next-step">Dans les autres pays, le paiement en ligne n'est pas encore disponible : après votre inscription et votre évaluation gratuite, notre équipe vous explique sur WhatsApp comment vous abonner. Les prix restent en francs CFA.</p>
    </section>

    @push('head')
        {!! Seo::jsonLd(Seo::offers()) !!}
    @endpush
</x-layout>
