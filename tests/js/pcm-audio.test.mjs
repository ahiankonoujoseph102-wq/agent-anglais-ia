// Lecture de la voix (public/js/pcm-audio.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { VOICE_RATE, decodePcm16, StreamResampler, VoiceScheduler, fadeIn } from '../../public/js/pcm-audio.js';

// Encode des entiers 16 bits en chaîne binaire petit-boutiste (comme atob le rend).
function encode(values) {
    return values.map((v) => {
        const u = v & 0xffff;
        return String.fromCharCode(u & 0xff, u >> 8);
    }).join('');
}

function sine(frequency, rate, length, amplitude = 0.8) {
    return Float32Array.from({ length }, (_, i) => amplitude * Math.sin((2 * Math.PI * frequency * i) / rate));
}

test('la voix de Gemini est lue à 24 kHz', () => {
    assert.equal(VOICE_RATE, 24000);
});

test('PCM 16 bits petit-boutiste : signe, ordre des octets, jamais au-delà de [-1, 1]', () => {
    const samples = decodePcm16(encode([0, 1, -1, 16384, -16384, 32767, -32768, 0x1234]));

    assert.deepEqual(Array.from(samples.slice(0, 7)), [0, 1 / 32768, -1 / 32768, 0.5, -0.5, 32767 / 32768, -1]);
    assert.equal(samples[7], 0x1234 / 32768);
    assert.ok(samples.every((v) => v >= -1 && v <= 1));
    // Octet impair final ignoré (morceau incomplet).
    assert.equal(decodePcm16(encode([5]) + 'x').length, 1);
});

test('rééchantillonnage continu : découper la voix en morceaux ne change rien', () => {
    for (const outRate of [48000, 44100, 16000]) {
        const signal = sine(440, VOICE_RATE, 2400);
        const whole = new StreamResampler(VOICE_RATE, outRate).process(signal);

        const chunked = new StreamResampler(VOICE_RATE, outRate);
        const parts = [];

        for (let start = 0, size = 37; start < signal.length; start += size, size = (size * 7) % 211 + 13) {
            parts.push(...chunked.process(signal.subarray(start, start + size)));
        }

        assert.ok(Math.abs(parts.length - whole.length) <= 1, `longueur à ${outRate} Hz`);

        for (let i = 0; i < Math.min(parts.length, whole.length); i++) {
            assert.ok(Math.abs(parts[i] - whole[i]) < 1e-6, `écart à l'échantillon ${i} (${outRate} Hz)`);
        }

        // Aucun saut brusque (craquement) : la sinusoïde reste lisse.
        const maxStep = (2 * Math.PI * 440 * 0.8) / outRate * 1.05;

        for (let i = 1; i < parts.length; i++) {
            assert.ok(Math.abs(parts[i] - parts[i - 1]) <= maxStep, `saut à l'échantillon ${i} (${outRate} Hz)`);
        }
    }
});

test('les morceaux sont programmés bout à bout, à l\'échantillon près', () => {
    const scheduler = new VoiceScheduler({ sampleRate: 24000 });

    const first = scheduler.schedule(10, 2400);
    assert.equal(first.gap, true);
    assert.equal(first.time, (240000 + 2880) / 24000); // réserve de 120 millisecondes

    // Morceaux suivants reçus plus vite qu'ils ne sont joués : collés au précédent, sans trou ni chevauchement.
    let expectedFrame = 240000 + 2880 + 2400;

    for (const frames of [2400, 1234, 999, 4801]) {
        const next = scheduler.schedule(10.01, frames);
        assert.equal(next.gap, false);
        assert.equal(Math.round(next.time * 24000), expectedFrame);
        assert.ok(Math.abs(next.time * 24000 - expectedFrame) < 1e-6);
        expectedFrame += frames;
    }

    assert.equal(scheduler.endFrame, expectedFrame);
});

test('trou du réseau au milieu d\'une phrase : la réserve grandit (sans dépasser 300 millisecondes)', () => {
    const scheduler = new VoiceScheduler({ sampleRate: 24000 });
    scheduler.schedule(0, 2400);

    const late = scheduler.schedule(5, 2400, true);
    assert.equal(late.gap, true);
    assert.ok(Math.abs(late.time - (5 + 0.18)) < 1e-9);

    for (let i = 0; i < 10; i++) {
        scheduler.schedule(100 + i * 10, 10, true);
    }

    assert.ok(Math.abs(scheduler.reserve - 0.3) < 1e-9);
});

test('arrêt de la voix : plus rien n\'est programmé après', () => {
    const scheduler = new VoiceScheduler({ sampleRate: 24000 });
    scheduler.schedule(0, 240000);
    scheduler.stop(1.02);

    assert.equal(scheduler.endTime, 1.02);
    assert.equal(scheduler.isGap(1.5), true);
});

test('fondu d\'entrée sur les premiers échantillons seulement', () => {
    const samples = fadeIn(new Float32Array(10).fill(1), 4);

    assert.deepEqual(Array.from(samples), [0, 0.25, 0.5, 0.75, 1, 1, 1, 1, 1, 1]);
});
