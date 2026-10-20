{{--
    « À propos » : uniquement des faits vérifiés (config/platform.php → founder, fonctionnement du site).
    Présentation du fondateur (founder.bio, écrite par lui) et sa photo : non affichées si elles manquent.
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

<x-layout :title="'À propos : '.$founder['name'].' et '.$teacher"
          :description="$platform.', créé à '.$founder['city'].' par '.$founder['name'].' : pourquoi la plateforme existe et comment '.$teacher.', professeur d\'anglais IA, vous fait parler.'">
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="info" /></span>
        <h1>À propos de {{ $platform }}</h1>
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
                <h2 id="apropos-fondateur">{{ $founder['name'] }}, {{ mb_strtolower($founder['role']) }}</h2>
                <p>{{ $platform }} a été fondé par {{ $founder['name'] }}, à {{ $founder['city'] }}, au {{ $founder['country'] }}, qui en a aussi conçu et développé le site.</p>
                @if (Founder::bio())
                    <p class="founder-bio">{{ Founder::bio() }}</p>
                @endif
            </div>
        </div>
    </section>

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
                    <li><x-icon name="bubble" /> Sa voix et ses réponses viennent d'une intelligence artificielle de Google (Gemini) ou, en secours, d'OpenAI.</li>
                    <li><x-icon name="level" /> L'évaluation orale gratuite ({{ $minutes }} minutes au plus) donne votre niveau, de A1 à C2.</li>
                    <li><x-icon name="check-circle" /> Pendant la séance, il vous fait parler, vous corrige aussitôt et garde vos erreurs pour les revoir.</li>
                    <li><x-icon name="clock" /> Il annonce la durée, vous prévient avant la fin, et le site arrête la séance à l'heure.</li>
                    <li><x-icon name="shield" /> Votre voix n'est jamais enregistrée ; le texte des conversations est effacé après {{ $keepDays }} jours (<a href="{{ route('privacy') }}">Confidentialité</a>).</li>
                </ul>
                <a class="btn btn-accent btn-lg" href="{{ Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
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
    @endpush
</x-layout>
