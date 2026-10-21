// Calendrier de la proposition d'installer l'application.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { STORAGE_KEY, LEGACY_KEY, delayDays, isDue, nextShowAt, dismiss, read, write } from '../../public/js/install-reminder.js';

const DAY = 24 * 60 * 60 * 1000;
const T0 = Date.UTC(2026, 9, 21, 9, 0, 0);

function memoryStorage(initial = {}) {
    const data = { ...initial };

    return {
        data,
        getItem: (key) => (key in data ? data[key] : null),
        setItem: (key, value) => { data[key] = String(value); },
        removeItem: (key) => { delete data[key]; },
    };
}

test('jamais fermée : la proposition s\'affiche', () => {
    assert.equal(isDue({}, T0), true);
    assert.equal(isDue(null, T0), true);
});

test('fermée : elle revient 1 jour après, puis 3 jours après, puis une fois par semaine', () => {
    assert.deepEqual([1, 2, 3, 4, 9].map(delayDays), [1, 3, 7, 7, 7]);

    let state = dismiss({}, T0);
    assert.equal(isDue(state, T0 + DAY - 1), false);
    assert.equal(isDue(state, T0 + DAY), true);

    state = dismiss(state, T0 + DAY);
    assert.equal(isDue(state, T0 + DAY + 3 * DAY - 1), false);
    assert.equal(isDue(state, T0 + DAY + 3 * DAY), true);

    state = dismiss(state, T0 + 4 * DAY);
    assert.equal(nextShowAt(state), T0 + 11 * DAY);
    state = dismiss(state, T0 + 11 * DAY);
    assert.equal(nextShowAt(state), T0 + 18 * DAY);
    assert.equal(state.count, 4);
});

test('jamais à chaque visite : fermée le matin, toujours cachée le soir', () => {
    const state = dismiss({}, T0);
    assert.equal(isDue(state, T0 + 10 * 60 * 60 * 1000), false);
});

test('application installée : plus jamais', () => {
    assert.equal(isDue({ installed: true }, T0), false);
    assert.equal(isDue({ count: 2, last: T0 - 30 * DAY, installed: true }, T0), false);
    assert.equal(nextShowAt({ count: 1, last: T0, installed: true }), null);
});

test('ancienne fermeture « pour de bon » : reprise comme une première fermeture (revient demain)', () => {
    const storage = memoryStorage({ [LEGACY_KEY]: '1' });
    const state = read(storage, T0);

    assert.deepEqual(state, { count: 1, last: T0 });
    assert.equal(storage.getItem(LEGACY_KEY), null);
    assert.equal(JSON.parse(storage.getItem(STORAGE_KEY)).count, 1);
    assert.equal(isDue(state, T0 + DAY), true);
});

test('stockage indisponible : rien n\'est affiché (sinon la proposition reviendrait à chaque visite)', () => {
    const broken = { getItem() { throw new Error('bloqué'); }, setItem() { throw new Error('bloqué'); }, removeItem() {} };

    assert.equal(read(broken, T0), undefined);
    assert.doesNotThrow(() => write(broken, { count: 1, last: T0 }));
});

test('état lu et écrit dans le navigateur', () => {
    const storage = memoryStorage();
    assert.deepEqual(read(storage, T0), {});
    write(storage, dismiss({}, T0));
    assert.deepEqual(read(storage, T0), { count: 1, last: T0 });
});
