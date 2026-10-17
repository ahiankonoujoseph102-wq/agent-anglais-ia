// Page de résultat de l'évaluation : message vocal de Teacher Joe, demandé au serveur
// seulement quand l'apprenant touche « Écouter » (jamais de lecture automatique).
const box = document.querySelector('[data-level-message]');

if (box) {
    const button = box.querySelector('[data-level-play]');
    const audio = box.querySelector('[data-level-audio]');
    const status = box.querySelector('[data-level-status]');
    const playLabel = box.querySelector('[data-play-label]');
    const pauseLabel = box.querySelector('[data-pause-label]');

    const show = (playing) => {
        button.setAttribute('aria-pressed', String(playing));
        playLabel.hidden = playing;
        pauseLabel.hidden = !playing;
    };
    const say = (text) => {
        status.textContent = text;
        status.hidden = !text;
    };

    audio.addEventListener('playing', () => { say(''); show(true); });
    audio.addEventListener('pause', () => show(false));
    audio.addEventListener('ended', () => show(false));
    audio.addEventListener('error', () => {
        show(false);
        button.disabled = false;
        say('Le message vocal n\'est pas disponible pour le moment. Vous pouvez le lire juste en dessous.');
    });

    button.addEventListener('click', () => {
        if (!audio.paused) {
            audio.pause();

            return;
        }

        // Premier toucher : le fichier est demandé maintenant (il peut être préparé à la demande).
        if (!audio.getAttribute('src')) {
            audio.src = audio.dataset.src;
            say('Préparation du message…');
        }

        audio.play().catch(() => {});
    });
}
