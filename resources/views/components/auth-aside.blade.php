{{-- Panneau d'aide de la connexion et de l'inscription (grand écran) : Teacher Joe et les trois points de l'accueil. --}}
@php($teacher = config('avatar.model.name'))
<figure class="aside-teacher">
    <span class="aside-face"><img src="{{ \App\Support\Asset::url('images/teacher-joe.webp') }}" width="560" height="560" alt="" loading="lazy" decoding="async"></span>
    <figcaption><strong>{{ $teacher }}</strong> Votre professeur d'anglais à l'oral</figcaption>
</figure>
<ul class="hero-points">
    <li><x-icon name="check-circle" /> Gratuite et sans engagement</li>
    <li><x-icon name="check-circle" /> Votre niveau, de A1 à C2</li>
    <li><x-icon name="check-circle" /> Paiement par Mobile Money</li>
</ul>
