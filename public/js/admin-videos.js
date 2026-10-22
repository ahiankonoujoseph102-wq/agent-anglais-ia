/*
 * Page « Vidéos » de l'administration : la case « Publiée » s'enregistre dès qu'on la touche (sans JavaScript,
 * le bouton « Enregistrer » reste là).
 */
document.querySelectorAll('[data-autosubmit]').forEach((box) => {
    box.form?.querySelector('[data-autosubmit-fallback]')?.setAttribute('hidden', '');
    box.addEventListener('change', () => box.form.requestSubmit());
});

/*
 * Essai comparatif des voix : « Écouter » demande l'extrait (préparé au premier toucher, quelques secondes), puis le
 * joue ; une voix qui échoue affiche son message sans gêner les autres.
 */
document.querySelectorAll('[data-voice-trial]').forEach((item) => {
    const button = item.querySelector('[data-voice-sample]');
    const audio = item.querySelector('[data-voice-audio]');
    const note = item.querySelector('[data-voice-note]');

    if (!button || !audio || !note) {
        return;
    }

    const say = (text) => {
        note.textContent = text;
        note.hidden = !text;
    };

    button.addEventListener('click', async () => {
        if (audio.src) {
            audio.play().catch(() => {});

            return;
        }

        button.setAttribute('aria-disabled', 'true');
        say('Préparation de la voix, quelques secondes…');

        try {
            const response = await fetch(button.dataset.voiceSample, { credentials: 'same-origin', headers: { Accept: 'audio/wav, application/json' } });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                say(data.message || 'Cette voix n\'a pas pu être préparée. Réessayez un peu plus tard.');

                return;
            }

            audio.src = URL.createObjectURL(await response.blob());
            audio.hidden = false;
            say('');
            audio.play().catch(() => {});
        } catch {
            say('Connexion impossible. Vérifiez votre connexion internet, puis réessayez.');
        } finally {
            button.removeAttribute('aria-disabled');
        }
    });
});
