@php($teacher = config('avatar.model.name'))

<p>Vous connaissez des mots, vous comprenez quand on vous parle lentement, mais au moment de répondre, plus rien ne sort. Ce blocage est très courant, et il ne vient pas d'un manque de connaissances : il vient de la peur de mal dire. La bonne nouvelle, c'est qu'elle se travaille, pas à pas.</p>

<h2>1. Changer d'objectif : être compris, pas parfait</h2>
<p>Parler couramment ne veut pas dire parler sans erreur. Même les personnes qui parlent très bien anglais en font. L'objectif est de faire passer votre message. Une phrase simple et comprise vaut mieux qu'une phrase parfaite qu'on n'ose pas dire.</p>

<h2>2. Préparer des phrases de secours</h2>
<p>Ce qui fait peur, c'est le silence. Ces phrases vous donnent du temps et gardent la conversation vivante :</p>
<ul class="phrases">
    <x-phrase en="Let me think." fr="Laissez-moi réfléchir." />
    <x-phrase en="How can I say this…" fr="Comment dire…" />
    <x-phrase en="I don't know the word in English, but it's like…" fr="Je ne connais pas le mot en anglais, mais c'est comme…" />
    <x-phrase en="Sorry, can you say that again?" fr="Pardon, pouvez-vous le redire ?" />
    <x-phrase en="What I mean is…" fr="Ce que je veux dire, c'est…" />
</ul>

<h2>3. Contourner le mot qui manque</h2>
<p>Vous ne trouvez pas un mot ? Décrivez-le avec des mots simples. « The thing you use to open a door » (la chose qui sert à ouvrir une porte) veut dire « key ». Votre interlocuteur vous donnera souvent le mot, et vous l'apprendrez pour de bon.</p>

<h2>4. Commencer court, puis allonger</h2>
<p>Ne cherchez pas à faire de longues phrases tout de suite. Une idée par phrase. Puis ajoutez un détail avec « because », « and », « but » :</p>
<ul class="phrases">
    <x-phrase en="I like my job." fr="J'aime mon travail." />
    <x-phrase en="I like my job because I meet many people." fr="J'aime mon travail parce que je rencontre beaucoup de gens." />
    <x-phrase en="I like my job because I meet many people, but the days are long." fr="J'aime mon travail parce que je rencontre beaucoup de gens, mais les journées sont longues." />
</ul>

<h2>5. S'entraîner dans un cadre sans jugement</h2>
<p>On ose parler quand on ne craint pas le regard des autres. Commencez là où l'erreur n'a pas de conséquence : seul à voix haute, avec un ami, ou avec un professeur patient. Quand vous avez pratiqué une situation plusieurs fois, la vraie conversation fait beaucoup moins peur.</p>

<h2>6. Garder une trace de vos progrès</h2>
<p>Enregistrez-vous une minute aujourd'hui, en parlant de votre journée. Recommencez dans un mois. Vous entendrez la différence, et c'est la meilleure motivation.</p>

<h2>Avec {{ $teacher }}</h2>
<p>{{ $teacher }} vous laisse le temps de répondre, accepte que vous lui posiez vos questions en français, et ne se moque jamais. Il vous corrige une ou deux erreurs à la fois, celles qui comptent le plus, puis vous fait redire la phrase. Si vous voulez d'abord gagner en confiance, choisissez l'objectif « Avoir confiance et oser parler » : il commence par des questions faciles et vous fait parler un peu plus à chaque fois.</p>
