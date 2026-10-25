// Appels de l'outil de fabrication à Talkami (API des vidéos, jeton secret) : une réponse 5xx passagère ou une
// coupure du réseau ne fait plus échouer une vidéo déjà fabriquée et déposée. Nouvel essai après 5, 15 puis
// 30 secondes ; la raison donnée par Talkami (champ « message », jamais le jeton) est gardée dans l'erreur.

const RETRY_DELAYS = [5000, 15000, 30000];

/** Message court d'une réponse refusée : « 503 (Jeton des vidéos non réglé) » ; jamais plus de 160 caractères. */
export async function describe(response) {
    let reason = '';

    try {
        const text = await response.text();
        reason = (() => {
            try {
                return String(JSON.parse(text)?.message ?? '');
            } catch {
                return text.replace(/<[^>]*>/g, ' ');
            }
        })().replace(/\s+/g, ' ').trim().slice(0, 160);
    } catch {}

    const path = (() => {
        try {
            return new URL(response.url).pathname;
        } catch {
            return '';
        }
    })();

    return `Talkami a répondu ${response.status}${reason ? ` (${reason})` : ''}${path ? ` pour ${path}` : ''}`;
}

/** Une erreur qui vaut la peine d'un nouvel essai : réseau coupé, 408, 429, ou toute erreur 5xx. */
export function retryable(status) {
    return status === 0 || status === 408 || status === 429 || status >= 500;
}

/**
 * Appel avec nouveaux essais. « acceptOnRetry » : statuts tenus pour une réussite à partir du deuxième essai
 * (« prête » renvoie 409 si le premier envoi a été pris en compte mais que sa réponse s'est perdue).
 */
export async function call(url, options = {}, { fetch: fetchFn = fetch, sleep = (ms) => new Promise((ok) => setTimeout(ok, ms)), delays = RETRY_DELAYS, acceptOnRetry = [], log = () => {} } = {}) {
    let lastError = null;

    for (let attempt = 0; attempt <= delays.length; attempt++) {
        if (attempt > 0) {
            log(`${lastError.message} : nouvel essai dans ${Math.round(delays[attempt - 1] / 1000)} secondes.`);
            await sleep(delays[attempt - 1]);
        }

        let response;

        try {
            response = await fetchFn(url, options);
        } catch (error) {
            lastError = new Error(`Talkami injoignable (${String(error?.message ?? error).slice(0, 120)})`);
            continue;
        }

        if (response.ok || (attempt > 0 && acceptOnRetry.includes(response.status))) {
            return response;
        }

        lastError = new Error(await describe(response));

        if (!retryable(response.status)) {
            throw lastError;
        }
    }

    throw lastError;
}
