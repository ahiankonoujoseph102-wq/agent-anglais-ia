/*
 * Petits comportements communs à toutes les pages (module ES, sans bibliothèque) :
 *   - service worker (cache des fichiers statiques, page hors connexion) ;
 *   - apparition douce des blocs .reveal quand ils arrivent à l'écran ;
 *   - bouton « Installer l'application » (Android) et explication pour iPhone ;
 *   - bouton « œil » des champs mot de passe (afficher / masquer).
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
