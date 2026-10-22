/**
 * « Enregistrer ma séance » : la vidéo n'existe que dans la page tant qu'elle n'est pas téléchargée. Pendant un
 * enregistrement, ou quand une vidéo est prête et pas encore téléchargée, quitter la page demande confirmation :
 * - lien interne du site (menu, retour à l'espace, « La suite »…) : la fenêtre de confirmation du site ;
 * - tout le reste (fermer l'onglet, recharger, bouton retour, lien saisi) : la confirmation du navigateur
 *   (beforeunload, texte imposé par le navigateur).
 * Rien n'est retenu quand aucune vidéo n'est en jeu : l'écouteur beforeunload n'existe qu'à ce moment-là.
 */

/** Une vidéo serait perdue si la page était quittée maintenant ? */
export function videoAtStake({ recording = false, ready = false, downloaded = false } = {}) {
    return Boolean(recording) || (Boolean(ready) && !downloaded);
}

/**
 * Lien qui quitte la page pour une autre page du site (même origine) dans le même onglet. Jamais : un lien de
 * téléchargement, un nouvel onglet, une ancre de la même page, un lien externe (WhatsApp, téléphone, e-mail).
 */
export function leavesPage(link, here) {
    if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) {
        return false;
    }

    let url;

    try {
        url = new URL(link.getAttribute('href') ?? '', here.href);
    } catch {
        return false;
    }

    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== here.origin) {
        return false;
    }

    // Même page, seulement une autre ancre : on ne quitte rien.
    return !(url.pathname === here.pathname && url.search === here.search && url.hash !== '');
}

/** Clic ordinaire (pas un nouvel onglet ni une nouvelle fenêtre). */
export function plainClick(event) {
    return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
}

export class VideoGuard {
    /**
     * @param {{ win?: Window, ask: () => Promise<boolean|null> }} options ask : fenêtre de confirmation du site
     *   (true : quitter, false : rester, null : fenêtre indisponible, le navigateur demandera lui-même).
     */
    constructor({ win = window, ask }) {
        this.win = win;
        this.ask = ask;
        this.state = { recording: false, ready: false, downloaded: false };
        this.listening = false;
        this.released = false;
        this.onBeforeUnload = (event) => {
            if (!this.atStake()) {
                return;
            }

            event.preventDefault();
            // Anciennes versions de Chrome : il faut aussi donner une valeur (le texte est ignoré).
            event.returnValue = '';
        };
        this.onClick = (event) => this.click(event);
        // Après les écouteurs du site (ancres, installation) : un clic qu'ils ont déjà pris en charge est laissé.
        win.addEventListener('click', this.onClick);
    }

    atStake() {
        return !this.released && videoAtStake(this.state);
    }

    update(changes) {
        Object.assign(this.state, changes);
        this.released = false;
        const needed = this.atStake();

        if (needed && !this.listening) {
            this.win.addEventListener('beforeunload', this.onBeforeUnload);
        } else if (!needed && this.listening) {
            this.win.removeEventListener('beforeunload', this.onBeforeUnload);
        }

        this.listening = needed;
    }

    /** L'apprenant a choisi de partir : plus aucune question (pas de seconde confirmation du navigateur). */
    release() {
        this.released = true;

        if (this.listening) {
            this.win.removeEventListener('beforeunload', this.onBeforeUnload);
            this.listening = false;
        }
    }

    async click(event) {
        if (event.defaultPrevented || !plainClick(event) || !this.atStake()) {
            return;
        }

        const link = event.target?.closest?.('a[href]');

        if (!link || !leavesPage(link, this.win.location)) {
            return;
        }

        event.preventDefault();
        const answer = await this.ask();

        if (answer === false) {
            return;
        }

        // Fenêtre indisponible (null) : la navigation part et le navigateur pose sa propre question.
        if (answer === true) {
            this.release();
        }

        this.win.location.assign(link.href);
    }
}
