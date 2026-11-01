// Accueil, cartes des publics : calcul de la carte en cours et de la suivante.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nearestIndex, nextIndex } from '../../public/js/audience-carousel.js';

const LEFTS = [0, 300, 600, 900];

test('la carte en cours est la plus proche de la position de défilement', () => {
    assert.equal(nearestIndex(LEFTS, 0, 860), 0);
    assert.equal(nearestIndex(LEFTS, 140, 860), 0);
    assert.equal(nearestIndex(LEFTS, 170, 860), 1);
    assert.equal(nearestIndex(LEFTS, 610, 860), 2);
});

test('tout au bout, c\'est la dernière carte (elle ne peut pas se caler au début)', () => {
    assert.equal(nearestIndex(LEFTS, 860, 860), 3);
    assert.equal(nearestIndex(LEFTS, 859, 860), 3);
});

test('aucune carte : la première', () => {
    assert.equal(nearestIndex([], 50, 0), 0);
});

test('après la dernière carte, on revient à la première', () => {
    assert.deepEqual([0, 1, 2, 3].map((i) => nextIndex(i, 4)), [1, 2, 3, 0]);
    assert.equal(nextIndex(0, 0), 0);
});
