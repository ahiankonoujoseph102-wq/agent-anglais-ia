/*
 * Petits comportements communs à toutes les pages (module ES, sans bibliothèque) :
 *   - service worker (cache des fichiers statiques, page hors connexion) ;
 *   - apparition douce des blocs .reveal quand ils arrivent à l'écran ;
 *   - bouton « Installer l'application » (Android) et explication pour iPhone ;
 *   - bouton « œil » des champs mot de passe (afficher / masquer) ;
 *   - message de confirmation en haut de la page (bouton « Fermer ») ;
 *   - fenêtre de confirmation intégrée des actions graves (jamais de fenêtre du navigateur) ;
 *   - navigation automatique : un lien vers une partie de la page y descend et met le focus sur son titre,
 *     une page revenue avec des erreurs met le focus sur le premier champ à corriger, un bouton d'envoi
 *     passe à « Envoi en cours… » et un second toucher est ignoré.
 */

const script = document.querySelector('script[data-sw]');

if ('serviceWorker' in navigator && script) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(script.dataset.sw, { scope: new URL('./', script.dataset.sw).pathname }).catch(() => {});
    });
}

/* ---------- Apparition des blocs ---------- */

const blocks = document.querySelectorAll('.reveal');

if (blocks.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px' });

    blocks.forEach((block) => observer.observe(block));
} else {
    blocks.forEach((block) => block.classList.add('is-visible'));
}

/* ---------- Installation de l'application ---------- */

const DISMISSED = 'install-dismissed';
const banner = document.getElementById('install');
const buttons = document.querySelectorAll('[data-install-button]');
const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
let deferredPrompt = null;

function dismissed() {
    try {
        return localStorage.getItem(DISMISSED) === '1';
    } catch {
        return false;
    }
}

function dismiss() {
    banner?.setAttribute('hidden', '');

    try {
        localStorage.setItem(DISMISSED, '1');
    } catch { /* stockage indisponible */ }
}

// Android (Chrome, Edge, Samsung Internet) : le navigateur propose l'installation.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    buttons.forEach((button) => button.removeAttribute('hidden'));

    if (banner && !dismissed()) {
        banner.removeAttribute('hidden');
    }
});

buttons.forEach((button) => button.addEventListener('click', async () => {
    if (!deferredPrompt) {
        return;
    }

    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    deferredPrompt = null;
    buttons.forEach((b) => b.setAttribute('hidden', ''));

    if (outcome === 'accepted') {
        banner?.setAttribute('hidden', '');
    }
}));

window.addEventListener('appinstalled', () => {
    buttons.forEach((button) => button.setAttribute('hidden', ''));
    banner?.setAttribute('hidden', '');
});

// iPhone et iPad (Safari) : pas d'invite automatique, on explique la marche à suivre.
const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

if (banner && ios && !standalone && !dismissed()) {
    banner.querySelector('[data-install-android]')?.setAttribute('hidden', '');
    banner.querySelector('[data-install-ios]')?.removeAttribute('hidden');
    banner.querySelector('[data-install-button]')?.setAttribute('hidden', '');
    banner.removeAttribute('hidden');
}

banner?.querySelector('[data-install-close]')?.addEventListener('click', dismiss);

/* ---------- Afficher / masquer le mot de passe ---------- */

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);

    if (!input) {
        return;
    }

    button.hidden = false;
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        const label = visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe';

        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.querySelector('[data-password-show]').toggleAttribute('hidden', visible);
        button.querySelector('[data-password-hide]').toggleAttribute('hidden', !visible);
        input.focus();
    });
});

// Champ remasqué à l'envoi : le navigateur ne garde pas le mot de passe dans l'historique des saisies.
document.addEventListener('submit', (event) => {
    event.target.querySelectorAll?.('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);

        if (input?.type === 'text') {
            button.click();
        }
    });
});

/* ---------- Message de confirmation en haut de la page ---------- */

// Il disparaît tout seul (animation CSS) ; le bouton permet de le fermer plus tôt.
document.querySelectorAll('[data-toast]').forEach((toast) => {
    const close = toast.querySelector('[data-toast-close]');
    close?.removeAttribute('hidden');
    close?.addEventListener('click', () => toast.remove());
    toast.addEventListener('animationend', (event) => {
        if (event.animationName === 'toast-out') {
            toast.remove();
        }
    });
});

/* ---------- Confirmation des actions graves ---------- */

let dialog = null;

function confirmationDialog() {
    if (dialog) {
        return dialog;
    }

    dialog = document.createElement('dialog');
    dialog.className = 'confirm-dialog';
    dialog.setAttribute('aria-labelledby', 'confirm-dialog-title');
    dialog.setAttribute('aria-describedby', 'confirm-dialog-message');
    dialog.innerHTML = '<form method="dialog" class="confirm-dialog-box">'
        + '<h2 id="confirm-dialog-title"></h2>'
        + '<p id="confirm-dialog-message"></p>'
        + '<div class="confirm-actions">'
        + '<button type="submit" value="confirm" class="btn btn-danger btn-block" data-confirm-ok></button>'
        + '<button type="submit" value="cancel" class="btn btn-light btn-block" autofocus>Annuler</button>'
        + '</div></form>';
    // Toucher en dehors de la fenêtre : annuler.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close('cancel');
        }
    });
    document.body.append(dialog);

    return dialog;
}

/**
 * Fenêtre de confirmation intégrée à la page, aux couleurs de la marque (Échap ou
 * « Annuler » : non). Renvoie true, false, ou null si le navigateur ne sait pas
 * afficher la fenêtre (le serveur affiche alors sa page de confirmation).
 * Aussi utilisée par la conversation vocale (« Terminer »).
 *
 * @param {{ title: string, message: string, confirm: string }} texts
 * @return {Promise<?boolean>}
 */
export function askConfirmation({ title, message, confirm }) {
    const box = confirmationDialog();

    if (typeof box.showModal !== 'function') {
        return Promise.resolve(null);
    }

    box.querySelector('#confirm-dialog-title').textContent = title;
    box.querySelector('#confirm-dialog-message').textContent = message;
    box.querySelector('[data-confirm-ok]').textContent = confirm || 'Confirmer';
    box.returnValue = '';
    box.showModal();

    return new Promise((resolve) => {
        box.addEventListener('close', () => resolve(box.returnValue === 'confirm'), { once: true });
    });
}

window.askConfirmation = askConfirmation;

/**
 * Message de confirmation : {plan} et {end} (offre d'un accès, voir App\Support\Confirmation) sont
 * remplis d'après les champs du formulaire ; la date de fin suit le fuseau du site.
 */
function filledMessage(form) {
    let message = form.dataset.confirmMessage || '';

    if (!message.includes('{plan}') && !message.includes('{end}')) {
        return message;
    }

    const select = form.querySelector('select[name="plan"]');
    const plan = select ? select.options[select.selectedIndex].text.split(' (')[0] : '';
    const duration = form.querySelector('input[name="duration"]:checked');
    const custom = Number(form.querySelector('input[name="custom_days"]')?.value || 0);
    const days = Math.max(1, Math.min(Number(form.dataset.confirmMaxDays) || 30, duration && duration.value !== 'custom' ? Number(duration.value) : custom || 1));
    const end = new Date(Date.now() + days * 86400000);
    let formatted = '';

    try {
        formatted = end.toLocaleString('fr-FR', {
            timeZone: form.dataset.confirmTimezone || undefined,
            day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
        });

        if (!formatted.includes(' à ')) {
            formatted = formatted.replace(/\s(\d{2}:\d{2})$/, ' à $1');
        }
    } catch {
        formatted = end.toLocaleDateString('fr-FR');
    }

    return message.replace('{plan}', plan).replace('{end}', formatted);
}

// Formulaires des actions graves (data-confirm, texte écrit par App\Support\Confirmation).
// Sans JavaScript, le même formulaire arrive sans confirmed=1 : page de confirmation du serveur.
document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) {
        return;
    }

    event.preventDefault();

    const answer = await askConfirmation({
        title: form.dataset.confirmTitle,
        message: filledMessage(form),
        confirm: form.dataset.confirmButton,
    });

    if (answer === false) {
        return;
    }

    if (answer === true) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'confirmed';
        input.value = '1';
        form.append(input);
    }

    form.dataset.confirmed = '1';
    markBusy(form, form.querySelector('button[type="submit"], button:not([type])'));
    form.submit();
});

// Retour arrière vers une page gardée en mémoire : chaque action grave redemande confirmation.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        delete form.dataset.confirmed;
        form.querySelectorAll('input[name="confirmed"]').forEach((input) => input.remove());
    });
});

/* ---------- Menu compact (téléphone, tablette) ---------- */

// Le menu est un <details> (il s'ouvre sans JavaScript) ; ici : fermeture en touchant
// à côté ou avec Échap, puis retour du clavier sur le bouton.
const menu = document.querySelector('[data-menu]');

if (menu) {
    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
}

/* ---------- Navigation automatique ---------- */

const reduceMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

// Ouvre les blocs repliés (<details>) qui contiennent l'élément : sinon il resterait invisible.
function reveal(element) {
    for (let parent = element.parentElement; parent; parent = parent.parentElement) {
        if (parent.tagName === 'DETAILS' && !parent.open) {
            parent.open = true;
        }
    }
}

// Le titre d'une partie : la cible si c'est un titre, sinon le titre qui la nomme (aria-labelledby), sinon son premier titre.
function headingOf(target) {
    if (/^H[1-6]$/.test(target.tagName)) {
        return target;
    }

    const labelledBy = target.getAttribute('aria-labelledby')?.split(' ')[0];

    return (labelledBy && document.getElementById(labelledBy)) || target.querySelector('h1, h2, h3') || target;
}

function focusWithoutScroll(element) {
    if (!element.matches('a[href], button, input, select, textarea, summary, [tabindex]')) {
        element.setAttribute('tabindex', '-1');
    }

    element.focus({ preventScroll: true });
}

/**
 * Descend jusqu'à une partie de la page (doucement, ou d'un saut si le téléphone demande moins
 * d'animations) et met le focus sur son titre.
 */
export function goTo(target) {
    if (!target) {
        return;
    }

    reveal(target);

    if (target.tagName === 'DETAILS') {
        target.open = true;
    }

    target.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });
    focusWithoutScroll(headingOf(target));
}

window.goTo = goTo;

// Lien vers une partie de la même page (sans JavaScript, le navigateur suit l'ancre de lui-même).
document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href*="#"]');

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target) {
        return;
    }

    const url = new URL(link.href, location.href);

    if (url.origin !== location.origin || url.pathname !== location.pathname || url.search !== location.search || url.hash.length < 2) {
        return;
    }

    const target = document.getElementById(decodeURIComponent(url.hash.slice(1)));

    if (!target) {
        return;
    }

    event.preventDefault();
    history.pushState(null, '', url.hash);
    goTo(target);
});

// L'ancre change sans recharger la page (lien d'une autre partie du site, retour arrière) : même traitement.
window.addEventListener('hashchange', () => {
    const target = location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;

    if (target && !target.matches('[data-pay-methods-for], [data-pay-block], .pay-section')) {
        goTo(target);
    }
});

// À l'arrivée : d'abord le premier champ à corriger, sinon la partie demandée par l'adresse (#…).
function firstInvalidField() {
    const main = document.getElementById('contenu') || document.body;
    const marked = main.querySelector('[aria-invalid="true"], .is-invalid');

    if (marked) {
        return marked;
    }

    const message = main.querySelector('.error');

    return message?.closest('.field, fieldset, .option-group, form')?.querySelector('input:not([type="hidden"]), select, textarea') ?? null;
}

const invalid = firstInvalidField();

if (invalid) {
    reveal(invalid);
    invalid.focus({ preventScroll: true });
    // Page qui vient de s'ouvrir : saut direct (« instant » passe avant le défilement doux de la feuille de style).
    invalid.scrollIntoView({ behavior: 'instant', block: 'center' });
} else if (location.hash.length > 1) {
    const target = document.getElementById(decodeURIComponent(location.hash.slice(1)));

    if (target && !target.matches('[data-pay-methods-for], [data-pay-block], .pay-section')) {
        // Le navigateur a déjà fait descendre la page : on ouvre ce qui est replié et on donne le focus au titre.
        reveal(target);
        target.scrollIntoView({ behavior: 'instant', block: 'start' });
        focusWithoutScroll(headingOf(target));
    }
}

// Un choix qui demande une suite (data-then="id") : le curseur va directement dans le champ suivant.
document.addEventListener('change', (event) => {
    const choice = event.target;
    const next = choice.dataset?.then && choice.checked ? document.getElementById(choice.dataset.then) : null;

    if (next) {
        next.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'center' });
        next.focus({ preventScroll: true });
    }
});

/* ---------- Boutons d'envoi : « Envoi en cours… » et aucun double envoi ---------- */

const BUSY_TIMEOUT = 30000;

function markBusy(form, button) {
    form.dataset.busy = '1';

    if (button && !button.classList.contains('is-busy')) {
        button.dataset.idleHtml = button.innerHTML;
        button.classList.add('is-busy');
        button.setAttribute('aria-disabled', 'true');
        button.innerHTML = '<span class="busy-spinner" aria-hidden="true"></span> ' + (button.dataset.busyLabel || 'Envoi en cours…');
    }

    // Si la page ne change pas (réseau coupé), le bouton redevient utilisable.
    setTimeout(() => unmarkBusy(form), BUSY_TIMEOUT);
}

function unmarkBusy(form) {
    delete form.dataset.busy;
    form.querySelectorAll('.is-busy').forEach((button) => {
        button.classList.remove('is-busy');
        button.removeAttribute('aria-disabled');

        if (button.dataset.idleHtml !== undefined) {
            button.innerHTML = button.dataset.idleHtml;
            delete button.dataset.idleHtml;
        }
    });
}

// Écouté en dernier : un envoi déjà pris en charge (confirmation, compression d'une photo) est ignoré ici.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') {
        return;
    }

    if (form.dataset.busy) {
        event.preventDefault();

        return;
    }

    if (event.defaultPrevented) {
        return;
    }

    markBusy(form, event.submitter ?? form.querySelector('button[type="submit"], button:not([type])'));
});

// Retour arrière vers une page gardée en mémoire : les boutons sont de nouveau utilisables.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('form[data-busy]').forEach(unmarkBusy);
    }
});
