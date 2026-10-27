@php($teacher = config('avatar.model.name'))

<p>Quand on part de zéro, on ne sait pas par où commencer : la grammaire ? Le vocabulaire ? Les applications ? Voici un chemin simple, pensé pour parler le plus tôt possible, sans vous décourager.</p>

<h2>1. Commencez par l'oral, pas par la grammaire</h2>
<p>Un enfant apprend à parler avant d'apprendre les règles. Vous n'êtes plus un enfant, mais le principe reste utile : apprenez d'abord des phrases entières que vous pouvez dire tout de suite, puis comprenez la règle qui se cache derrière. Une phrase comme « How much is it? » vous sert le jour même, sans connaître encore la règle des questions.</p>

<h2>2. Les premières choses à apprendre</h2>
<p>Dans cet ordre, chacune quelques jours :</p>
<ol>
    <li><strong>Saluer et se présenter</strong> : « Hello, my name is… », « I'm from… », « Nice to meet you. »</li>
    <li><strong>Les nombres et les prix</strong> : utiles au marché, au téléphone, pour l'heure.</li>
    <li><strong>Demander de l'aide</strong> : « Can you repeat, please? », « I don't understand. »</li>
    <li><strong>Le verbe « to be »</strong> (être) : « I am, you are, he is, she is, we are, they are ». Il est partout.</li>
    <li><strong>Le présent de tous les jours</strong> : « I work, I live, I like, I have ». Attention au « s » avec « he » et « she » : « she works ».</li>
    <li><strong>Les questions simples</strong> : « What…? Where…? When…? How much…? »</li>
</ol>
<ul class="phrases">
    <x-phrase en="I am a student. She is a nurse." fr="Je suis étudiant(e). Elle est infirmière." />
    <x-phrase en="I live in Lomé. My brother lives in Kara." fr="J'habite à Lomé. Mon frère habite à Kara." />
    <x-phrase en="Where do you work?" fr="Où travaillez-vous ?" />
</ul>

<h2>3. Peu de temps, mais chaque jour</h2>
<p>Quinze minutes par jour valent mieux que deux heures le samedi. Le cerveau retient mieux ce qu'il revoit souvent, à quelques jours d'intervalle. Choisissez un moment fixe (le matin avant le travail, le soir après le repas) et tenez-le, même quand vous n'avez pas envie.</p>

<h2>4. Parlez à voix haute dès le premier jour</h2>
<p>Lire et écouter ne suffisent pas : la bouche doit s'habituer aux sons anglais. Répétez les phrases à voix haute, même seul. Enregistrez-vous et réécoutez-vous. Au début, on se trouve ridicule ; c'est normal, et cela passe vite.</p>
<p class="tip"><strong>Astuce prononciation :</strong> le son « th » de « thank you » se fait en plaçant le bout de la langue entre les dents. Entraînez-vous lentement : « th… thank… thank you ».</p>

<h2>5. Acceptez les erreurs</h2>
<p>Un débutant fait des erreurs : c'est la preuve qu'il essaie. Ce qui compte, c'est d'être corrigé gentiment et de redire la phrase correctement. Une erreur corrigée et redite plusieurs fois devient une phrase juste.</p>

<h2>6. Ce qu'il vaut mieux éviter</h2>
<ul>
    <li>Apprendre de longues listes de mots sans jamais les dire dans une phrase.</li>
    <li>Passer d'une méthode à l'autre chaque semaine.</li>
    <li>Attendre d'« être prêt » pour parler : on devient prêt en parlant.</li>
    <li>Traduire chaque phrase mot à mot depuis le français (« I have 20 years » au lieu de « I'm 20 »).</li>
</ul>

<h2>7. Savoir où vous en êtes</h2>
<p>Les niveaux vont de A1 (débutant) à C2 (maîtrise). Connaître le vôtre évite de commencer trop haut, puis d'abandonner. L'évaluation orale gratuite de {{ config('platform.assessment.max_minutes') }} minutes avec {{ $teacher }} vous donne votre niveau, même si vous ne parlez presque pas : il commence lentement, et vous pouvez lui dire en français que vous ne comprenez pas. Un débutant complet est le bienvenu.</p>
