// Rendu de la voix du professeur (public/js/voice-clarity.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildVoiceChain, clearSettings, softClipCurve, CLEAR_DEFAULTS } from '../../public/js/voice-clarity.js';

// Contexte audio simulé : chaque nœud garde son type, ses réglages et ce à quoi il est branché.
function fakeContext() {
    const param = (value = 0) => ({ value });
    const node = (kind, extra = {}) => {
        const n = { kind, next: null, ...extra };
        n.connect = (target) => { n.next = target; return target; };
        return n;
    };

    return {
        createGain: () => node('gain', { gain: param(1) }),
        createBiquadFilter: () => node('filter', { type: '', frequency: param(), Q: param(), gain: param() }),
        createDynamicsCompressor: () => node('compressor', { threshold: param(), knee: param(), ratio: param(), attack: param(), release: param() }),
        createWaveShaper: () => node('shaper', { curve: null, oversample: 'none' }),
    };
}

function walk(start) {
    const nodes = [];
    for (let n = start; n; n = n.next) nodes.push(n);
    return nodes;
}

test('le son actuel est gardé valeur pour valeur', () => {
    const { input, output } = buildVoiceChain(fakeContext(), { mode: 'classic', gain: 2.5 });
    const chain = walk(input);
    assert.deepEqual(chain.map((n) => n.kind), ['gain', 'gain', 'compressor']);
    assert.equal(chain[1].gain.value, 2.5);
    assert.equal(output.threshold.value, -3);
    assert.equal(output.knee.value, 0);
    assert.equal(output.ratio.value, 20);
    assert.equal(output.release.value, 0.15);
});

test('un mode inconnu garde le son actuel', () => {
    const { input } = buildVoiceChain(fakeContext(), { mode: 'autre', gain: 2.5 });
    assert.deepEqual(walk(input).map((n) => n.kind), ['gain', 'gain', 'compressor']);
});

test('la voix plus nette : coupe-bas, creux, présence, volume, compression douce, limiteur, garde-fou', () => {
    const { input, output } = buildVoiceChain(fakeContext(), { mode: 'clear', gain: 2.5, clear: {} });
    const chain = walk(input);
    assert.deepEqual(chain.map((n) => n.kind), ['gain', 'filter', 'filter', 'filter', 'gain', 'compressor', 'compressor', 'shaper']);
    assert.equal(chain[1].type, 'highpass');
    assert.equal(chain[2].gain.value, CLEAR_DEFAULTS.mud_db);
    assert.equal(chain[3].frequency.value, CLEAR_DEFAULTS.presence_hz);
    // Le volume de la voix plus nette vient de ses propres réglages, jamais de LIVE_VOICE_GAIN.
    assert.equal(chain[4].gain.value, CLEAR_DEFAULTS.gain);
    assert.equal(chain[5].ratio.value, CLEAR_DEFAULTS.compressor_ratio);
    assert.equal(output.kind, 'shaper');
    assert.ok(output.curve instanceof Float32Array);
});

test('les réglages du serveur sont repris, ramenés dans des bornes sûres', () => {
    const s = clearSettings({ gain: 9, presence_db: 2, mud_db: 'x' });
    assert.equal(s.gain, 4);
    assert.equal(s.presence_db, 2);
    assert.equal(s.mud_db, CLEAR_DEFAULTS.mud_db);
});

test('le garde-fou ne touche pas la voix normale et ne dépasse jamais le maximum', () => {
    const curve = softClipCurve(2001, 0.9);
    const at = (x) => curve[Math.round((x + 1) / 2 * 2000)];
    assert.ok(Math.abs(at(0.5) - 0.5) < 1e-3);
    assert.ok(Math.abs(at(-0.8) + 0.8) < 1e-3);
    for (const v of curve) {
        assert.ok(Math.abs(v) <= 1);
    }
    assert.ok(at(1) > 0.97);
});
