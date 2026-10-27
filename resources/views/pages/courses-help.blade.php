{{--
    Page d'arrivée « Aide aux cours d'anglais » (publique, indexée) : « Mes cours » expliqué aux élèves, aux étudiants
    et aux parents. Uniquement des faits du code (CourseFiles, platform.courses, documents de révision). Règles de texte :
    jamais « fait les devoirs » (sauf la négation), « donne les réponses », « tous vos cours », « correction écrite » ;
    toujours « uniquement les cours d'anglais ». Questions affichées = données structurées FAQPage.
--}}
@php
    use App\Support\CourseFiles;
    use App\Support\Navigation;
    use App\Support\Seo;

    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
    $sessionMinutes = (int) config('platform.courses.max_session_minutes');
    $minutes = config('platform.assessment.max_minutes');
@endphp

<x-layout title="Aide aux cours d'anglais pour élèves et étudiants"
          :description="'Votre leçon d\'anglais en photo ou en PDF, expliquée à l\'oral et en français par '.$teacher.', avec un document de révision. Élèves, étudiants, parents.'">
    <section class="section page-hero" aria-labelledby="aide-titre">
        <div class="students reveal">
            <figure class="photo-frame"><x-photo name="parent-enfant-lecture" /></figure>
            <div>
                <span class="eyebrow"><x-icon name="book" /> Élèves, étudiants, parents</span>
                <h1 id="aide-titre">Aide aux cours d'anglais : comprenez enfin vos leçons</h1>
                <p class="lead">Prenez votre leçon d'anglais en photo, ou envoyez-la en PDF : {{ $teacher }} la lit, puis vous l'explique en français, à l'oral, et vous aide à faire vos exercices vous-même.</p>
                <div class="actions actions-row">
                    <a class="btn btn-accent btn-lg btn-block" href="{{ Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
                    <a class="btn btn-outline btn-block" href="{{ route('register') }}">Inscrire mon enfant</a>
                </div>
                <p class="muted small next-step">Uniquement pour les cours d'anglais : collège, lycée, université ou formation.</p>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="aide-comment">
        <div class="section-head reveal">
            <span class="eyebrow">« Mes cours »</span>
            <h2 id="aide-comment">Comment ça se passe</h2>
        </div>
        <ol class="how-steps">
            <li>{{ CourseFiles::offer() }}. Un PDF plus long ? Vous choisissez la partie à travailler, {{ CourseFiles::partPages() }} pages au plus à la fois.</li>
            <li>{{ $teacher }} lit la leçon. Si la photo est illisible, il vous le dit et vous conseille pour en prendre une meilleure.</li>
            <li>Vous parlez avec lui, à l'oral, {{ $sessionMinutes }} minutes au plus par séance : il vous demande ce que vous voulez (comprendre toute la leçon, poser une question, travailler un exercice), puis il explique en français, point par point, avec des exemples en anglais.</li>
            <li>Pour un exercice, il ne fait pas les devoirs à votre place : il explique la consigne, donne un indice, vous laisse proposer votre réponse, puis corrige et explique.</li>
            <li>Après la séance (sauf une séance très courte), vous recevez un document de révision en PDF : la leçon expliquée simplement, vos erreurs, les phrases à retenir et un petit exercice à refaire seul.</li>
        </ol>
    </section>

    <section class="section" aria-labelledby="aide-qui">
        <div class="section-head reveal">
            <h2 id="aide-qui">Pour qui ?</h2>
        </div>
        <div class="features">
            <article class="card feature reveal">
                <span class="icon-bubble icon-bubble-primary"><x-icon name="school" /></span>
                <div>
                    <h3>Les élèves</h3>
                    <p>Vous n'avez pas compris la leçon en classe, ou vous n'osez pas poser la question ? Vous la reprenez à votre rythme, aussi souvent que nécessaire, et vous pouvez poser vos questions en français.</p>
                </div>
            </article>
            <article class="card feature reveal" style="--i: 1">
                <span class="icon-bubble"><x-icon name="book" /></span>
                <div>
                    <h3>Les étudiants</h3>
                    <p>Cours d'anglais de l'université ou de la formation, en PDF : vous travaillez la partie qui vous pose problème, et vous vous entraînez à l'oral pour vos exposés.</p>
                </div>
            </article>
            <article class="card feature reveal" style="--i: 2">
                <span class="icon-bubble"><x-icon name="users" /></span>
                <div>
                    <h3>Les parents</h3>
                    <p>Vous ne parlez pas anglais, ou pas assez pour aider votre enfant ? {{ $teacher }} lui explique ses leçons avec patience, en français. Inscrivez-le avec votre numéro et restez à ses côtés pour le paiement.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="section" aria-labelledby="aide-prix">
        <div class="section-head reveal">
            <span class="eyebrow">Prix en francs CFA</span>
            <h2 id="aide-prix">Compris dans chaque formule</h2>
            <p class="muted">« Mes cours » se partage le temps du jour de la formule avec la séance orale. L'évaluation orale de {{ $minutes }} minutes, elle, est gratuite.</p>
        </div>
        <div class="plans">
            @foreach ($plans as $plan)
                <x-plan-card compact :plan="$plan" :href="auth()->check() ? route('payment.create', $plan->key) : route('register')" class="reveal" style="--i: {{ $loop->index }}" />
            @endforeach
        </div>
    </section>

    <section class="section" aria-labelledby="aide-questions">
        <div class="section-head reveal">
            <h2 id="aide-questions">Vos questions</h2>
        </div>
        @php(ob_start())
        <div class="faq reveal">
            <details>
                <summary><x-icon name="help" /><span>{{ $teacher }} fait-il les exercices à ma place ?</span></summary>
                <p>Non. Il explique la consigne, donne un indice et vous laisse essayer. Si vous bloquez encore après deux essais, il explique la bonne réponse, pour que vous compreniez la règle et sachiez la refaire seul.</p>
            </details>
            <details>
                <summary><x-icon name="help" /><span>Quels cours puis-je envoyer ?</span></summary>
                <p>Uniquement des cours d'anglais : une leçon du cahier ou du livre, une fiche, un polycopié. {{ CourseFiles::offer() }}. Une leçon qui n'est pas en anglais est refusée poliment.</p>
            </details>
            <details>
                <summary><x-icon name="help" /><span>Faut-il un abonnement ?</span></summary>
                <p>Oui : « Mes cours » est compris dans chaque formule, sur le même temps par jour que la séance orale. Commencez par l'évaluation orale gratuite, puis choisissez une formule.</p>
            </details>
            <details>
                <summary><x-icon name="help" /><span>Mon enfant peut-il l'utiliser seul ?</span></summary>
                <p>Oui, depuis un téléphone avec un micro. Pour un jeune enfant, restez près de lui la première fois : vous verrez comment {{ $teacher }} explique, et vous pourrez lire avec lui le document de révision après la séance.</p>
            </details>
        </div>
        @php($faqHtml = ob_get_clean())
        {!! $faqHtml !!}
        @push('head')
            {!! Seo::jsonLd(Seo::faq($faqHtml)) !!}
        @endpush
        <p class="muted small next-step"><a href="{{ route('guides.index') }}">Lire nos guides gratuits pour progresser en anglais</a></p>
    </section>

    <x-guide-cta :title="'Commencez par l\'évaluation gratuite'" />
</x-layout>
