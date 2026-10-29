{{-- Fin d'un guide ou d'une page d'arrivée : essayer à l'oral, avec l'évaluation gratuite (un seul bouton orange). --}}
@props(['title' => null, 'text' => null])
@php
    $teacher = config('avatar.model.name');
    $minutes = config('platform.assessment.max_minutes');
@endphp
<section {{ $attributes->class(['card guide-cta reveal']) }} aria-labelledby="essayer">
    <span class="icon-bubble icon-bubble-primary"><x-icon name="mic" /></span>
    <div>
        <h2 id="essayer">{{ $title ?? 'Essayez à l\'oral avec '.$teacher }}</h2>
        <p>{{ $text ?? 'Lire ne suffit pas : il faut parler. Passez l\'évaluation orale gratuite : '.$minutes.' minutes avec '.$teacher.' pour connaître votre niveau, de A1 à C2, depuis votre téléphone.' }}</p>
        <x-teacher-lineup compact />
        <a class="btn btn-accent btn-lg" href="{{ \App\Support\Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
    </div>
</section>
