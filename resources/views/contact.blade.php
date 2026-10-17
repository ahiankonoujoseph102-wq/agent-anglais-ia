<x-layout title="Contact" narrow :description="'Coordonnées du centre '.config('platform.name').' : adresse, horaires, téléphones, WhatsApp.'">
    <div class="auth-head rise">
        <span class="icon-bubble"><x-icon name="bubble" /></span>
        <h1>Nous contacter</h1>
        <p>Une question sur votre compte, un paiement ou une séance ? Écrivez-nous ou appelez-nous.</p>
    </div>

    <section class="card rise" style="--i: 1">
        <ul class="contact-list">
            <li><span class="icon-bubble"><x-icon name="pin" /></span><div><strong>Bureau</strong>{{ $contact['address'] }}</div></li>
            <li><span class="icon-bubble"><x-icon name="clock" /></span><div><strong>Horaires</strong>{{ $contact['hours'] }}</div></li>
            <li><span class="icon-bubble"><x-icon name="phone" /></span>
                <div>
                    <strong>Téléphones</strong>
                    @foreach ($contact['phones'] as $phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>@if (! $loop->last)<br>@endif
                    @endforeach
                </div>
            </li>
            <li><span class="icon-bubble"><x-icon name="mail" /></span><div><strong>E-mail</strong><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></div></li>
        </ul>

        <a class="btn btn-whatsapp btn-block btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Écrire sur WhatsApp</a>
    </section>
</x-layout>
