{{--
    « Comment partager ma vidéo » : trois étapes, une fois la vidéo de « Enregistrer ma séance » téléchargée.
    Replié (une ligne), même style que les phrases utiles. Rien n'est envoyé à la plateforme : c'est l'apprenant qui
    partage, s'il le souhaite.
--}}
@props(['id' => 'partager-ma-video'])

<details {{ $attributes->merge(['class' => 'useful-phrases recording-share']) }}>
    {{-- L'ancre est sur le titre : un lien vers elle ouvre le bloc (goTo de public/js/app.js ouvre les <details> parents). --}}
    <summary id="{{ $id }}" class="useful-head">
        <span class="useful-label"><x-icon name="share" /> Comment partager ma vidéo</span>
    </summary>
    <div class="useful-body">
        <ol class="recording-steps">
            <li>À la fin de la séance, touchez « Télécharger ma vidéo », avant de quitter la page.</li>
            <li>Ouvrez l'application Fichiers de votre téléphone, puis le dossier « Téléchargements » : la vidéo commence par « {{ \Illuminate\Support\Str::slug(config('platform.name')) }} » et porte la date de la séance.</li>
            <li>Ouvrez-la, touchez l'icône de partage, puis choisissez WhatsApp pour l'envoyer à vos proches, ou TikTok pour la publier.</li>
        </ol>
        <p class="useful-intro">C'est vous qui décidez : la vidéo reste sur votre téléphone, {{ config('platform.name') }} ne la reçoit jamais.</p>
    </div>
</details>
