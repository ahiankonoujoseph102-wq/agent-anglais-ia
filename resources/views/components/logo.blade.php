{{-- Logo officiel (image de public/brand/, réglée dans config/platform.php → brand.logo). --}}
@props(['variant' => 'header'])

@php
    $file = config("platform.brand.logo.{$variant}");
    [$width, $height] = @getimagesize(public_path($file)) ?: [640, 174];
@endphp

<a href="{{ route('home') }}" {{ $attributes->class(['logo']) }}>
    <img src="{{ \App\Support\Asset::url($file) }}" width="{{ $width }}" height="{{ $height }}" alt="{{ config('platform.name') }}">
</a>
