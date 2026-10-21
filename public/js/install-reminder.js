/**
 * Calendrier de la proposition d'installer l'application (bandeau de public/js/app.js).
 *
 * Tant que l'élève ne l'a jamais fermée, elle s'affiche. Fermée : elle revient 1 jour après, puis 3 jours après,
 * puis une fois par semaine ; jamais à chaque visite. Application installée : plus jamais.
 * État gardé dans le navigateur (localStorage, clé STORAGE_KEY) : { count: fermetures, last: date de la
 * dernière fermeture (millisecondes), installed }.
 */

export const STORAGE_KEY = 'install-reminder';
// Ancienne clé (« fermée pour de bon ») : reprise comme une première fermeture, la proposition revient demain.
export const LEGACY_KEY = 'install-dismissed';
export const DELAYS_DAYS = [1, 3, 7];

const DAY = 24 * 60 * 60 * 1000;

/** Jours d'attente après la n-ième fermeture (1 : un jour, 2 : trois jours, ensuite une semaine). */
export function delayDays(count) {
    return count <= 0 ? 0 : DELAYS_DAYS[Math.min(count, DELAYS_DAYS.length) - 1];
}

/** La proposition peut-elle s'afficher maintenant ? */
export function isDue(state, now) {
    if (!state || !state.count) {
        return !state?.installed;
    }

    if (state.installed) {
        return false;
    }

    return now >= state.last + delayDays(state.count) * DAY;
}

/** Date (millisecondes) à laquelle la proposition revient, ou null. */
export function nextShowAt(state) {
    return state?.count && !state.installed ? state.last + delayDays(state.count) * DAY : null;
}

/** Nouvel état après une fermeture. */
export function dismiss(state, now) {
    return { count: (state?.count ?? 0) + 1, last: now };
}

/**
 * Lit l'état. Stockage indisponible (navigation privée, données bloquées) : undefined, et la proposition ne
 * s'affiche pas (sinon elle reviendrait à chaque visite).
 */
export function read(storage, now) {
    try {
        const raw = storage.getItem(STORAGE_KEY);

        if (raw) {
            const state = JSON.parse(raw);

            return state && typeof state === 'object' ? state : {};
        }

        if (storage.getItem(LEGACY_KEY) === '1') {
            const state = dismiss(null, now);
            write(storage, state);
            storage.removeItem(LEGACY_KEY);

            return state;
        }

        return {};
    } catch {
        return undefined;
    }
}

export function write(storage, state) {
    try {
        storage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch { /* stockage indisponible */ }
}
