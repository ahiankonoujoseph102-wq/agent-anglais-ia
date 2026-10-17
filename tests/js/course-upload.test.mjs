import test from 'node:test';
import assert from 'node:assert/strict';
import { DEFAULT_MAX_SIDE, KEEP_BELOW_BYTES, addPages, fitWithin, formatBytes, needsReduction, reducedName } from '../../public/js/course-upload.js';

test('une photo de téléphone est réduite à 1600 pixels du côté le plus long', () => {
    assert.equal(DEFAULT_MAX_SIDE, 1600);
    assert.deepEqual(fitWithin(3000, 4000), { width: 1200, height: 1600 });
    assert.deepEqual(fitWithin(4000, 3000), { width: 1600, height: 1200 });
});

test('une petite image n\'est jamais agrandie', () => {
    assert.deepEqual(fitWithin(800, 600), { width: 800, height: 600 });
    assert.deepEqual(fitWithin(1, 1), { width: 1, height: 1 });
});

test('seules les photos grandes ou lourdes sont retouchées', () => {
    assert.equal(needsReduction(200 * 1024, 1200, 1600), false);
    assert.equal(needsReduction(200 * 1024, 3000, 4000), true);
    assert.equal(needsReduction(KEEP_BELOW_BYTES * 2 + 1, 1200, 1600), true);
});

test('quatre pages au plus : le reste est refusé, dans l\'ordre d\'ajout', () => {
    assert.deepEqual(addPages(['a'], ['b', 'c'], 4), { pages: ['a', 'b', 'c'], refused: 0 });
    assert.deepEqual(addPages(['a', 'b', 'c'], ['d', 'e', 'f'], 4), { pages: ['a', 'b', 'c', 'd'], refused: 2 });
    assert.deepEqual(addPages(['a', 'b', 'c', 'd'], ['e'], 4), { pages: ['a', 'b', 'c', 'd'], refused: 1 });
});

test('le nom du fichier réduit est un JPEG', () => {
    assert.equal(reducedName('page.png'), 'page.jpg');
    assert.equal(reducedName('IMG_2041.HEIC'), 'IMG_2041.jpg');
    assert.equal(reducedName(''), 'page.jpg');
});

test('les tailles sont écrites en toutes lettres', () => {
    assert.equal(formatBytes(850 * 1024), '850 kilooctets');
    assert.equal(formatBytes(2.4 * 1024 * 1024), '2,4 mégaoctets');
});
