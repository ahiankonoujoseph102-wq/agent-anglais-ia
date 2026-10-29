{{--
    Ligne « Avec … · Changer » avant la séance du jour et « Mes cours » : montrée seulement quand le choix du professeur
    est proposé (au moins deux professeurs prêts) et avant le début de la séance (le professeur d'une séance commencée
    ne change jamais). « Changer » mène à la carte « Votre professeur » de « Mon espace », puis ramène ici.
--}}
@props(['teacher', 'back'])
@if (\App\Support\Teachers\Teachers::hasChoice())
    <p class="teacher-switch">
        <span class="teacher-switch-face"><img src="{{ \App\Support\Asset::url($teacher->portrait()) }}" width="36" height="36" alt=""></span>
        <span>Avec <strong>{{ $teacher->name() }}</strong></span>
        <a href="{{ $back !== '' ? route('dashboard', ['retour' => $back]) : route('dashboard') }}#professeur">Changer de professeur</a>
    </p>
@endif
