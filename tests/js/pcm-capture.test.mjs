// Réduction de fréquence du micro (public/js/pcm-capture-worklet.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Downsampler, toInt16 } from '../../public/js/pcm-capture-worklet.js';

function sine(frequency, rate, length) {
    return Float32Array.from({ length }, (_, i) => 0.8 * Math.sin((2 * Math.PI * frequency * i) / rate));
}

// Amplitude (valeur efficace × racine de 2) après le régime transitoire du filtre.
function amplitude(samples) {
    const tail = samples.slice(200);
    return Math.sqrt(tail.reduce((sum, v) => sum + v * v, 0) / tail.length) * Math.SQRT2;
}

function run(inRate, frequency, chunk = 128) {
    const downsampler = new Downsampler(inRate, 16000);
    const input = sine(frequency, inRate, inRate);
    const output = [];

    for (let start = 0; start < input.length; start += chunk) {
        downsampler.process(input.subarray(start, start + chunk), (sample) => output.push(sample));
    }

    return output;
}

for (const rate of [24000, 44100, 48000]) {
    test(`micro à ${rate} Hz : 16 000 échantillons par seconde, la voix passe intacte`, () => {
        const output = run(rate, 1000);

        assert.ok(Math.abs(output.length - 16000) <= 2, `${output.length} échantillons`);
        assert.ok(Math.abs(amplitude(output) - 0.8) < 0.03, `amplitude ${amplitude(output)}`);
    });

    test(`micro à ${rate} Hz : les aigus au-dessus de 8 kHz sont filtrés (pas de repliement en grésillement)`, () => {
        const output = run(rate, 11000);

        assert.ok(amplitude(output) < 0.02, `amplitude repliée ${amplitude(output)}`);
    });
}

test('conversion en 16 bits : écrêtage à [-1, 1], arrondi', () => {
    assert.equal(toInt16(0), 0);
    assert.equal(toInt16(1), 32767);
    assert.equal(toInt16(-1), -32768);
    assert.equal(toInt16(3), 32767);
    assert.equal(toInt16(-3), -32768);
    assert.equal(toInt16(0.5), 16384);
});
