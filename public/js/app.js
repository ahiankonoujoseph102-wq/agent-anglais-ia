/*
 * Petits comportements communs à toutes les pages (module ES, sans bibliothèque) :
 *   - service worker (cache des fichiers statiques, page hors connexion) ;
 *   - apparition douce des blocs .reveal quand ils arrivent à l'écran ;
 *   - bouton « Installer l'application » (Android) et explication pour iPhone ;
 *   - bouton « œil » des champs mot de passe (afficher / masquer) ;
 *   - message de confirmation en haut de la page (bouton « Fermer ») ;
 *   - fenêtre de confirmation intégrée des actions graves (jamais de fenêtre du navigateur).
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
        message: form.dataset.confirmMessage,
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
    form.submit();
});

// Retour arrière vers une page gardée en mémoire : chaque action grave redemande confirmation.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        delete form.dataset.confirmed;
        form.querySelectorAll('input[name="confirmed"]').forEach((input) => input.remove());
    });
});
