{{--
    Visage de Teacher Joe en pastille ronde (image fixe déjà utilisée par l'accueil et « Mon espace », 12 Ko, en cache) :
    pages d'erreur, hors connexion, états vides. Décoratif (alt vide) : le texte voisin dit tout. Image absente (hors
    connexion, pas encore en cache) : la pastille disparaît, la page reste propre.
--}}
@props(['size' => 96])

<span {{ $attributes->merge(['class' => 'hero-teacher-face teacher-face']) }} style="--face: {{ (int) $size }}px">
    <img src="{{ \App\Support\Asset::url('images/teacher-joe.webp') }}" width="{{ (int) $size }}" height="{{ (int) $size }}" alt="" onerror="this.parentNode.remove()">
</span>
