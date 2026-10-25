{{-- Liens TikTok et chaîne WhatsApp de la plateforme (config/platform.php → contact), dans un nouvel onglet.
     Texte seul : aucune icône de marque, rien de plus à charger. --}}
@php
    $links = array_filter([
        'Suivre '.config('platform.name').' sur TikTok' => config('platform.contact.tiktok'),
        'Rejoindre la chaîne WhatsApp' => config('platform.contact.whatsapp_channel'),
    ]);
@endphp
@if ($links)
    <ul {{ $attributes->merge(['class' => 'social-links']) }} aria-label="{{ config('platform.name') }} sur les réseaux">
        @foreach ($links as $label => $url)
            <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $label }}<span class="sr-only"> (s'ouvre dans un nouvel onglet)</span></a></li>
        @endforeach
    </ul>
@endif
