{{-- Liens vers les pages « Apprendre l'anglais » de chaque pays (App\Support\CountryPage). --}}
@props(['pages', 'current' => null])

<ul class="country-links">
    @foreach ($pages as $countryPage)
        @continue($countryPage->slug === $current)
        <li>
            <a class="card country-link" href="{{ route('country', $countryPage->slug) }}">
                <span class="icon-bubble"><x-icon name="pin" /></span>
                <span><strong>{{ $countryPage->heading }}</strong><span class="muted small">{{ $countryPage->country ? implode(', ', array_map(fn ($operator) => $operator->name, array_values($countryPage->country->operators))) : 'Mobile Money' }}</span></span>
                <x-icon name="chevron-right" />
            </a>
        </li>
    @endforeach
</ul>
