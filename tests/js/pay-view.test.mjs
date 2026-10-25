import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { AWAY_KEY, AWAY_MAX_MS, BOTTOM_GAP, TOP_OFFSET, readAway, scrollTarget, shouldReturn, visibleBottomFrom, writeAway } from '../../public/js/pay-view.js';

test('le bloc de paiement s\'affiche juste sous l\'en-tête quand le bouton tient dans l\'écran', () => {
    // Bloc à 900 pixels du haut de la page, bouton fini à 1 300 : 1 300 − 816 = 484 pixels, l'écran en montre 576.
    assert.equal(scrollTarget({ top: 900, bottom: 1300, visibleBottom: 576 }), 900 - TOP_OFFSET);
});

test('si le bouton ne tient pas, la page descend juste assez pour le montrer au-dessus de la barre du bas', () => {
    const target = scrollTarget({ top: 900, bottom: 1600, visibleBottom: 576 });
    assert.equal(target, 1600 + BOTTOM_GAP - 576);
    // Le bas du bouton est alors dans la zone visible.
    assert.ok(1600 - target <= 576);
});

test('jamais un défilement négatif', () => {
    assert.equal(scrollTarget({ top: 20, bottom: 200, visibleBottom: 600 }), 0);
});

test('la zone visible s\'arrête au-dessus de la barre du bas et du bandeau d\'installation', () => {
    assert.equal(visibleBottomFrom(640, []), 640);
    assert.equal(visibleBottomFrom(640, [576]), 576);
    assert.equal(visibleBottomFrom(640, [576, 500]), 500);
    // Un élément fixé en haut (l'en-tête) ou hors de l'écran ne compte pas.
    assert.equal(visibleBottomFrom(640, [0, 700]), 640);
});

test('retour depuis l\'application Mobile Money : même page, même moyen de paiement, il y a peu de temps', () => {
    const now = 1_000_000;
    const saved = { key: 'TG.mixx', path: '/abonnement/semaine', at: now - 60_000 };

    assert.equal(shouldReturn(saved, { key: 'TG.mixx', path: '/abonnement/semaine', now }), true);
    assert.equal(shouldReturn(saved, { key: 'TG.flooz', path: '/abonnement/semaine', now }), false, 'autre moyen de paiement');
    assert.equal(shouldReturn(saved, { key: 'TG.mixx', path: '/abonnement/essentiel', now }), false, 'autre formule');
    assert.equal(shouldReturn(saved, { key: null, path: '/abonnement/semaine', now }), false, 'capture déjà choisie ou rien de choisi');
    assert.equal(shouldReturn({ ...saved, at: now - AWAY_MAX_MS }, { key: 'TG.mixx', path: '/abonnement/semaine', now }), false, 'trop ancien');
    assert.equal(shouldReturn(null, { key: 'TG.mixx', path: '/abonnement/semaine', now }), false);
});

test('l\'état gardé ne bloque jamais la page, même sans stockage', () => {
    const store = new Map();
    const storage = { getItem: (k) => store.get(k) ?? null, setItem: (k, v) => store.set(k, v), removeItem: (k) => store.delete(k) };

    writeAway(storage, { key: 'BJ.mtn', path: '/abonnement/semaine', at: 1 });
    assert.deepEqual(readAway(storage), { key: 'BJ.mtn', path: '/abonnement/semaine', at: 1 });
    writeAway(storage, null);
    assert.equal(store.has(AWAY_KEY), false);

    const broken = { getItem() { throw new Error('bloqué'); }, setItem() { throw new Error('bloqué'); }, removeItem() { throw new Error('bloqué'); } };
    assert.equal(readAway(broken), null);
    assert.doesNotThrow(() => writeAway(broken, { key: 'x' }));
    assert.equal(readAway(null), null);
    storage.setItem(AWAY_KEY, '{pas du json');
    assert.equal(readAway(storage), null);
});

test('le parcours de paiement se charge sans page (rien n\'est lancé hors d\'un navigateur)', async () => {
    await assert.doesNotReject(import('../../public/js/payment-flow.js'));
});

test('le retour depuis l\'application est écouté, et le bouton mis en évidence', () => {
    const source = readFileSync(new URL('../../public/js/payment-flow.js', import.meta.url), 'utf8');

    assert.match(source, /visibilitychange/);
    assert.match(source, /pagehide/);
    assert.match(source, /is-highlighted/);
    // Le choix de l'image (galerie) n'est pas pris pour un départ vers l'application Mobile Money.
    assert.match(source, /picking = true/);
});
