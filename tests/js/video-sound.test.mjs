// Son et temps de fabrication des vidéos de Teacher Joe (tools/video/common.mjs) : sonie visée à −14 LUFS, crête vraie
// sous −1 dB, limiteur à 48 kHz ; arrêt d'une machine trop lente avant la limite de GitHub.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { audioFilter, soundFix, willFinish } from '../../tools/video/common.mjs';

const audio = { target_lufs: -14, limit: 0.708, limiter_makeup_db: 1.5, max_peak_db: -1, tolerance_lu: 1, rate: 48000 };

test('le limiteur travaille à 48 kHz, avant toute autre étape', () => {
    const filter = audioFilter(audio, 12.9);

    assert.match(filter, /^aresample=48000,/);
    assert.match(filter, /volume=12\.9dB,alimiter=limit=0\.708:/);
    assert.match(audioFilter(audio, 3, 0.5), /alimiter=limit=0\.5:/);
});

test('un son déjà bon n\'est pas refait', () => {
    assert.equal(soundFix({ lufs: -14.2, peak: -2.2 }, audio, 13), null);
    assert.equal(soundFix({ lufs: -13.1, peak: -1 }, audio, 13), null);
    assert.equal(soundFix({ lufs: null, peak: null }, audio, 13), null);
});

test('la vidéo du Club du 29 octobre (crête +1,4 dB) aurait été refaite avec un plafond plus bas', () => {
    const fix = soundFix({ lufs: -13.7, peak: 1.4 }, audio, 12.9);

    assert.ok(fix.limit < audio.limit);
    // 2,9 dB plus bas (crête visée −1,5 dB) : 0,708 × 10^(−2,9/20) ≈ 0,507.
    assert.equal(fix.limit, 0.507);
    assert.equal(fix.gainDb, 12.6);
});

test('un son trop faible est remonté vers −14 LUFS', () => {
    const fix = soundFix({ lufs: -17, peak: -6 }, audio, 10);

    assert.equal(fix.gainDb, 13);
    assert.equal(fix.limit, audio.limit);
});

test('une machine trop lente est arrêtée tôt, une machine normale jamais', () => {
    const start = 0;
    const deadline = 72 * 60000;
    const frames = 4224; // 3 minutes 31 à 20 images par seconde

    // Avant 250 images, aucune décision.
    assert.equal(willFinish(start, 100 * 1150, 100, frames, deadline), true);
    // 1 150 millisecondes par image : environ 81 minutes, au-delà des 72 accordées.
    assert.equal(willFinish(start, 250 * 1150, 250, frames, deadline), false);
    // 294 millisecondes par image (dépôt privé) : environ 21 minutes.
    assert.equal(willFinish(start, 250 * 294, 250, frames, deadline), true);
    // Sans temps accordé : jamais arrêtée.
    assert.equal(willFinish(start, 250 * 5000, 250, frames, 0), true);
});
