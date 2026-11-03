import { test } from 'node:test';
import assert from 'node:assert/strict';
import { audioUrl, progress, shouldPoll } from '../../public/js/practice.js';

test('l\'adresse de la voix vise l\'élément demandé', () => {
    assert.equal(audioUrl('https://talkami.net/mes-seances/12/reviser/voix/0', 3), 'https://talkami.net/mes-seances/12/reviser/voix/3');
    assert.equal(audioUrl('/mes-seances/10/reviser/voix/0?leger=1', 4), '/mes-seances/10/reviser/voix/4?leger=1');
});

test('la barre de progression avance avec les éléments', () => {
    assert.equal(progress(0, 4), 25);
    assert.equal(progress(3, 4), 100);
    assert.equal(progress(0, 0), 0);
});

test('on ne demande l\'état que tant qu\'une voix manque, et pas sans fin', () => {
    assert.equal(shouldPoll([true, false], 0), true);
    assert.equal(shouldPoll([true, true], 0), false);
    assert.equal(shouldPoll([false], 24), false);
});
