<x-layout title="Contact" :description="'Coordonnées du centre '.config('platform.name').' : adresse, horaires, téléphones, WhatsApp.'">
    <h1>Nous contacter</h1>

    <section class="card">
        <dl class="summary">
            <dt>Bureau</dt><dd>{{ $contact['address'] }}</dd>
            <dt>Horaires</dt><dd>{{ $contact['hours'] }}</dd>
            <dt>Téléphones</dt>
            <dd>
                @foreach ($contact['phones'] as $phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>@if (! $loop->last)<br>@endif
                @endforeach
            </dd>
            <dt>E-mail</dt><dd><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd>
        </dl>

        <a class="btn btn-whatsapp btn-block" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Écrire sur WhatsApp</a>
    </section>
</x-layout>
