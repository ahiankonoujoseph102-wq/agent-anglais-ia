{{--
    Évaluation gratuite pas encore commencée : « Avec Teacher Joe · Préférer Teacher Rose ». Teacher Joe est choisi
    d'avance ; le lien recharge la page avec l'autre professeur (?professeur=…). Rien avec un seul professeur proposé.
--}}
@props(['teacher'])
@if (\App\Support\Teachers\Teachers::hasChoice())
    <p class="teacher-switch assessment-teacher">
        <x-icon name="user" />
        <span>Avec <strong>{{ $teacher->name() }}</strong></span>
        @foreach (\App\Support\Teachers\Teachers::choosable() as $other)
            @continue($other->key === $teacher->key)
            <a href="{{ route('assessment.show', $other->isDefault() ? [] : ['professeur' => $other->key]) }}" data-busy-label="{{ $other->name() }} arrive…">Préférer {{ $other->name() }}</a>
        @endforeach
    </p>
@endif
