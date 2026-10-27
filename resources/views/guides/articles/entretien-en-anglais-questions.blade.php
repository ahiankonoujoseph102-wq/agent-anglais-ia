@php($teacher = config('avatar.model.name'))

<p>Entretien d'embauche, de stage, de bourse ou d'admission : les questions changent peu d'un jury à l'autre. Les connaître à l'avance, et surtout s'entraîner à y répondre à voix haute, change tout le jour J. Voici dix questions fréquentes, avec une façon simple d'y répondre en anglais américain. Adaptez chaque réponse à votre histoire : un jury entend vite une réponse apprise par cœur.</p>

<h2>1. « Tell me about yourself. »</h2>
<p>Parlez-moi de vous. Ce n'est pas votre vie entière : en trente secondes à une minute, dites qui vous êtes, ce que vous faites et pourquoi vous êtes là.</p>
<ul class="phrases">
    <x-phrase en="I'm Kafui, I'm 23, and I just finished a degree in accounting at the University of Lomé. During my studies, I worked part-time in a small company, where I learned to manage invoices. Today, I'm looking for an internship to grow in a larger team." fr="Je m'appelle Kafui, j'ai 23 ans et je viens de finir une licence de comptabilité à l'université de Lomé. Pendant mes études, j'ai travaillé à temps partiel dans une petite entreprise, où j'ai appris à gérer les factures. Aujourd'hui, je cherche un stage pour progresser dans une équipe plus grande." />
</ul>

<h2>2. « Why do you want this job? » / « Why do you want this scholarship? »</h2>
<p>Pourquoi ce poste, ou cette bourse ? Reliez votre projet à ce qu'on vous propose.</p>
<ul class="phrases">
    <x-phrase en="I want this scholarship because it will help me study public health, and I want to use those skills to improve health care in my community." fr="Je veux cette bourse parce qu'elle m'aidera à étudier la santé publique, et je veux utiliser ces compétences pour améliorer les soins dans ma communauté." />
</ul>

<h2>3. « What are your strengths? »</h2>
<p>Quelles sont vos qualités ? Donnez-en deux, chacune avec un exemple réel.</p>
<ul class="phrases">
    <x-phrase en="I'm organized and reliable. For example, I managed the schedule of our student association for two years." fr="Je suis organisé(e) et fiable. Par exemple, j'ai géré l'emploi du temps de notre association d'étudiants pendant deux ans." />
</ul>

<h2>4. « What is your greatest weakness? »</h2>
<p>Quel est votre plus grand défaut ? Choisissez un vrai point à améliorer, et dites ce que vous faites pour progresser.</p>
<ul class="phrases">
    <x-phrase en="I used to be shy when speaking in public. I'm working on it: I now practice my presentations out loud before each meeting." fr="J'étais timide pour parler en public. J'y travaille : maintenant, je répète mes présentations à voix haute avant chaque réunion." />
</ul>

<h2>5. « Why should we choose you? »</h2>
<p>Pourquoi vous choisir ? Résumez ce que vous apportez, sans critiquer les autres candidats.</p>
<ul class="phrases">
    <x-phrase en="I have the skills you need, I learn quickly, and I really want to contribute to your project." fr="J'ai les compétences dont vous avez besoin, j'apprends vite, et je veux vraiment contribuer à votre projet." />
</ul>

<h2>6. « Tell me about a challenge you faced. »</h2>
<p>Parlez d'une difficulté que vous avez surmontée. Une méthode simple : la situation, ce que vous avez fait, le résultat.</p>
<ul class="phrases">
    <x-phrase en="Last year, our team was late on a project. I suggested a weekly plan, and we finished on time." fr="L'an dernier, notre équipe était en retard sur un projet. J'ai proposé un planning par semaine, et nous avons fini à temps." />
</ul>

<h2>7. « Where do you see yourself in five years? »</h2>
<p>Où vous voyez-vous dans cinq ans ? Montrez un projet réaliste, en lien avec le poste ou les études.</p>
<ul class="phrases">
    <x-phrase en="In five years, I see myself leading a small team and using the experience I gained here." fr="Dans cinq ans, je me vois diriger une petite équipe, grâce à l'expérience acquise ici." />
</ul>

<h2>8. « Why do you want to study abroad? »</h2>
<p>Pourquoi étudier à l'étranger ? Parlez de ce que vous voulez apprendre, et de ce que vous ferez ensuite.</p>
<ul class="phrases">
    <x-phrase en="I want to study abroad to learn new methods in agriculture, and then bring that knowledge back home." fr="Je veux étudier à l'étranger pour apprendre de nouvelles méthodes en agriculture, puis rapporter ce savoir chez moi." />
</ul>

<h2>9. « What do you know about us? »</h2>
<p>Que savez-vous de nous ? Renseignez-vous avant l'entretien et citez un ou deux faits précis sur l'entreprise ou l'université.</p>
<ul class="phrases">
    <x-phrase en="I know that your company works with clients in many countries, and that you are developing your logistics team." fr="Je sais que votre entreprise travaille avec des clients de nombreux pays, et que vous développez votre équipe logistique." />
</ul>

<h2>10. « Do you have any questions for us? »</h2>
<p>Avez-vous des questions ? Répondez toujours oui : c'est une preuve d'intérêt.</p>
<ul class="phrases">
    <x-phrase en="Yes. What would a typical day look like in this position?" fr="Oui. À quoi ressemblerait une journée type à ce poste ?" />
</ul>

<h2>Les phrases qui sauvent</h2>
<p>Ne pas comprendre une question n'est pas grave. Ne rien dire, si. Gardez ces phrases prêtes :</p>
<ul class="phrases">
    <x-phrase en="Could you repeat the question, please?" fr="Pourriez-vous répéter la question, s'il vous plaît ?" />
    <x-phrase en="That's a good question. Let me think for a moment." fr="C'est une bonne question. Laissez-moi réfléchir un instant." />
    <x-phrase en="Do you mean…?" fr="Voulez-vous dire… ?" />
</ul>

<h2>Comment s'entraîner</h2>
<p>Écrivez vos réponses, mais ne les apprenez pas mot à mot : retenez les idées, puis répondez à voix haute, comme devant le jury. Le mieux est de vous faire poser les questions par quelqu'un qui joue le jury et vous corrige. Avec l'objectif « Préparer un entretien », {{ $teacher }} peut jouer le jury ou le recruteur pendant vos séances : <a href="{{ route('interview') }}">voir comment se préparer avec {{ $teacher }}</a>. Aucun entraînement ne peut promettre une bourse ou une embauche, mais arriver préparé aide à montrer ce que vous valez.</p>
