// Micro de l'apprenant (public/js/mic-input.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { listMicrophones, micLabel, rms, meterLevel, SilenceWatch, MicBoost } from '../../public/js/mic-input.js';

const mic = (deviceId, label, kind = 'audioinput') => ({ deviceId, label, kind });

test('libellés compréhensibles pour les micros d\'Android', () => {
    assert.equal(micLabel('Speakerphone'), 'Micro du téléphone');
    assert.equal(micLabel('Headset earpiece'), 'Micro du téléphone');
    assert.equal(micLabel('Bluetooth headset'), 'Écouteurs ou enceinte Bluetooth');
    assert.equal(micLabel('Galaxy Buds2 Pro'), 'Écouteurs ou enceinte Bluetooth');
    assert.equal(micLabel('Wired headset'), 'Écouteurs avec fil');
    assert.equal(micLabel('USB audio'), 'Micro USB');
    assert.equal(micLabel('', 1), 'Micro 2');
});

test('liste sans doublons, sans les sorties audio ni les entrées virtuelles « default »', () => {
    const list = listMicrophones([
        mic('default', 'Default'),
        mic('communications', 'Communications - Micro'),
        mic('a1', 'Speakerphone'),
        mic('a2', 'Headset earpiece'),
        mic('bt', 'Bluetooth headset'),
        mic('out', 'Speaker', 'audiooutput'),
    ]);

    assert.deepEqual(list.map((m) => m.id), ['a1', 'a2', 'bt']);
    assert.deepEqual(list.map((m) => m.label), ['Micro du téléphone', 'Micro du téléphone (2)', 'Écouteurs ou enceinte Bluetooth']);
});

test('un seul micro réel : une seule entrée (pas de choix à proposer)', () => {
    assert.equal(listMicrophones([mic('default', 'Default'), mic('a1', 'Speakerphone')]).length, 1);
    assert.equal(listMicrophones([mic('default', '')]).length, 1);
});

test('niveau sonore et témoin', () => {
    assert.equal(rms(new Float32Array(0)), 0);
    assert.ok(Math.abs(rms(Float32Array.from([0.5, -0.5, 0.5, -0.5])) - 0.5) < 1e-9);
    assert.equal(meterLevel(0), 0);
    assert.equal(meterLevel(0.2), 1);
    assert.equal(meterLevel(3), 1);
    assert.ok(meterLevel(0.02) > 0.3, 'une voix faible reste visible');
});

test('alerte après 8 secondes sans aucun son pendant le tour de l\'apprenant', () => {
    const watch = new SilenceWatch({ seconds: 8 });

    for (let t = 0; t < 8; t += 0.25) {
        assert.equal(watch.update(t, 0.0001, true), false);
    }

    assert.equal(watch.update(8, 0.0001, true), true);
    // Un son capté efface l'alerte et relance le décompte.
    assert.equal(watch.update(8.5, 0.05, true), false);
    assert.equal(watch.update(9, 0, true), false);
});

test('pas d\'alerte quand le professeur parle, en pause ou micro coupé', () => {
    const watch = new SilenceWatch({ seconds: 8 });

    for (let t = 0; t < 20; t += 0.5) {
        assert.equal(watch.update(t, 0, t % 4 < 2), false);
    }
});

// Simule une séance : bruit de fond, puis parole, à 30 mesures par seconde.
function run(boost, { noise, speech, seconds = 6, listening = true }) {
    let gain = boost.gain;
    for (let i = 0; i < seconds * 30; i++) {
        const t = (boost.last ?? 0) + 1 / 30;
        const talking = Math.floor(i / 30) % 2 === 1;
        gain = boost.update(t, talking ? speech : noise, listening);
    }
    return gain;
}

test('voix normale : le micro garde son volume', () => {
    const boost = new MicBoost();
    assert.equal(run(boost, { noise: 0.002, speech: 0.08 }), 1);
});

test('voix très faible : le micro est remonté, sans dépasser le maximum', () => {
    const boost = new MicBoost({ max: 24, target: 0.05 });
    const gain = run(boost, { noise: 0.0001, speech: 0.002 });
    assert.ok(gain > 10, `gain ${gain}`);
    assert.ok(gain <= 24);
});

test('le bruit de fond seul ne fait jamais monter le volume', () => {
    const boost = new MicBoost();
    assert.equal(run(boost, { noise: 0.001, speech: 0.001 }), 1);
});

test('le bruit de fond remonté reste sous le plafond', () => {
    const boost = new MicBoost({ max: 24, target: 0.05, noiseCeiling: 0.006 });
    const gain = run(boost, { noise: 0.001, speech: 0.004 });
    assert.ok(gain * 0.001 <= 0.006 + 1e-9, `gain ${gain}`);
});

test('rien ne change en dehors du tour de l\'apprenant', () => {
    const boost = new MicBoost();
    assert.equal(run(boost, { noise: 0.0001, speech: 0.002, listening: false }), 1);
});

test('voix qui redevient forte : le volume redescend aussitôt', () => {
    const boost = new MicBoost();
    run(boost, { noise: 0.0001, speech: 0.002 });
    const gain = boost.update(boost.last + 0.03, 0.2, true);
    assert.ok(gain <= 1.0001, `gain ${gain}`);
});
