/*
 * Envois longs de « Mes cours » (lecture d'une leçon, d'un PDF jusqu'à 50 pages ou d'une partie) : le formulaire
 * part en arrière-plan (fetch, réponse JSON { redirect }), pour qu'une limite du serveur ou du réseau ne montre
 * jamais une page d'erreur ni une attente sans fin. Formulaires marqués data-long-submit ; délai d'attente
 * data-timeout (secondes, sinon 100). En cas d'échec : message clair dans [data-long-submit-error], bouton de
 * nouveau utilisable, événement « long-submit:failed » sur le formulaire. Sans JavaScript, l'envoi reste ordinaire.
 * Testé par tests/js/long-submit.test.mjs.
 */

export const MESSAGES = {
    tooLarge: 'Votre fichier est trop lourd pour être envoyé : envoyez seulement les pages utiles.',
    timeout: 'La lecture prend plus de temps que prévu. Rien n\'a été compté : réessayez dans un instant, ou envoyez seulement les pages utiles.',
    network: 'La connexion a été coupée pendant l\'envoi. Vérifiez votre connexion internet, puis réessayez.',
    server: 'La lecture est momentanément indisponible. Rien n\'a été compté : réessayez dans quelques minutes.',
    expired: 'La page a expiré. Rechargez-la, puis réessayez.',
    tooMany: 'Vous avez envoyé beaucoup de demandes. Patientez une minute, puis réessayez.',
};

/**
 * Message à afficher pour une réponse qui n'est pas une réussite (null : pas d'erreur connue).
 */
export function messageFor(status, timedOut = false) {
    if (timedOut) {
        return MESSAGES.timeout;
    }

    if (status === 0) {
        return MESSAGES.network;
    }

    if (status === 413) {
        return MESSAGES.tooLarge;
    }

    if (status === 419) {
        return MESSAGES.expired;
    }

    if (status === 429) {
        return MESSAGES.tooMany;
    }

    if (status === 408 || status === 504 || status === 524) {
        return MESSAGES.timeout;
    }

    return status >= 500 ? MESSAGES.server : null;
}

/**
 * Premier message d'une réponse de validation Laravel ({ errors: { champ: [message] } }).
 */
export function firstError(data) {
    const errors = data?.errors && typeof data.errors === 'object' ? Object.values(data.errors) : [];
    const first = errors.find((list) => Array.isArray(list) && list.length > 0);

    return first ? String(first[0]) : (typeof data?.message === 'string' && data.message !== '' ? data.message : null);
}

/**
 * Envoie le formulaire et renvoie { redirect } ou { error } (jamais d'exception).
 */
export async function send(form, { timeoutMs = 100000, fetchImpl = globalThis.fetch, body = null } = {}) {
    const controller = new AbortController();
    let timedOut = false;
    const timer = setTimeout(() => {
        timedOut = true;
        controller.abort();
    }, timeoutMs);

    try {
        const response = await fetchImpl(form.action, {
            method: 'POST',
            body: body ?? new FormData(form),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        let data = null;

        try {
            data = await response.json();
        } catch {
            data = null;
        }

        if (response.ok && typeof data?.redirect === 'string') {
            return { redirect: data.redirect };
        }

        if (response.status === 422) {
            return { error: firstError(data) ?? MESSAGES.server };
        }

        return { error: messageFor(response.status) ?? firstError(data) ?? MESSAGES.server };
    } catch {
        return { error: messageFor(0, timedOut) };
    } finally {
        clearTimeout(timer);
    }
}

function attach(form) {
    const errorEl = form.querySelector('[data-long-submit-error]');
    const waiting = form.querySelector('[data-long-submit-waiting]');

    form.addEventListener('submit', async (event) => {
        // Envoi refusé par un autre script (aucune page choisie) ou déjà en cours.
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();

        if (form.dataset.busy) {
            return;
        }

        const button = event.submitter ?? form.querySelector('button[type="submit"]');
        form.dataset.busy = '1';

        if (errorEl) {
            errorEl.hidden = true;
        }

        if (button) {
            button.dataset.idleHtml ??= button.innerHTML;
            button.classList.add('is-busy');
            button.setAttribute('aria-disabled', 'true');
            button.innerHTML = `<span class="busy-spinner" aria-hidden="true"></span> ${form.dataset.busyLabel || 'Envoi en cours…'}`;
        }

        if (waiting) {
            waiting.hidden = false;
        }

        const result = await send(form, { timeoutMs: (Number(form.dataset.timeout) || 100) * 1000 });

        if (result.redirect) {
            window.location.assign(result.redirect);

            return;
        }

        delete form.dataset.busy;

        if (button) {
            button.classList.remove('is-busy');
            button.removeAttribute('aria-disabled');
            button.innerHTML = button.dataset.idleHtml;
        }

        if (waiting) {
            waiting.hidden = true;
        }

        if (errorEl) {
            errorEl.textContent = result.error;
            errorEl.hidden = false;
            errorEl.scrollIntoView?.({ block: 'center' });
        }

        form.dispatchEvent(new CustomEvent('long-submit:failed', { detail: result }));
    });
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('form[data-long-submit]').forEach(attach);
}
