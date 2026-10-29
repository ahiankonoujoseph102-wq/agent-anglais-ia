{{--
    Ligne « Avec … · Changer » avant la séance du jour et « Mes cours » : montrée seulement quand le choix du professeur
    est proposé (au moins deux professeurs prêts) et avant le début de la séance (le professeur d'une séance commencée
    ne change jamais). « Changer » mène à la carte « Votre professeur » de « Mon espace », puis ramène ici.
--}}
@props(['teacher', 'back'])
@if (\App\Support\Teachers\Teachers::hasChoice())
    <p class="teacher-switch">
        <x-icon name="user" />
        <span>Avec <strong>{{ $teacher->name() }}</strong></span>
        <a href="{{ route('dashboard', ['retour' => $back]) }}#professeur">Changer<span class="sr-only"> de professeur</span></a>
    </p>
@endif
