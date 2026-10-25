/*
 * Paiement (page /abonnement/{formule}) : faire tenir une partie de la page dans la zone VISIBLE de l'écran, c'est-à-dire
 * sous l'en-tête et au-dessus de la barre du bas (et de tout autre élément fixé en bas). Utilisé par payment-flow.js
 * (bloc de paiement, retour depuis l'application Mobile Money) et proof-upload.js (aperçu et « Envoyer ma capture »).
 * Aucun effet à l'import : les calculs sont testés par tests/js/pay-view.test.mjs.
 */

// Hauteur gardée sous l'en-tête fixe (la même que scroll-margin-top des blocs du paiement).
export const TOP_OFFSET = 84;
// Marge sous le bouton, pour qu'il ne touche pas le bord de la zone visible.
export const BOTTOM_GAP = 16;
// Retour depuis l'application Mobile Money : au-delà, l'état gardé est oublié.
export const AWAY_MAX_MS = 2 * 60 * 60 * 1000;
export const AWAY_KEY = 'pay-away';

/**
 * Position de défilement (en pixels depuis le haut de la page) qui montre la partie [top, bottom] : le haut de la
 * partie juste sous l'en-tête ; si le bas n'entre pas dans la zone visible, on descend juste assez pour que le bas
 * (le bouton) soit visible, quitte à cacher le haut.
 */
export function scrollTarget({ top, bottom, visibleBottom, offset = TOP_OFFSET, gap = BOTTOM_GAP }) {
    return Math.max(0, Math.round(Math.max(top - offset, bottom + gap - visibleBottom)));
}

/**
 * Bas de la zone visible : la hauteur de la fenêtre, moins les éléments fixés en bas de l'écran qui sont affichés
 * (barre du bas de l'apprenant, bandeau d'installation). `fixedTops` : haut de chacun de ces éléments.
 */
export function visibleBottomFrom(viewportHeight, fixedTops) {
    return fixedTops.filter((top) => top > viewportHeight / 2 && top < viewportHeight).reduce((min, top) => Math.min(min, top), viewportHeight);
}

/**
 * Faut-il ramener la personne à l'étape ③ ? Oui si elle a quitté cette page avec ce même moyen de paiement choisi,
 * il y a moins de `maxAge`, sans avoir encore choisi sa capture.
 */
export function shouldReturn(saved, { key, path, now, maxAge = AWAY_MAX_MS }) {
    return Boolean(saved && key && saved.key === key && saved.path === path && Number.isFinite(saved.at) && now - saved.at >= 0 && now - saved.at < maxAge);
}

/** État gardé dans le navigateur (jamais bloquant : stockage indisponible = rien de gardé). */
export function readAway(storage) {
    try {
        const value = JSON.parse(storage?.getItem(AWAY_KEY) ?? 'null');

        return value && typeof value === 'object' ? value : null;
    } catch {
        return null;
    }
}

export function writeAway(storage, value) {
    try {
        value === null ? storage?.removeItem(AWAY_KEY) : storage?.setItem(AWAY_KEY, JSON.stringify(value));
    } catch {
        // Navigation privée, stockage plein : la page marche sans.
    }
}

/** Bas de la zone visible de l'écran, d'après les éléments réellement fixés en bas. */
export function visibleBottom(doc = document) {
    const view = doc.defaultView;
    const tops = [...doc.querySelectorAll('.tabbar, .install')]
        .filter((element) => !element.hidden && view.getComputedStyle(element).position === 'fixed' && element.getClientRects().length > 0)
        .map((element) => element.getBoundingClientRect().top);

    return visibleBottomFrom(view.innerHeight, tops);
}

/**
 * Fait défiler la page pour montrer `first` (en haut) jusqu'à `last` (en bas, par exemple le bouton), en douceur, ou
 * d'un saut si le téléphone demande moins d'animations (ou si `instant`).
 */
export function revealBetween(first, last, { instant = false } = {}) {
    if (!first) {
        return;
    }

    const view = first.ownerDocument.defaultView;
    const top = first.getBoundingClientRect().top + view.scrollY;
    const bottom = (last && last.getClientRects().length > 0 ? last : first).getBoundingClientRect().bottom + view.scrollY;
    const reduce = view.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    view.scrollTo({ top: scrollTarget({ top, bottom, visibleBottom: visibleBottom(first.ownerDocument) }), behavior: instant || reduce ? 'instant' : 'smooth' });
}
