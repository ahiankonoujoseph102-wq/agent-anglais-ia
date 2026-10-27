{{--
    Page d'arrivée « Préparer un entretien en anglais » (publique, indexée) : le mode entretien de la séance du jour
    (objectif « Préparer un entretien » du questionnaire, config/teacher.php → goal_focus.interview) et les exposés.
    Interdits : promettre une bourse, une admission, un visa, un emploi ou un résultat ; nommer un examen officiel.
    Questions affichées = données structurées FAQPage.
--}}
@php
    use App\Enums\Goal;
    use App\Support\Navigation;
    use App\Support\Plan;
    use App\Support\Seo;

    $teacher = config('avatar.model.name');
    $minutes = config('platform.assessment.max_minutes');
    $daily = collect(Plan::all())->pluck('dailyMinutes')->unique()->sort()->values();
@endphp

<x-layout title="Préparer un entretien en anglais, à l'oral"
          :description="'Entraînez-vous à l\'oral pour un entretien en anglais : bourse, admission, stage, embauche. '.$teacher.' joue le jury et vous aide à mieux répondre.'">
    <section class="section page-hero" aria-labelledby="entretien-titre">
        <div class="students reveal">
            <figure class="photo-frame"><x-photo name="etudiants-bibliotheque" /></figure>
            <div>
                <span class="eyebrow"><x-icon name="work" /> Bourse, admission, stage, embauche</span>
                <h1 id="entretien-titre">Préparer un entretien en anglais avec {{ $teacher }}</h1>
                <p class="lead">Le jour de l'entretien, il faut comprendre la question et répondre sans bloquer. Cela se prépare à voix haute : {{ $teacher }} joue le jury ou le recruteur, vous répondez, il vous corrige et vous aide à mieux dire.</p>
                <div class="actions actions-row">
                    <a class="btn btn-accent btn-lg btn-block" href="{{ Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
                    <a class="btn btn-outline btn-block" href="{{ route('guides.show', 'entretien-en-anglais-questions') }}">Lire les 10 questions fréquentes</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="entretien-comment">
        <div class="section-head reveal">
            <h2 id="entretien-comment">Comment on s'entraîne</h2>
        </div>
        <ol class="how-steps">
            <li>Passez l'évaluation orale gratuite : {{ $minutes }} minutes avec {{ $teacher }} pour connaître votre niveau, de A1 à C2.</li>
            <li>Choisissez une formule, puis, dans le questionnaire, l'objectif « {{ Goal::Interview->label() }} » (ou « {{ Goal::Presentation->label() }} »).</li>
            <li>Chaque séance commence par la leçon du jour, à votre niveau. Pendant la pratique, {{ $teacher }} peut jouer l'examinateur qui vous correspond : jury de bourse ou d'admission, recruteur pour un stage ou un emploi.</li>
            <li>Il pose une question à la fois et attend votre réponse complète. Ensuite, il vous dit ce qui était bien, corrige l'erreur la plus utile, propose une meilleure façon de répondre en anglais américain, et vous la fait redire.</li>
        </ol>
        <p class="muted small">Le français sert seulement à expliquer. Vous pouvez demander de répéter ou de ralentir à tout moment.</p>
    </section>

    <section class="section" aria-labelledby="entretien-exemples">
        <div class="section-head reveal">
            <h2 id="entretien-exemples">Des questions qui reviennent souvent</h2>
        </div>
        <ul class="phrases">
            <x-phrase en="Tell me about yourself." fr="Parlez-moi de vous." />
            <x-phrase en="Why do you want this scholarship?" fr="Pourquoi voulez-vous cette bourse ?" />
            <x-phrase en="Why should we choose you?" fr="Pourquoi devrions-nous vous choisir ?" />
            <x-phrase en="What are your strengths?" fr="Quelles sont vos qualités ?" />
            <x-phrase en="Where do you see yourself in five years?" fr="Où vous voyez-vous dans cinq ans ?" />
        </ul>
        <p>Pour chacune, notre guide propose une façon simple de répondre : <a href="{{ route('guides.show', 'entretien-en-anglais-questions') }}">Entretien en anglais : 10 questions et comment y répondre</a>.</p>
    </section>

    <section class="section" aria-labelledby="entretien-questions">
        <div class="section-head reveal">
            <h2 id="entretien-questions">Vos questions</h2>
        </div>
        @php(ob_start())
        <div class="faq reveal">
            <details>
                <summary><x-icon name="help" /><span>Est-ce que cela m'assure une bourse, un visa ou une embauche ?</span></summary>
                <p>Non, et personne ne peut le promettre honnêtement. {{ $teacher }} vous aide à comprendre les questions, à répondre plus clairement et à parler avec plus d'assurance. La décision appartient au jury ou au recruteur.</p>
            </details>
            <details>
                <summary><x-icon name="help" /><span>Et si mon niveau est faible ?</span></summary>
                <p>L'entraînement suit votre niveau : des réponses courtes et simples d'abord, puis plus détaillées. Le programme commence au niveau trouvé par l'évaluation gratuite.</p>
            </details>
            <details>
                <summary><x-icon name="help" /><span>Puis-je préparer un exposé ou une soutenance ?</span></summary>
                <p>Oui, avec l'objectif « {{ Goal::Presentation->label() }} » : vous présentez pendant une minute, {{ $teacher }} vous corrige, puis vous aide à le redire plus clairement et à répondre aux questions d'un jury.</p>
            </details>
            <details>
                <summary><x-icon name="help" /><span>Combien de temps par jour ?</span></summary>
                <p>Selon la formule : {{ $daily->map(fn ($value) => $value.' minutes')->join(', ', ' ou ') }} par jour, à partager entre la séance orale et « Mes cours ». Le site arrête la séance à l'heure.</p>
            </details>
        </div>
        @php($faqHtml = ob_get_clean())
        {!! $faqHtml !!}
        @push('head')
            {!! Seo::jsonLd(Seo::faq($faqHtml)) !!}
        @endpush
        <p class="muted small next-step"><a href="{{ route('plans') }}">Voir les formules et les prix</a></p>
    </section>

    <x-guide-cta />
</x-layout>
