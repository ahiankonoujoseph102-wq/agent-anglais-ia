{{--
    « À propos » : uniquement des faits vérifiés (config/platform.php → founder, fonctionnement du site).
    Présentation du fondateur (founder.bio, écrite par lui), son histoire (founder.story) et sa photo : non affichées
    si elles manquent. Aucune mention de l'intelligence artificielle (règle du fondateur pour les pages publiques).
--}}
@php
    use App\Support\Asset;
    use App\Support\Founder;
    use App\Support\Navigation;
    use App\Support\Seo;

    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
    $founder = config('platform.founder');
    $minutes = config('platform.assessment.max_minutes');
    $keepDays = (int) config('platform.transcripts.keep_days');
@endphp

<x-layout :title="'À propos : '.$founder['name'].', fondateur'"
          :description="$founder['name'].', fondateur de '.$platform.' à '.$founder['city'].', au '.$founder['country'].' : l\'histoire vraie de la plateforme et de '.$teacher.', professeur d\'anglais en ligne.'">
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="info" /></span>
        <h1>{{ $founder['name'] }} et l'histoire de {{ $platform }}</h1>
        <p>Apprendre à parler anglais depuis son téléphone, avec un professeur disponible chaque jour.</p>
    </div>

    <section class="section" aria-labelledby="apropos-fondateur" id="fondateur">
        <div class="card about-card reveal">
            @if (Founder::photoUrl())
                <img class="founder-photo" src="{{ Founder::photoUrl() }}" width="320" height="320" alt="{{ Founder::photoAlt() }}" loading="lazy" decoding="async">
            @else
                <span class="icon-bubble"><x-icon name="user" /></span>
            @endif
            <div>
                <h2 id="apropos-fondateur">{{ $founder['name'] }}, {{ mb_strtolower($founder['role']) }} de {{ $platform }}</h2>
                <p>{{ $platform }} a été fondé par {{ $founder['name'] }}, {{ $founder['headline'] }}, au {{ $founder['country'] }}, qui en a aussi conçu et développé le site.</p>
                @if (Founder::bio())
                    <p class="founder-bio">{{ Founder::bio() }}</p>
                @endif
            </div>
        </div>
    </section>

    @if (Founder::story())
        <section class="section" aria-labelledby="apropos-histoire" id="histoire">
            <div class="section-head reveal">
                <span class="eyebrow">Notre histoire</span>
                <h2 id="apropos-histoire">Pourquoi {{ $platform }} existe</h2>
            </div>
            <div class="card about-story reveal">
                @foreach (Founder::story() as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                <p><a href="{{ route('plans') }}">Voir les formules et les prix</a></p>
                <x-social-links class="about-social" />
            </div>
        </section>
    @endif

    <section class="section" aria-labelledby="apropos-pourquoi">
        <div class="section-head reveal">
            <span class="eyebrow">Pourquoi {{ $platform }}</span>
            <h2 id="apropos-pourquoi">Parler anglais, pas seulement l'étudier</h2>
        </div>
        <div class="features">
            <article class="card feature reveal">
                <span class="icon-bubble icon-bubble-primary"><x-icon name="mic" /></span>
                <div>
                    <h3>Pour les francophones d'Afrique</h3>
                    <p>{{ $platform }} enseigne l'anglais oral aux francophones de tous les pays, en commençant par le Togo. Les leçons partent de situations d'ici (le marché, le transport, l'administration) et vous préparent aux échanges internationaux : travail, voyage, études, téléphone et internet. On y apprend l'anglais américain, compris partout dans le monde. Étudiants, élèves, parents, enseignants ou professionnels : chacun choisit son objectif, par exemple oser parler, étudier ou voyager à l'étranger, préparer un entretien ou parler avec des clients.</p>
                </div>
            </article>
            <article class="card feature reveal" style="--i: 1">
                <span class="icon-bubble"><x-icon name="mobile" /></span>
                <div>
                    <h3>Depuis un simple téléphone</h3>
                    <p>Un site léger, sans application à installer, et un paiement par Mobile Money au Togo, au Bénin, au Burkina Faso et en Côte d'Ivoire.</p>
                </div>
            </article>
            <article class="card feature reveal" style="--i: 2">
                <span class="icon-bubble"><x-icon name="calendar" /></span>
                <div>
                    <h3>Un professeur chaque jour</h3>
                    <p>Une séance orale chaque jour que vous choisissez, construite sur votre niveau, votre objectif et votre métier.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="section" aria-labelledby="apropos-teacher">
        <div class="students students-reverse reveal">
            <figure class="photo-frame"><img src="{{ Asset::url('images/teacher-joe.webp') }}" width="560" height="560" alt="{{ $teacher }}, le professeur d'anglais de {{ $platform }}" loading="lazy" decoding="async"></figure>
            <div>
                <span class="eyebrow">Votre professeur</span>
                <h2 id="apropos-teacher">Comment fonctionne {{ $teacher }}</h2>
                <ul class="check-list">
                    <li><x-icon name="sparkle" /> {{ $teacher }} est un personnage animé en 3D dans votre navigateur ; sa bouche suit sa voix.</li>
                    <li><x-icon name="bubble" /> {{ $teacher }} a été conçu et programmé par {{ $founder['name'] }}, selon la méthode pédagogique de {{ $platform }}.</li>
                    @foreach (\App\Support\Teachers\Teachers::choosable() as $other)
                        @continue($other->isDefault())
                        <li><x-icon name="users" /> <span>{{ $other->name() }}, {{ $other->pronoun() === 'elle' ? 'professeure' : 'professeur' }} de {{ $platform }} {{ $other->pronoun() === 'elle' ? 'elle' : 'lui' }} aussi, a été {{ $other->pronoun() === 'elle' ? 'conçue' : 'conçu' }} de la même façon, avec la même méthode. Vous choisissez votre professeur dans « Mon espace », et vous pouvez en changer chaque jour.</span></li>
                    @endforeach
                    <li><x-icon name="level" /> L'évaluation orale gratuite donne votre niveau, de A1 à C2, en {{ $minutes }} minutes, puis {{ config('avatar.model.name') }} vous montre comment se passe une leçon et répond à vos questions.</li>
                    <li><x-icon name="check-circle" /> <span>Chaque séance se passe en quatre temps : la leçon au tableau, la pratique guidée, une conversation dans une situation de votre vie, puis le bilan ; il vous corrige aussitôt et garde vos erreurs pour les revoir.</span></li>
                    <li><x-icon name="clock" /> Il annonce la durée, vous prévient avant la fin, et le site arrête la séance à l'heure.</li>
                    <li><x-icon name="shield" /> <span>Votre voix n'est jamais enregistrée ; le texte des conversations est effacé après {{ $keepDays }} jours (voir la page <a href="{{ route('privacy') }}">Confidentialité</a>).</span></li>
                </ul>
                <a class="btn btn-accent btn-lg" href="{{ Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
                <p class="muted small next-step"><a href="{{ route('how') }}">Voir {{ $teacher }} en vidéo : comment ça marche</a></p>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="apropos-pays">
        <div class="section-head reveal">
            <h2 id="apropos-pays">Apprendre l'anglais dans votre pays</h2>
        </div>
        <x-country-links :pages="$countryPages" />
    </section>

    @push('head')
        {!! Seo::jsonLd(Seo::founder()) !!}
        {!! Seo::jsonLd(Seo::organization(withProfiles: true)) !!}
    @endpush
</x-layout>
