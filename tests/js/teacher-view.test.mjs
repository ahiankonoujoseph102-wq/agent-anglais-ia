// Choix du professeur 3D (public/js/teacher-view.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { skipReason, withTimeout } from '../../public/js/teacher-view.js';

const capable = { preference: null, deviceMemory: 4, webgl: true };

test('le professeur 3D est affiché par défaut', () => {
    assert.equal(skipReason(capable), null);
    // Un ancien réglage ou une valeur inconnue ne bloque pas la 3D.
    assert.equal(skipReason({ ...capable, preference: 'off' }), null);
    // Mémoire inconnue (navigateurs qui ne la donnent pas) : 3D quand même.
    assert.equal(skipReason({ ...capable, deviceMemory: undefined }), null);
});

test('« Visage simple » choisi par l\'apprenant est respecté, « 3D » aussi', () => {
    assert.equal(skipReason({ ...capable, preference: 'svg' }), 'preference');
    assert.equal(skipReason({ ...capable, preference: '3d' }), null);
});

test('repli sur le visage simple sans WebGL 2 ou avec très peu de mémoire', () => {
    assert.equal(skipReason({ ...capable, webgl: false }), 'webgl');
    assert.equal(skipReason({ ...capable, deviceMemory: 0.5 }), 'memory');
});

test('un chargement trop long est abandonné, un chargement rapide aboutit', async () => {
    const slow = new Promise((resolve) => setTimeout(() => resolve('3d'), 200));
    await assert.rejects(withTimeout(slow, 20), /timeout/);

    assert.equal(await withTimeout(Promise.resolve('3d'), 20), '3d');
    await assert.rejects(withTimeout(Promise.reject(new Error('webgl')), 20), /webgl/);
});
