{{--
    Icônes SVG intégrées, dessinées pour le site (trait de 2 px, grille de 24 × 24).
    Utilisation : <x-icon name="mic" /> ; avec label="…" l'icône est annoncée aux lecteurs d'écran.
--}}
@props(['name', 'label' => null])

@php
    $paths = [
        'bubble' => '<path d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-7l-5 4v-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M8 9.5h8M8 12.5h5"/>',
        'mic' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21M9 21h6"/>',
        'level' => '<path d="M5 20v-5M12 20V10M19 20V4"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'award' => '<circle cx="12" cy="9" r="6"/><path d="m8.6 14 -1.6 7 5-2.6 5 2.6-1.6-7"/>',
        'mobile' => '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M11 18h2"/>',
        'money' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9.5"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/>',
        'home' => '<path d="m3 11 9-8 9 8"/><path d="M5.5 9.5V21h13V9.5"/><path d="M10 21v-6h4v6"/>',
        'book' => '<path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v15H6.5A1.5 1.5 0 0 0 5 19.5z"/><path d="M5 19.5A1.5 1.5 0 0 0 6.5 21H19v-3"/>',
        'list' => '<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'whatsapp' => '<path d="M20.5 12a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.2-4.2A8.5 8.5 0 1 1 20.5 12z"/><path d="M9 8.5c0 3.6 2.9 6.5 6.5 6.5l1-1.6-2-1-1 1a4 4 0 0 1-2.4-2.4l1-1-1-2z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/>',
        'phone' => '<path d="M5 3.5h3.5l1.5 4.5-2 1.2a11 11 0 0 0 6.8 6.8l1.2-2 4.5 1.5V19a2 2 0 0 1-2 2A16.5 16.5 0 0 1 3 5.5a2 2 0 0 1 2-2z"/>',
        'pin' => '<path d="M12 21s-7-6-7-11.5a7 7 0 0 1 14 0C19 15 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M5 20h14"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5M5 20h14"/>',
        'wifi-off' => '<path d="M3 3l18 18M8.5 16.5a5 5 0 0 1 7 0M5 12.6a10 10 0 0 1 4.6-2.4M19 12.6a10 10 0 0 0-2.5-1.7M2 8.8a15 15 0 0 1 4-2.3M22 8.8A15 15 0 0 0 11 5M12 20h.01"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.4-5.7"/><path d="M20 4v5h-5"/>',
        'share' => '<path d="M12 3v12M8 7l4-4 4 4"/><path d="M6 11H5v10h14V11h-1"/>',
        'plus-square' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M12 8v8M8 12h8"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'alert' => '<path d="M12 3.5 21.5 20h-19z"/><path d="M12 10v4.5M12 17.5h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
        'play' => '<path d="M7.5 4.5v15L19.5 12z"/>',
        'logout' => '<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3M15 16l4-4-4-4M19 12H9"/>',
        'login' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l4-4-4-4M14 12H3"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'sparkle' => '<path d="M12 3.5 13.8 9l5.7 1.8-5.7 1.8L12 18l-1.8-5.4L4.5 10.8 10.2 9z"/>',
        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5.5A1.5 1.5 0 0 1 6.5 4H15"/>',
        'shield' => '<path d="M12 3 4.5 6v6c0 4.6 3.2 8 7.5 9 4.3-1 7.5-4.4 7.5-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'headphones' => '<path d="M4 16v-4a8 8 0 0 1 16 0v4"/><rect x="3" y="14" width="4.5" height="7" rx="1.5"/><rect x="16.5" y="14" width="4.5" height="7" rx="1.5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'fire' => '<path d="M12 21c-3.9 0-6.5-2.6-6.5-6 0-3.6 3-5.4 3.5-9 2 1 3.5 3 3.5 5 1-.5 1.8-1.6 2-3 2 1.6 4 4.2 4 7 0 3.4-2.6 6-6.5 6z"/>',
        'edit' => '<path d="M4 20h4L19.5 8.5a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m14 6 4 4"/>',
        'chart' => '<path d="M4 4v16h16"/><path d="m8 15 3.5-4 3 2.5L19 8"/>',
    ];
@endphp

<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif focusable="false">{!! $paths[$name] ?? $paths['bubble'] !!}</svg>
