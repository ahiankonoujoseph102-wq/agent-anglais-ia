/*
 * Page d'administration « Essai des voix » : bouton « Écouter » (un extrait à la fois).
 */

const error = document.getElementById('voice-error');
let current = null;

document.querySelectorAll('[data-voice-sample]').forEach((button) => {
    const label = button.innerHTML;

    button.addEventListener('click', async () => {
        current?.pause();
        error.hidden = true;
        button.disabled = true;
        button.textContent = 'Chargement…';

        try {
            const response = await fetch(button.dataset.voiceSample, { credentials: 'same-origin', headers: { Accept: 'audio/wav, application/json' } });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'L\'extrait n\'a pas pu être chargé.');
            }

            current = new Audio(URL.createObjectURL(await response.blob()));
            await current.play();
        } catch (failure) {
            error.textContent = failure.message;
            error.hidden = false;
        } finally {
            button.disabled = false;
            button.innerHTML = label;
        }
    });
});
