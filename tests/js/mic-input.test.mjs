// Micro de l'apprenant (public/js/mic-input.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { listMicrophones, micLabel, rms, meterLevel, SilenceWatch } from '../../public/js/mic-input.js';

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
