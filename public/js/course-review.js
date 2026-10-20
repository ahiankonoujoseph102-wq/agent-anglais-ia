/*
 * Documents de révision « en préparation » (components/course-review) : la page se met à jour toute seule. L'état est
 * demandé de temps en temps (jamais plus d'une fois toutes les 15 secondes, une demi-heure au plus) ; dès qu'il change,
 * le bon bloc est montré (téléchargement, ou « Réessayer »). Sans JavaScript, recharger la page suffit.
 */

export const POLL_MS = 15000;
export const MAX_POLLS = 120;

/** Montre le bloc de l'état reçu, cache les autres. */
export function show(root, state) {
    root.dataset.reviewState = state;
    root.querySelectorAll('[data-review-when]').forEach((block) => {
        block.hidden = block.dataset.reviewWhen !== state;
    });
}

export function watch(root, { fetchImpl = fetch, setTimer = setTimeout, interval = POLL_MS, max = MAX_POLLS } = {}) {
    let polls = 0;

    const tick = async () => {
        polls += 1;

        try {
            const response = await fetchImpl(root.dataset.reviewStatus, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (response.ok) {
                const { state } = await response.json();

                if (state && state !== 'pending') {
                    show(root, state === 'skipped' ? 'failed' : state);

                    return;
                }
            }
        } catch {
            // Réseau coupé : on réessaie au prochain tour.
        }

        if (polls < max) {
            setTimer(tick, interval);
        }
    };

    setTimer(tick, interval);
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-review-status]').forEach((root) => watch(root));
}
