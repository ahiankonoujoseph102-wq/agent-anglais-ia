// Porte du micro (public/js/mic-gate.js). Lancé par tests/Feature/JavaScriptTest.php (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { MicGate, voiceEndsAt } from '../../public/js/mic-gate.js';

const DELAY = 0.08;
const GUARD = 0.4;
// Aucune voix jouée depuis longtemps.
const SILENT = -10;

// Rejoue des paquets de micro toutes les 100 millisecondes, de `from` à `to` (secondes, horloge audio).
function run(gate, from, to, voiceEnd, blocked = () => false) {
    const actions = [];

    for (let t = from; t < to - 1e-9; t += 0.1) {
        const now = Math.round(t * 1000) / 1000;
        actions.push([now, gate.decide(now, voiceEnd, blocked(now))]);
    }

    return actions;
}

test('la fin de la voix est la fin programmée du dernier tampon, plus le retard et la latence de sortie', () => {
    assert.equal(voiceEndsAt(12, DELAY, 0.05), 12 + DELAY + 0.05);
    assert.equal(voiceEndsAt(12, DELAY), 12 + DELAY);
    assert.equal(voiceEndsAt(12, DELAY, undefined), 12 + DELAY);
});

test('le micro reste fermé tant que le son est joué, même si Gemini a fini d\'envoyer, puis 400 millisecondes après', () => {
    const gate = new MicGate({ guard: GUARD });
    // Tout l'audio est reçu à t = 0,5 seconde, mais sa lecture est programmée jusqu'à t = 6 secondes.
    const end = voiceEndsAt(6, DELAY, 0.04);

    for (const [now, action] of run(gate, 0.5, 6.5, end)) {
        assert.notEqual(action, 'send', `paquet envoyé à ${now} seconde(s) alors que le professeur parle encore`);
    }

    assert.equal(gate.decide(end + GUARD - 0.01, end, false), 'hold');
    assert.equal(gate.decide(end + GUARD, end, false), 'send');
});

test('micro fermé par la voix plus d\'une seconde : un seul audioStreamEnd, puis l\'envoi reprend à la réouverture', () => {
    const gate = new MicGate({ guard: GUARD });

    assert.equal(gate.decide(0, SILENT, false), 'send');
    assert.equal(gate.decide(0.1, SILENT, false), 'send');

    // Le professeur parle de 0,2 à 3 secondes.
    const end = voiceEndsAt(3, DELAY);
    const closed = run(gate, 0.2, end + GUARD, end);
    const ends = closed.filter(([, action]) => action === 'end');

    assert.equal(ends.length, 1);
    assert.ok(ends[0][0] >= 1.2 - 1e-9, 'audioStreamEnd envoyé avant une seconde de fermeture');
    assert.ok(ends[0][0] <= 1.3, 'audioStreamEnd trop tardif');
    assert.ok(closed.every(([, action]) => action !== 'send'));

    assert.equal(gate.decide(end + GUARD, end, false), 'send');
    assert.equal(gate.decide(end + GUARD + 0.1, end, false), 'send');
});

test('micro fermé moins d\'une seconde : pas d\'audioStreamEnd', () => {
    const gate = new MicGate({ guard: GUARD });

    gate.decide(0, SILENT, false);
    const end = voiceEndsAt(0.3, DELAY);

    assert.ok(run(gate, 0.1, end + GUARD, end).every(([, action]) => action === 'hold'));
    assert.equal(gate.decide(end + GUARD, end, false), 'send');
});

test('pause ou micro coupé : audioStreamEnd tout de suite, rien pendant la coupure, reprise ensuite', () => {
    const gate = new MicGate({ guard: GUARD });

    gate.decide(0, SILENT, false);
    assert.equal(gate.decide(0.1, SILENT, true), 'end');
    assert.ok(run(gate, 0.2, 5, SILENT, () => true).every(([, action]) => action === 'hold'));
    assert.equal(gate.decide(5, SILENT, false), 'send');
});

test('aucun audioStreamEnd si rien n\'a été envoyé (début, nouvelle connexion)', () => {
    const gate = new MicGate({ guard: GUARD });

    assert.ok(run(gate, 0, 3, 5).every(([, action]) => action === 'hold'));

    gate.decide(6, SILENT, false);
    gate.reset();
    assert.equal(gate.decide(6.1, SILENT, true), 'hold');
});

test('Gemini : la porte reste fermée pendant un trou du son tant que la réponse n\'est pas finie', async () => {
    const { heldVoiceEnd } = await import('../../public/js/mic-gate.js');

    // Son reçu épuisé à 10 s, réponse pas finie, dernier morceau à 9,5 s : voix tenue pour en cours.
    assert.ok(heldVoiceEnd({ scheduledEnd: 10, now: 10.6, pending: true, lastChunkAt: 9.5, hold: 3 }) > 10.6);
    // Réponse finie (turnComplete) : la fin programmée compte.
    assert.equal(heldVoiceEnd({ scheduledEnd: 10, now: 10.6, pending: false, lastChunkAt: 9.5, hold: 3 }), 10);
    // Plus aucun son depuis plus de 3 secondes : sécurité, la porte se rouvre.
    assert.equal(heldVoiceEnd({ scheduledEnd: 10, now: 13, pending: true, lastChunkAt: 9.5, hold: 3 }), 10);
    // Accueil demandé, pas encore de voix : fermée jusqu'au premier son ou jusqu'à la limite.
    assert.ok(heldVoiceEnd({ scheduledEnd: 0, now: 1, expectingUntil: 5 }) > 1);
    assert.equal(heldVoiceEnd({ scheduledEnd: 0, now: 6, expectingUntil: 5 }), 0);
});
