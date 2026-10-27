@php
    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
@endphp

<p>À l'école, on a souvent appris un anglais britannique ; dans les films, les chansons et sur internet, on entend surtout de l'anglais américain. Faut-il choisir ? Rassurez-vous : c'est la même langue. Les deux se comprennent sans difficulté, et les différences sont surtout dans quelques mots, l'orthographe et l'accent.</p>

<h2>1. Le vocabulaire</h2>
<p>Quelques mots du quotidien changent. Le premier mot est américain, le second britannique :</p>
<ul class="phrases">
    <x-phrase en="apartment / flat" fr="appartement" />
    <x-phrase en="elevator / lift" fr="ascenseur" />
    <x-phrase en="vacation / holiday" fr="vacances" />
    <x-phrase en="cell phone / mobile phone" fr="téléphone portable" />
    <x-phrase en="truck / lorry" fr="camion" />
    <x-phrase en="gas / petrol" fr="essence" />
    <x-phrase en="line / queue" fr="file d'attente" />
    <x-phrase en="fall / autumn" fr="automne" />
    <x-phrase en="soccer / football" fr="football" />
    <x-phrase en="trash / rubbish" fr="ordures" />
</ul>

<h2>2. L'orthographe</h2>
<p>L'anglais américain simplifie souvent l'écriture :</p>
<ul>
    <li><span lang="en-US">color</span> (américain), colour (britannique) : on retire le « u ». De même <span lang="en-US">favorite</span>, <span lang="en-US">neighbor</span>.</li>
    <li><span lang="en-US">center</span>, centre : la fin en « -er ». De même <span lang="en-US">theater</span>.</li>
    <li><span lang="en-US">organize</span>, organise : le « z » est plus fréquent en américain.</li>
    <li><span lang="en-US">traveled</span>, travelled : une seule consonne en américain.</li>
</ul>

<h2>3. Quelques tournures</h2>
<ul class="phrases">
    <x-phrase en="Do you have a pen? / Have you got a pen?" fr="As-tu un stylo ? (les deux se disent ; la première est plus américaine)" />
    <x-phrase en="on the weekend / at the weekend" fr="le week-end" />
    <x-phrase en="I just ate. / I've just eaten." fr="Je viens de manger." />
</ul>

<h2>4. La prononciation</h2>
<ul>
    <li><strong>Le « r »</strong> se prononce en américain, même en fin de mot : <span lang="en-US">car, water, teacher</span>.</li>
    <li><strong>Le « t » entre deux voyelles</strong> devient souvent plus doux, presque un « d » : <span lang="en-US">water, better, city</span>.</li>
    <li><strong>Certains mots</strong> changent de voyelle : <span lang="en-US">can't</span> se dit avec un « a » court en américain, et le « a » de <span lang="en-US">tomato</span> se dit « é ».</li>
</ul>

<h2>Lequel apprendre ?</h2>
<p>Les deux vous ouvrent les mêmes portes. Le plus important est d'en choisir un comme modèle pour votre prononciation, afin de parler de façon naturelle, et de reconnaître l'autre quand vous l'entendez. Mélanger les mots des deux n'est pas une faute : c'est seulement moins régulier.</p>
<p>{{ $platform }} a choisi l'anglais américain, compris partout dans le monde, et très présent dans les vidéos, la musique et sur internet. Avec {{ $teacher }}, une forme britannique n'est jamais comptée comme une faute : il vous donne simplement l'équivalent américain quand c'est utile.</p>
