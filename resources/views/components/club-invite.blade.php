{{-- Club des abonnés : groupe WhatsApp réservé aux abonnés payants actifs (App\Support\SubscribersClub). --}}
@props(['user' => auth()->user()])
@php
    $clubLink = \App\Support\SubscribersClub::linkFor($user);
@endphp
@if ($clubLink)
    <section {{ $attributes->merge(['class' => 'card club-invite']) }} id="club-des-abonnes" aria-labelledby="club-title">
        <div class="card-title"><span class="icon-bubble icon-bubble-accent"><x-icon name="users" /></span><h2 id="club-title">Club des abonnés {{ config('platform.name') }}</h2></div>
        <p>Merci d'avoir choisi {{ config('platform.name') }} pour apprendre l'anglais 🙏🇺🇸</p>
        <p>J'ai créé un groupe WhatsApp réservé uniquement à nos abonnés : le Club des abonnés {{ config('platform.name') }}.</p>
        <p>Dans ce groupe :</p>
        <ul class="club-list">
            <li>✅ on pratique l'anglais ensemble ;</li>
            <li>✅ on partage nos progrès avec {{ config('avatar.model.name') }} ;</li>
            <li>✅ vous recevez en premier les nouveautés et les astuces pour progresser plus vite.</li>
        </ul>
        <a class="btn btn-whatsapp btn-block" href="{{ $clubLink }}" target="_blank" rel="noopener noreferrer"><x-icon name="whatsapp" /> Rejoindre le Club des abonnés</a>
        <p class="muted small club-signature">Coach {{ config('platform.founder.name') }}, fondateur de {{ config('platform.name') }}</p>
    </section>
@endif
