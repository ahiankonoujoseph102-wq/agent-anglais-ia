import { test } from 'node:test';
import assert from 'node:assert/strict';
import { show, watch } from '../../public/js/course-review.js';

function fakeRoot(state = 'pending') {
    const blocks = ['pending', 'ready', 'failed'].map((when) => ({ dataset: { reviewWhen: when }, hidden: when !== state }));

    return { dataset: { reviewState: state, reviewStatus: '/mes-cours/seances/7/document/etat' }, querySelectorAll: () => blocks, blocks };
}

test('montre seulement le bloc de l\'état reçu', () => {
    const root = fakeRoot();
    show(root, 'ready');
    assert.deepEqual(root.blocks.map((b) => b.hidden), [true, false, true]);
    assert.equal(root.dataset.reviewState, 'ready');
});

test('interroge l\'état jusqu\'à ce que le document soit prêt, puis s\'arrête', async () => {
    const root = fakeRoot();
    const answers = ['pending', 'pending', 'ready'];
    const timers = [];
    let calls = 0;
    const fetchImpl = async (url) => {
        assert.equal(url, '/mes-cours/seances/7/document/etat');
        return { ok: true, json: async () => ({ state: answers[calls++] }) };
    };

    watch(root, { fetchImpl, setTimer: (fn) => timers.push(fn) });

    while (timers.length) {
        await timers.shift()();
    }

    assert.equal(calls, 3);
    assert.equal(root.dataset.reviewState, 'ready');
});

test('un échec réseau n\'arrête pas la surveillance, qui a une limite', async () => {
    const root = fakeRoot();
    const timers = [];
    let calls = 0;
    const fetchImpl = async () => { calls++; throw new Error('réseau'); };

    watch(root, { fetchImpl, setTimer: (fn) => timers.push(fn), max: 4 });

    while (timers.length) {
        await timers.shift()();
    }

    assert.equal(calls, 4);
    assert.equal(root.dataset.reviewState, 'pending');
});

test('un échec définitif montre « Réessayer »', async () => {
    const root = fakeRoot();
    const timers = [];

    watch(root, { fetchImpl: async () => ({ ok: true, json: async () => ({ state: 'failed' }) }), setTimer: (fn) => timers.push(fn) });
    await timers.shift()();

    assert.deepEqual(root.blocks.map((b) => b.hidden), [true, true, false]);
});
