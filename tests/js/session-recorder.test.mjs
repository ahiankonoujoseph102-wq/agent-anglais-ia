// « Enregistrer ma séance » (public/js/session-recorder.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { coverRect, extensionFor, fileName, formatElapsed, formatSize, pickMimeType, supportInfo } from '../../public/js/session-recorder.js';

test('le MP4 est préféré, sinon le WebM, sinon rien', () => {
    assert.match(pickMimeType(() => true), /^video\/mp4/);
    assert.match(pickMimeType((type) => type.startsWith('video/webm')), /^video\/webm;codecs=vp9/);
    assert.equal(pickMimeType((type) => type === 'video/webm'), 'video/webm');
    assert.equal(pickMimeType(() => false), null);
    assert.equal(pickMimeType(() => { throw new Error('refusé'); }), null);
    assert.equal(extensionFor('video/mp4;codecs=avc1'), 'mp4');
    assert.equal(extensionFor('video/webm;codecs=vp8,opus'), 'webm');
});

test('le nom du fichier est clair : plateforme, séance, date et heure', () => {
    assert.equal(fileName('talkami-seance', new Date(2026, 9, 22, 14, 5), 'mp4'), 'talkami-seance-22-10-2026-14h05.mp4');
    assert.equal(fileName('talkami-evaluation', new Date(2026, 0, 3, 9, 0), 'webm'), 'talkami-evaluation-03-01-2026-09h00.webm');
});

test('durée et taille sont écrites sans abréviation', () => {
    assert.equal(formatElapsed(0), '0:00');
    assert.equal(formatElapsed(125.7), '2:05');
    assert.equal(formatSize(300 * 1024), 'moins d\'un mégaoctet');
    assert.equal(formatSize(1024 * 1024), '1 mégaoctet');
    assert.equal(formatSize(12.4 * 1024 * 1024), '12 mégaoctets');
});

test('le professeur remplit la zone 3:4 sans être déformé', () => {
    const wide = coverRect(800, 600, 540, 720);
    assert.ok(Math.abs(wide.sw / wide.sh - 540 / 720) < 1e-9);
    assert.equal(wide.sh, 600);
    assert.equal(wide.sx, (800 - wide.sw) / 2);
    const tall = coverRect(300, 900, 540, 720);
    assert.equal(tall.sw, 300);
    assert.ok(tall.sy > 0 && tall.sy < (900 - tall.sh) / 2);
});

test('sans MediaRecorder, capture du canevas ou sortie audio en flux : pas d\'enregistrement', () => {
    const ok = {
        MediaRecorder: { isTypeSupported: (type) => type === 'video/webm' },
        HTMLCanvasElement: { prototype: { captureStream() {} } },
        AudioContext: { prototype: { createMediaStreamDestination() {} } },
    };
    assert.equal(supportInfo(ok), 'video/webm');
    assert.equal(supportInfo({ ...ok, MediaRecorder: undefined }), null);
    assert.equal(supportInfo({ ...ok, HTMLCanvasElement: { prototype: {} } }), null);
    assert.equal(supportInfo({ ...ok, AudioContext: { prototype: {} } }), null);
    assert.equal(supportInfo({ ...ok, MediaRecorder: { isTypeSupported: () => false } }), null);
});

test('la vidéo ne part jamais au serveur et le son n\'est jamais rejoué', () => {
    const code = readFileSync(new URL('../../public/js/session-recorder.js', import.meta.url), 'utf8');
    assert.ok(!/fetch\(|XMLHttpRequest|sendBeacon|WebSocket/.test(code));
    assert.ok(!code.includes('context.destination'));
    assert.ok(!code.includes('Teacher Joe') && !code.includes('Talkami'));
});
