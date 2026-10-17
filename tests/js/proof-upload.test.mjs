import test from 'node:test';
import assert from 'node:assert/strict';
import { COMPRESS_ABOVE_BYTES, MAX_SIDE, fitWithin, formatBytes, needsCompression } from '../../public/js/proof-upload.js';

test('une petite capture est envoyée telle quelle', () => {
    assert.equal(needsCompression(400 * 1024, 1080, 1920), false);
});

test('une image lourde ou très grande est compressée', () => {
    assert.equal(needsCompression(COMPRESS_ABOVE_BYTES + 1, 1080, 1920), true);
    assert.equal(needsCompression(300 * 1024, 1440, 3200), true);
});

test('la réduction garde les proportions et n\'agrandit jamais', () => {
    assert.deepEqual(fitWithin(1440, 3200), { width: 900, height: MAX_SIDE });
    assert.deepEqual(fitWithin(800, 600), { width: 800, height: 600 });
});

test('les tailles sont écrites en toutes lettres', () => {
    assert.equal(formatBytes(850 * 1024), '850 kilooctets');
    assert.equal(formatBytes(2.4 * 1024 * 1024), '2,4 mégaoctets');
});
