@php
    $teacher = config('avatar.model.name');
    $platform = config('platform.name');
@endphp

<p>Le téléphone est souvent le seul ordinateur que l'on a sous la main. Il peut devenir une vraie salle de classe, à une condition : qu'il vous fasse parler. Beaucoup d'applications font surtout toucher l'écran, choisir des mots et collectionner des points ; c'est utile pour le vocabulaire, mais cela n'apprend pas à prendre la parole.</p>

<h2>1. Ce qu'il vous faut</h2>
<ul>
    <li><strong>Un téléphone avec un micro</strong> qui marche, et un navigateur récent comme Chrome.</li>
    <li><strong>Une connexion internet</strong> correcte. Pour une séance orale avec {{ $teacher }}, comptez environ 3 à 4 mégaoctets par minute.</li>
    <li><strong>Un endroit calme</strong> : le bruit (télévision, rue, ventilateur) gêne la compréhension de votre voix.</li>
    <li><strong>Des écouteurs</strong>, si vous le pouvez : vous entendez mieux, et les autres ne vous entendent pas.</li>
</ul>

<h2>2. Choisir une méthode qui fait parler</h2>
<p>Posez-vous une question simple : combien de minutes ai-je parlé anglais aujourd'hui ? Si la réponse est « zéro », la méthode vous fait réviser, pas parler. Cherchez une méthode où vous répondez à voix haute, où l'on vous corrige, et où l'on vous fait redire la phrase.</p>

<h2>3. Organiser vos séances</h2>
<ol>
    <li>Choisissez un moment fixe dans la journée et réservez-le.</li>
    <li>Mettez le téléphone en mode « Ne pas déranger » pour ne pas être interrompu par les messages.</li>
    <li>Gardez l'application sur l'écran d'accueil pour l'ouvrir en un geste.</li>
    <li>Terminez chaque séance par une phrase à retenir, et redites-la le lendemain.</li>
</ol>

<h2>4. Utiliser les petits moments</h2>
<p>En dehors des séances, votre téléphone peut encore servir :</p>
<ul>
    <li>Écoutez une courte vidéo en anglais pendant un trajet, et répétez une phrase à voix basse.</li>
    <li>Changez la langue d'une application que vous connaissez bien : vous apprendrez des mots du quotidien.</li>
    <li>Enregistrez-vous en lisant cinq phrases, puis réécoutez-vous.</li>
</ul>
<ul class="phrases">
    <x-phrase en="Settings" fr="Réglages" />
    <x-phrase en="Send a message" fr="Envoyer un message" />
    <x-phrase en="Battery low" fr="Batterie faible" />
</ul>

<h2>5. Les pièges à éviter</h2>
<ul>
    <li>Ouvrir l'application sans objectif, et faire défiler au lieu de parler.</li>
    <li>Se fier seulement aux points et aux séries : ils motivent, mais ne disent pas si vous savez parler.</li>
    <li>Parler en chuchotant pour ne pas être entendu : votre voix doit être claire.</li>
</ul>

<h2>Avec {{ $platform }}</h2>
<p>{{ $platform }} fonctionne dans le navigateur, sans application à télécharger depuis un magasin : vous pouvez l'installer sur l'écran d'accueil. {{ $teacher }} vous parle, vous répondez à voix haute, il vous corrige et vous fait redire. Vous pouvez même <a href="{{ route('how') }}">voir une séance en vidéo</a> avant de commencer, puis passer l'évaluation orale gratuite.</p>
