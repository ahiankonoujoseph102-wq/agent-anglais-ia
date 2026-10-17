{{-- Logo provisoire : icône + nom + accroche, réglés dans config/platform.php (brand). --}}
@props(['tagline' => false])

<a href="{{ route('home') }}" {{ $attributes->class(['logo']) }}>
    <span class="logo-mark"><x-icon :name="config('platform.brand.icon', 'bubble')" /></span>
    <span class="logo-text">
        {{ config('platform.name') }}
        @if ($tagline)
            <small>{{ config('platform.brand.tagline') }}</small>
        @endif
    </span>
</a>
