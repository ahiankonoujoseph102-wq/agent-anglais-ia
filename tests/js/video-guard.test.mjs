// node --test tests/js/video-guard.test.mjs
// Vidéo de « Enregistrer ma séance » pas encore téléchargée : confirmation avant de quitter la page, jamais sinon.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { leavesPage, VideoGuard, videoAtStake } from '../../public/js/video-guard.js';

const here = new URL('https://talkami.test/evaluation');

/** Fenêtre simulée : écouteurs et navigations notés. */
function fakeWindow() {
    const listeners = {};

    return {
        listeners,
        assigned: [],
        location: { href: here.href, origin: here.origin, pathname: here.pathname, search: '', assign(url) { this.parent.assigned.push(url); } },
        addEventListener(type, fn) { (listeners[type] ??= new Set()).add(fn); },
        removeEventListener(type, fn) { listeners[type]?.delete(fn); },
        count(type) { return listeners[type]?.size ?? 0; },
        fire(type, event) { [...(listeners[type] ?? [])].forEach((fn) => fn(event)); return event; },
    };
}

function link(href, attributes = {}) {
    return {
        href: new URL(href, here).href,
        target: attributes.target ?? '',
        getAttribute: (name) => (name === 'href' ? href : attributes[name] ?? null),
        hasAttribute: (name) => name in attributes,
        closest() { return this; },
    };
}

function click(target, extra = {}) {
    return { target, button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra };
}

function setup(answer) {
    const win = fakeWindow();
    win.location.parent = win;
    const asked = [];
    const guard = new VideoGuard({ win, ask: async () => { asked.push(true); return answer; } });

    return { win, guard, asked };
}

test('une vidéo est en jeu pendant l\'enregistrement, puis tant qu\'elle n\'est pas téléchargée', () => {
    assert.equal(videoAtStake({}), false);
    assert.equal(videoAtStake({ recording: true }), true);
    assert.equal(videoAtStake({ ready: true }), true);
    assert.equal(videoAtStake({ ready: true, downloaded: true }), false);
});

test('liens qui quittent la page : seulement vers une autre page du site, dans le même onglet', () => {
    assert.equal(leavesPage(link('/espace'), here), true);
    assert.equal(leavesPage(link('https://talkami.test/programme#suite'), here), true);
    assert.equal(leavesPage(link('#subtitles'), here), false);
    assert.equal(leavesPage(link('/evaluation#formules'), here), false);
    assert.equal(leavesPage(link('https://wa.me/22872240231'), here), false);
    assert.equal(leavesPage(link('tel:+22872240231'), here), false);
    assert.equal(leavesPage(link('blob:https://talkami.test/1', { download: 'video.mp4' }), here), false);
    assert.equal(leavesPage(link('/espace', { target: '_blank' }), here), false);
});

test('beforeunload : écouté seulement quand une vidéo est en jeu, retiré après le téléchargement', () => {
    const { win, guard } = setup(true);
    assert.equal(win.count('beforeunload'), 0);

    guard.update({ recording: true });
    assert.equal(win.count('beforeunload'), 1);
    const event = win.fire('beforeunload', { defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
    assert.equal(event.defaultPrevented, true);
    assert.equal(event.returnValue, '');

    guard.update({ recording: false, ready: true });
    assert.equal(win.count('beforeunload'), 1);
    guard.update({ downloaded: true });
    assert.equal(win.count('beforeunload'), 0);
});

test('lien interne avec une vidéo non téléchargée : fenêtre du site, rester ou partir', async () => {
    const stay = setup(false);
    stay.guard.update({ ready: true });
    const first = click(link('/espace'));
    await stay.guard.click(first);
    assert.equal(first.defaultPrevented, true);
    assert.equal(stay.asked.length, 1);
    assert.deepEqual(stay.win.assigned, []);
    assert.equal(stay.win.count('beforeunload'), 1);

    const leave = setup(true);
    leave.guard.update({ ready: true });
    await leave.guard.click(click(link('/espace')));
    assert.deepEqual(leave.win.assigned, ['https://talkami.test/espace']);
    // Choix fait : le navigateur ne repose pas la question.
    assert.equal(leave.win.count('beforeunload'), 0);

    // Fenêtre du site indisponible : la navigation part, le navigateur demande lui-même.
    const native = setup(null);
    native.guard.update({ ready: true });
    await native.guard.click(click(link('/espace')));
    assert.deepEqual(native.win.assigned, ['https://talkami.test/espace']);
    assert.equal(native.win.count('beforeunload'), 1);
});

test('rien n\'est demandé sans vidéo, après le téléchargement, ni pour une ancre, un nouvel onglet ou un clic déjà pris', async () => {
    const { guard, asked } = setup(true);

    for (const event of [click(link('/espace'))]) {
        await guard.click(event);
        assert.equal(event.defaultPrevented, false);
    }

    guard.update({ ready: true, downloaded: true });
    await guard.click(click(link('/espace')));
    guard.update({ downloaded: false });
    await guard.click(click(link('#subtitles')));
    await guard.click(click(link('/espace'), { ctrlKey: true }));
    await guard.click(click(link('/espace'), { defaultPrevented: true }));
    assert.equal(asked.length, 0);
});
