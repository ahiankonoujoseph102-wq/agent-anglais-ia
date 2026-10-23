<x-layout title="Contact et WhatsApp, cours d'anglais à Lomé" narrow :description="'Contactez '.config('platform.name').' : bureau à Lomé, horaires, téléphones et WhatsApp pour vos questions sur les cours d\'anglais en ligne et le paiement.'">
    <div class="auth-head rise">
        <span class="icon-bubble"><x-icon name="bubble" /></span>
        <h1>Nous contacter</h1>
        <p>Une question sur votre compte, un paiement ou une séance ? Écrivez-nous ou appelez-nous.</p>
    </div>

    {{-- L'action d'abord : écrire sur WhatsApp, puis les coordonnées. --}}
    @if ($contact['whatsapp'])
        <a class="btn btn-whatsapp btn-block btn-lg contact-whatsapp rise" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Écrire sur WhatsApp</a>
    @endif

    {{-- Un visage derrière le numéro : le fondateur (photo et nom de la configuration, rien d'inventé). --}}
    @if ($founderPhoto = \App\Support\Founder::photoUrl())
        <div class="contact-founder rise">
            <img src="{{ $founderPhoto }}" width="56" height="56" alt="">
            <p><strong>{{ \Illuminate\Support\Str::before(config('platform.founder.name'), ' ') }} vous répond</strong>
                <span>{{ config('platform.founder.name') }}, fondateur de {{ config('platform.name') }}</span></p>
        </div>
    @endif

    <section class="card rise" style="--i: 1">
        <ul class="contact-list">
            @if ($contact['address'])
                <li><span class="icon-bubble"><x-icon name="pin" /></span><div><strong>Bureau</strong>{{ $contact['address'] }}</div></li>
            @endif
            <li><span class="icon-bubble"><x-icon name="clock" /></span><div><strong>Horaires</strong>{{ $contact['hours'] }}</div></li>
            @if ($contact['phones'])
                <li><span class="icon-bubble"><x-icon name="phone" /></span>
                    <div>
                        <strong>Téléphones</strong>
                        @foreach ($contact['phones'] as $phone)
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>@if (! $loop->last)<br>@endif
                        @endforeach
                    </div>
                </li>
            @endif
            @if ($contact['email'])
                <li><span class="icon-bubble"><x-icon name="mail" /></span><div><strong>E-mail</strong><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></div></li>
            @endif
        </ul>

    </section>
</x-layout>
