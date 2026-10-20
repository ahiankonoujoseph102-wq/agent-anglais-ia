@php
    use App\Enums\Level;
    use App\Support\Asset;

    $minutes = config('platform.assessment.max_minutes');
    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
    // Bouton principal : inscription pour un visiteur, évaluation (ou espace) pour un compte connecté.
    $start = auth()->check() ? (auth()->user()->assessment()->exists() ? route('dashboard') : route('assessment.show')) : route('register');
    $startLabel = auth()->check() && auth()->user()->assessment()->exists() ? 'Aller à mon espace' : 'Passer mon évaluation gratuite';
    // Section « élèves » : avec une formule active, le bouton ouvre directement « Mes cours ».
    $hasAccess = auth()->check() && auth()->user()->activeSubscription()->exists();
    $coursesUrl = $hasAccess ? route('courses.index') : $start;
    $coursesLabel = $hasAccess ? 'Ouvrir Mes cours' : $startLabel;
    $coursesIcon = $hasAccess ? 'book' : 'mic';
@endphp

<x-layout install :description="'Apprenez à parler anglais avec '.$teacher.', professeur d\'anglais en ligne disponible chaque jour sur votre téléphone. Évaluation orale gratuite.'">
    {{-- Haut de page : la promesse et le bouton orange, visibles sans faire défiler. --}}
    <section class="hero" aria-labelledby="promesse">
        {{-- Fond animé léger (CSS seulement) : trois formes floues ; bulles près de Teacher Joe. --}}
        <span class="hero-blob" aria-hidden="true"></span>
        <div class="hero-text rise">
            {{-- Téléphone : Teacher Joe visible dès l'ouverture (même image que plus bas, aucun poids en plus). --}}
            <p class="hero-teacher">
                <span class="hero-teacher-face"><img src="{{ Asset::url('images/teacher-joe.webp') }}" width="56" height="56" alt=""></span>
                <span><strong>{{ $teacher }}</strong> Votre professeur d'anglais à l'oral</span>
            </p>
            <span class="eyebrow hero-eyebrow"><x-icon name="mic" /> Osez parler anglais, pour les francophones</span>
            <h1 id="promesse">Parlez anglais avec un professeur <span class="accent">disponible chaque jour</span></h1>
            <p class="lead">
                {{ $teacher }} vous fait parler sans gêne ni jugement, vous corrige et vous fait progresser à votre rythme, depuis votre téléphone.
                Commencez par une évaluation orale gratuite de {{ $minutes }} minutes.
                Vous apprenez l'anglais américain, compris partout dans le monde.
                <span class="hero-also">Élève ou étudiant ? {{ $teacher }} vous aide aussi à comprendre vos cours d'anglais de l'école ou de l'université.</span>
            </p>
            <div class="actions actions-row">
                <a class="btn btn-accent btn-lg btn-block" href="{{ $start }}"><x-icon name="mic" /> {{ $startLabel }}</a>
                @guest
                    <a class="btn btn-outline btn-block" href="{{ route('login') }}">J'ai déjà un compte</a>
                @endguest
            </div>
            <ul class="hero-points">
                <li><x-icon name="check-circle" /> Gratuite et sans engagement</li>
                <li><x-icon name="check-circle" /> Votre niveau, de A1 à C2</li>
                <li><x-icon name="check-circle" /> Paiement par Mobile Money</li>
            </ul>
        </div>

        <figure class="hero-media rise" style="--i: 2">
            <img src="{{ Asset::url('images/teacher-joe.webp') }}" width="560" height="560" alt="{{ $teacher }}, le professeur d'anglais de {{ $platform }}" fetchpriority="high">
            <span class="hero-bubble right" lang="en" aria-hidden="true">Hello!</span>
            <span class="hero-bubble" lang="en" aria-hidden="true">Let's talk!</span>
            <span class="hero-bubble third" lang="en" aria-hidden="true">How are you?</span>
        </figure>
    </section>

    {{--
        « Êtes-vous vraiment fier de votre niveau en anglais ? » et « Vous avez déjà essayé ? » : des situations que l'on vit,
        jamais des témoignages (aucun nom, aucune photo, aucune citation attribuée). Deux cartes, côte à côte sur ordinateur.
    --}}
    <div class="section band persuade">
        <section class="card persuade-card reveal" aria-labelledby="reconnaissez">
            <h2 id="reconnaissez">Êtes-vous vraiment fier de votre niveau en anglais ?</h2>
            <p class="persuade-lead">Si vous vous reconnaissez dans l'une de ces phrases, {{ $teacher }} est fait pour vous.</p>
            <ul class="situations">
                <li><span class="icon-bubble"><x-icon name="mic-off" /></span> <span>Je comprends un peu l'anglais, mais quand il faut parler, je bloque.</span></li>
                <li><span class="icon-bubble"><x-icon name="messages" /></span> <span>Je cherche mes mots, et ça ne sort pas.</span></li>
                <li><span class="icon-bubble"><x-icon name="alert" /></span> <span>J'ai peur de faire des fautes et qu'on se moque de moi.</span></li>
                <li><span class="icon-bubble"><x-icon name="user-off" /></span> <span>Je n'ai personne avec qui pratiquer.</span></li>
            </ul>
            <p class="persuade-answer"><strong>Ce n'est pas un manque d'intelligence : c'est un manque de pratique.</strong> {{ $teacher }} est là pour ça.</p>
            <a class="btn btn-accent btn-block" href="{{ $start }}"><x-icon name="mic" /> {{ $startLabel }}</a>
        </section>

        <section class="card persuade-card reveal" style="--i: 1" aria-labelledby="deja-essaye">
            <h2 id="deja-essaye">Vous avez déjà essayé ?</h2>
            <ul class="tried">
                <li><x-icon name="close-circle" /> <span>Des applications où l'on clique sur des réponses, sans jamais parler.</span></li>
                <li><x-icon name="close-circle" /> <span>Des vidéos regardées… puis oubliées.</span></li>
                <li><x-icon name="close-circle" /> <span>Des cours de grammaire, sans personne pour vous écouter.</span></li>
            </ul>
            <p class="tried-yes"><x-icon name="check-circle" /> <span>Avec {{ $platform }}, vous parlez chaque jour, et {{ $teacher }} vous écoute et vous corrige.</span></p>
        </section>
    </div>

    {{-- Sections alternées : bleu très clair, bleu nuit (une seule), blanc… (.band, fond sur toute la largeur). --}}
    <section class="section band band-soft" aria-labelledby="etapes">
        <div class="section-head reveal">
            <span class="eyebrow">Comment ça marche</span>
            <h2 id="etapes">Quatre étapes simples</h2>
        </div>
        <ol class="how">
            <li class="card reveal" style="--i: 0">
                <div class="how-head"><span class="how-num">1</span><span class="icon-bubble"><x-icon name="user-plus" /></span></div>
                <h3>Créez votre compte</h3>
                <p>Avec votre nom et votre numéro de téléphone. Aucune carte bancaire.</p>
            </li>
            <li class="card reveal" style="--i: 1">
                <div class="how-head"><span class="how-num">2</span><span class="icon-bubble"><x-icon name="mic" /></span></div>
                <h3>Passez l'évaluation gratuite</h3>
                <p>{{ $minutes }} minutes à l'oral pour trouver votre niveau, puis une mini-leçon et vos questions.</p>
            </li>
            <li class="card reveal" style="--i: 2">
                <div class="how-head"><span class="how-num">3</span><span class="icon-bubble"><x-icon name="mobile" /></span></div>
                <h3>Choisissez votre formule</h3>
                <p>Payez par Mobile Money et envoyez la capture. Souvent, votre accès s'ouvre tout de suite pour 24 heures, puis notre équipe le confirme.</p>
            </li>
            <li class="card reveal" style="--i: 3">
                <div class="how-head"><span class="how-num">4</span><span class="icon-bubble"><x-icon name="calendar-check" /></span></div>
                <h3>Parlez chaque jour</h3>
                <p>{{ $teacher }} vous accueille, 4 petites questions, et votre programme est prêt : première séance possible le jour même.</p>
            </li>
        </ol>
    </section>

    {{--
        Comment se passe le cours : la séance du jour en quatre temps (config/teacher.php → lesson), avec un aperçu du
        Tableau en HTML (aucune image ; phrases de l'unité 1 du niveau A1 du programme officiel). Seule section bleu nuit.
    --}}
    <section class="section band band-dark" aria-labelledby="cours">
        <div class="course-grid">
            <div class="section-head reveal">
                <span class="eyebrow">Une séance avec {{ $teacher }}</span>
                <h2 id="cours">Comment se passe votre cours avec {{ $teacher }} ?</h2>
                <p class="muted">Patient, sans jugement, à votre rythme : vous pouvez lui poser une question à tout moment, même en français.</p>
            </div>
            <figure class="board board-preview reveal" aria-label="Aperçu du Tableau pendant une séance">
                <div class="board-head">
                    <span class="board-label"><x-icon name="list" /> Tableau</span>
                    <span class="board-phase">Leçon</span>
                </div>
                <div class="board-body">
                    <p class="board-title" lang="en">Greetings and introductions</p>
                    <ul class="board-list">
                        <li class="board-item is-seen"><span class="board-en" lang="en">Good morning, madam!</span><span class="board-fr">Bonjour, madame !</span></li>
                        <li class="board-item is-lit"><span class="board-en" lang="en">My name is Afi. I'm from Togo.</span><span class="board-fr">Je m'appelle Afi. Je viens du Togo.</span></li>
                        <li class="board-item"><span class="board-en" lang="en">How are you? I'm fine, thanks.</span><span class="board-fr">Comment allez-vous ? Je vais bien, merci.</span></li>
                    </ul>
                </div>
            </figure>
            <ol class="card course-steps reveal">
                <li><span class="how-num course-num">1</span><p><strong>La leçon :</strong> {{ $teacher }} vous enseigne les phrases, les mots et les verbes du jour, affichés au tableau, en anglais simple, avec une explication en français si vous ne comprenez pas.</p></li>
                <li><span class="how-num course-num">2</span><p><strong>La pratique :</strong> vous répétez et vous construisez vos phrases, guidé par {{ $teacher }}.</p></li>
                <li><span class="how-num course-num">3</span><p><strong>La conversation :</strong> vous utilisez la leçon dans une vraie situation (travail, marché, entretien…), il vous corrige avec douceur.</p></li>
                <li><span class="how-num course-num">4</span><p><strong>Le bilan :</strong> ce que vous avez appris, et une phrase à retenir.</p></li>
            </ol>
        </div>
        <ul class="course-plus reveal">
            <li><x-icon name="pin" /> <span><strong>Des situations de chez nous :</strong> le Grand Marché de Lomé, le zémidjan, le port, le travail, le voyage, les études.</span></li>
            <li><x-icon name="calendar" /> <span><strong>À votre rythme :</strong> vous choisissez vos jours, et chaque séance a une durée fixe.</span></li>
        </ul>
        <ul class="levels reveal" aria-label="Les niveaux, de A1 à C2">
            @foreach (Level::cases() as $level)
                <li><strong class="level-dot">{{ $level->value }}</strong><span>{{ $level->label() }}</span></li>
            @endforeach
        </ul>
        <p class="muted small reveal next-step">Un programme construit pour vous, vos erreurs revues aux séances suivantes, un bilan oral et une attestation à la fin de chaque niveau.</p>
    </section>

    <section class="section band" aria-labelledby="plans">
        <div class="section-head reveal">
            <span class="eyebrow">Formules</span>
            <h2 id="plans">Des formules simples, sans engagement</h2>
            <p class="muted">L'évaluation est offerte. Ensuite, choisissez la durée de séance qui vous convient.</p>
        </div>
        <div class="plans">
            @foreach ($plans as $plan)
                <x-plan-card :plan="$plan" :href="auth()->check() ? route('payment.create', $plan->key) : route('register')" class="reveal" style="--i: {{ $loop->index }}" />
            @endforeach
        </div>
        {{-- Moyens de paiement groupés par pays, en petites étiquettes. --}}
        <div class="pay-methods reveal">
            <p class="muted small">Paiement par Mobile Money, depuis votre téléphone. <a href="{{ route('plans') }}">Tout savoir sur les formules et les prix</a>.</p>
            <ul class="pay-countries" aria-label="Moyens de paiement par pays">
                @foreach ($paymentCountries as $paymentCountry)
                    <li>
                        <span class="pay-country">{{ $paymentCountry['name'] }}</span>
                        <span class="tags">
                            @foreach ($paymentCountry['operators'] as $operatorName)
                                <span class="tag">{{ $operatorName }}</span>
                            @endforeach
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{--
        Quatre cartes compactes de même format (.students.audience) : étudiants, élèves, parents, professionnels.
        Chacune : une photo, un titre, une phrase, trois points courts au plus, un bouton orange.
        Téléphone : l'une sous l'autre ; à partir de 720 pixels : grille 2 × 2.
    --}}
    <div class="section band band-soft audiences">
        <section class="students audience reveal" aria-labelledby="etudiants">
            <figure class="photo-frame"><x-photo name="etudiants-bibliotheque" /></figure>
            <div class="audience-body">
                <h2 id="etudiants">Vous êtes étudiant ?</h2>
                <p>Prenez la parole en anglais : exposés, entretiens, études ou stage à l'étranger.</p>
                <ul class="audience-points">
                    <li><x-icon name="check-circle" /> Oser parler, sans gêne ni jugement</li>
                    <li><x-icon name="check-circle" /> S'entraîner aux entretiens de bourse, d'admission ou de stage</li>
                    <li><x-icon name="check-circle" /> Vos cours d'anglais en PDF, avec « Mes cours »</li>
                </ul>
                <a class="btn btn-accent" href="{{ $start }}"><x-icon name="mic" /> {{ $startLabel }}</a>
            </div>
        </section>

        <section class="students audience reveal" aria-labelledby="eleves">
            <figure class="photo-frame"><x-photo name="eleve-ecouteurs" /></figure>
            <div class="audience-body">
                <h2 id="eleves">Vous êtes élève ?</h2>
                <p>Vos cours d'anglais enfin compris : en plus de l'oral, {{ $teacher }} vous aide dans vos leçons d'anglais de l'école avec « Mes cours ».</p>
                <ul class="audience-points">
                    <li><x-icon name="camera" /> {{ \App\Support\CourseFiles::offer() }}</li>
                    <li><x-icon name="book" /> {{ $teacher }} vous l'explique en détail, en français quand il le faut, et répond à vos questions</li>
                    <li><x-icon name="edit" /> Il vous guide pour faire votre exercice vous-même, puis corrige</li>
                </ul>
                <p class="help-only"><x-icon name="info" /> Uniquement pour les cours d'anglais. Aussi pour les étudiants : lycée, université, formation.</p>
                <a class="btn btn-accent" href="{{ $coursesUrl }}"><x-icon :name="$coursesIcon" /> {{ $coursesLabel }}</a>
            </div>
        </section>

        <section class="students audience reveal" aria-labelledby="parents">
            <figure class="photo-frame"><x-photo name="parent-enfant-lecture" /></figure>
            <div class="audience-body">
                <h2 id="parents">Vous êtes parent ?</h2>
                <p>Un professeur d'anglais patient pour votre enfant.</p>
                <ul class="audience-points">
                    <li><x-icon name="check-circle" /> {{ $teacher }} ne fait pas les devoirs à la place de votre enfant</li>
                    <li><x-icon name="check-circle" /> Il l'aide à comprendre sa leçon d'anglais et à faire lui-même son exercice, une étape après l'autre</li>
                    <li><x-icon name="check-circle" /> Il l'entraîne aussi à parler anglais à voix haute.</li>
                </ul>
                <a class="btn btn-accent" href="{{ route('register') }}"><x-icon name="user-plus" /> Inscrire mon enfant</a>
            </div>
        </section>

        <section class="students audience reveal" aria-labelledby="pros">
            <figure class="photo-frame"><x-photo name="equipe-reunion" /></figure>
            <div class="audience-body">
                <h2 id="pros">Vous travaillez ?</h2>
                <p>Réunions, clients, visiteurs : parlez anglais dans votre métier.</p>
                <ul class="audience-points">
                    <li><x-icon name="check-circle" /> Indiquez votre métier et votre objectif dans le questionnaire</li>
                    <li><x-icon name="check-circle" /> Les situations qui vous concernent passent en premier</li>
                    <li><x-icon name="check-circle" /> Accueillir un client étranger, présenter votre activité, négocier, voyager</li>
                </ul>
                <a class="btn btn-accent" href="{{ $start }}"><x-icon name="mic" /> {{ $startLabel }}</a>
            </div>
        </section>
    </div>

    <section class="section band" aria-labelledby="pays">
        <div class="section-head reveal">
            <span class="eyebrow">Dans votre pays</span>
            <h2 id="pays">Apprendre l'anglais au Togo, au Bénin, au Burkina Faso et en Côte d'Ivoire</h2>
            <p class="muted">Mêmes formules et mêmes prix partout, avec les moyens de paiement de votre pays. Vous habitez ailleurs ? {{ $platform }} accueille les francophones du monde entier, abonnement avec notre équipe sur WhatsApp. <a href="{{ route('about') }}">Qui sommes-nous ?</a></p>
        </div>
        <x-country-links :pages="$countryPages" />
    </section>

    {{-- « Qui est derrière … ? » : le fondateur, sobrement (config/platform.php → founder ; photo chargée à la demande). --}}
    @if (\App\Support\Founder::bio())
        <section class="section band founder-section" aria-labelledby="fondateur-accueil">
            <div class="card founder-card reveal">
                @if (\App\Support\Founder::photoUrl())
                    <img class="founder-photo" src="{{ \App\Support\Founder::photoUrl() }}" width="320" height="320" alt="{{ \App\Support\Founder::photoAlt() }}" loading="lazy" decoding="async">
                @endif
                <div>
                    <h2 id="fondateur-accueil">Qui est derrière {{ $platform }} ?</h2>
                    <p class="founder-name"><strong>{{ config('platform.founder.name') }}</strong>, {{ config('platform.founder.headline') }}</p>
                    <p class="founder-bio">{{ \App\Support\Founder::bio() }}</p>
                    <a href="{{ route('about') }}" class="founder-more">En savoir plus <x-icon name="arrow-right" /></a>
                </div>
            </div>
        </section>
    @endif

    <section class="section band band-soft faq-section" aria-labelledby="questions">
        <div class="section-head reveal">
            <span class="eyebrow">Vos questions</span>
            <h2 id="questions">Questions fréquentes</h2>
        </div>
        {{-- Questions fréquentes : le même HTML donne les données structurées FAQPage (App\Support\Seo::faq). --}}
        @php(ob_start())
        <div class="faq reveal">
            <details>
                <summary><x-icon name="wallet" /><span>Comment payer mon abonnement ?</span></summary>
                <p>Par Mobile Money, depuis votre téléphone. Vous envoyez le montant exact au numéro affiché sur le site, puis la capture d'écran de la confirmation. Aucune carte bancaire n'est nécessaire. Mêmes formules et mêmes prix dans tous les pays :</p>
                <ul class="faq-list">
                    @foreach ($paymentCountries as $paymentCountry)
                        <li><strong>{{ $paymentCountry['name'] }}</strong> : {{ implode(', ', $paymentCountry['operators']) }}</li>
                    @endforeach
                </ul>
                <p>Vous habitez dans un autre pays ? L'inscription et l'évaluation gratuite fonctionnent partout : notre équipe vous explique sur WhatsApp comment vous abonner, avec les mêmes prix en francs CFA.</p>
            </details>
            <details>
                <summary><x-icon name="languages" /><span>Quel anglais vais-je apprendre ?</span></summary>
                <p>L'anglais américain, compris partout dans le monde : sa prononciation, son vocabulaire et son orthographe. Si vous avez appris des mots britanniques à l'école, ils ne sont jamais comptés comme des fautes : {{ $teacher }} vous donne simplement l'équivalent américain quand c'est utile.</p>
            </details>
            <details>
                <summary><x-icon name="mic" /><span>Comment se passe une séance ?</span></summary>
                <p>Elle a une durée fixe et se passe en quatre temps. La leçon : {{ $teacher }} vous enseigne les phrases, les mots et les verbes du jour, affichés au tableau. La pratique : vous répétez et vous construisez vos phrases. La conversation : vous utilisez la leçon dans une situation de votre vie, il vous corrige avec douceur. Le bilan : ce que vous avez appris et une phrase à retenir. Il parle anglais, adapté à votre niveau, et vous explique en français quand vous ne comprenez pas.</p>
            </details>
            <details>
                <summary><x-icon name="calendar-check" /><span>Que se passe-t-il après le paiement ? Qu'est-ce que l'accès provisoire ?</span></summary>
                <p>Vous envoyez la capture de la confirmation. Si elle est claire (bon montant, bon numéro, paiement récent), vous avez un accès provisoire : vos séances commencent tout de suite, pendant 24 heures, le temps que notre équipe vérifie votre paiement ; une fois vérifiée, votre formule devient définitive, sans perdre aucun jour. Sinon, l'équipe vérifie d'abord. {{ $teacher }} vous accueille, vous répondez à 4 petites questions (objectif, métier, jours, difficultés) et votre programme personnel est prêt : votre première séance peut avoir lieu le jour même.</p>
            </details>
            <details>
                <summary><x-icon name="headphones" /><span>De quel matériel ai-je besoin ?</span></summary>
                <p>Un téléphone ou un ordinateur avec un micro, un navigateur récent comme Chrome, et une connexion internet. Des écouteurs sont pratiques mais pas obligatoires. Aucune application à installer : vous pouvez simplement ajouter {{ $platform }} à l'écran d'accueil de votre téléphone.</p>
            </details>
            <details>
                <summary><x-icon name="wifi" /><span>Combien de données internet faut-il ?</span></summary>
                <p>Les pages du site sont légères. Pendant une séance, la voix passe en continu : comptez environ 3 à 4 mégaoctets par minute selon le temps de parole, soit environ 50 mégaoctets pour une séance de 15 minutes. Le professeur en 3D (environ 1 mégaoctet) n'est téléchargé qu'une seule fois. Si vous le pouvez, utilisez le wifi.</p>
            </details>
            <details>
                <summary><x-icon name="award" /><span>Qu'est-ce que l'attestation de niveau ?</span></summary>
                <p>À la fin de chaque niveau (douze unités), vous passez un bilan oral avec {{ $teacher }}. S'il est réussi, vous recevez une attestation de niveau en PDF, que chacun peut vérifier en ligne grâce à son code.</p>
            </details>
            <details>
                <summary><x-icon name="book" /><span>Puis-je travailler mes cours d'anglais, même en PDF ?</span></summary>
                <p>Oui, avec « Mes cours », pour les élèves et les étudiants (lycée, université, formation) qui ont une formule active. {{ \App\Support\CourseFiles::offer() }}. Pour un long PDF, vous choisissez avant la séance les pages ({{ \App\Support\CourseFiles::partPages() }} au plus à la fois) ou le passage à travailler, et vous revenez un autre jour pour la suite, sans renvoyer le fichier. {{ $teacher }} lit votre leçon et vous l'explique, répond à vos questions et vous guide pour faire l'exercice vous-même. C'est uniquement pour les cours d'anglais, et c'est une aide en plus de votre programme pour parler anglais. Votre formule donne un temps par jour, que vous partagez librement entre la séance orale et « Mes cours ».</p>
            </details>
            <details>
                <summary><x-icon name="bubble" /><span>Puis-je m'entraîner pour un entretien ou un exposé en anglais ?</span></summary>
                <p>Oui. Dans le questionnaire de votre programme, choisissez l'objectif « Préparer un entretien (bourse, admission, visa, stage, embauche) » ou « Faire un exposé ou une soutenance en anglais ». Pour un entretien, {{ $teacher }} joue l'examinateur : il pose les questions une par une, écoute votre réponse, puis vous corrige et vous propose une meilleure formulation. C'est un entraînement à l'oral : ce n'est pas la préparation d'un examen officiel, et aucun résultat n'est garanti.</p>
            </details>
            <details>
                <summary><x-icon name="gift" /><span>L'évaluation est-elle vraiment gratuite ?</span></summary>
                <p>Oui. Une évaluation orale par compte, sans paiement ni engagement : {{ $minutes }} minutes pour trouver votre niveau, puis une mini-leçon pour découvrir comment se passe un cours, et vos questions ({{ \App\Models\Assessment::totalMinutes() }} minutes au plus en tout). Ensuite, vous décidez.</p>
            </details>
        </div>
        @php($faqHtml = ob_get_clean())
        {!! $faqHtml !!}
        @push('head')
            {!! \App\Support\Seo::jsonLd(\App\Support\Seo::organization()) !!}
            {!! \App\Support\Seo::jsonLd(\App\Support\Seo::website()) !!}
            {!! \App\Support\Seo::jsonLd(\App\Support\Seo::offers()) !!}
            {!! \App\Support\Seo::jsonLd(\App\Support\Seo::faq($faqHtml)) !!}
        @endpush
    </section>

    <section class="section band">
        <div class="cta-band reveal">
            <h2>Votre anglais oral commence aujourd'hui</h2>
            <p>Une évaluation orale gratuite avec {{ $teacher }} : votre niveau, une première phrase apprise et vos questions.</p>
            <div class="cta-actions">
                <a class="btn btn-accent btn-lg" href="{{ $start }}"><x-icon name="mic" /> {{ $startLabel }}</a>
                @if ($whatsappUrl)
                    <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Une question ? WhatsApp</a>
                @endif
            </div>
        </div>
    </section>
</x-layout>
