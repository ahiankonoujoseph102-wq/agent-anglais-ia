import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { SessionClosing } from '../../public/js/conversation-end.js';

// Fin de la séance du jour et de « Mes cours » : annonce d'une minute, UNE conclusion, fermeture propre.

const make = (options = {}) => new SessionClosing({ total: 900, ...options });

/** Déroule la séance seconde par seconde (minuteur du site), le professeur parlant pendant les secondes données. */
function run(closing, { from = 120, speaking = () => false } = {}) {
    const steps = [];

    for (let remaining = from; remaining > 0; remaining--) {
        const now = 1000 - remaining;
        const step = closing.next(remaining, speaking(remaining), now);

        if (step) {
            steps.push([step, remaining]);
        }
    }

    return steps;
}

test('une annonce vers une minute, puis UNE conclusion vers 40 secondes, chacune une seule fois', () => {
    assert.deepEqual(run(make()), [['announce', 60], ['conclude', 40]]);
});

test('il finit sa phrase avant chaque indication, au plus tard 15 secondes après, puis au plus tard à 25 secondes', () => {
    const steps = run(make(), { speaking: (r) => (r <= 60 && r > 52) || (r <= 40 && r > 20) });

    assert.deepEqual(steps, [['announce', 52], ['conclude', 25]]);
});

test('séance courte : pas d\'annonce, seulement la conclusion', () => {
    assert.deepEqual(run(make({ total: 100 }), { from: 100 }), [['conclude', 40]]);
});

test('la fin dépend du temps restant, jamais du volume du micro', () => {
    // Aucun paramètre ne parle du micro : seulement le temps restant, et si la voix du professeur sort encore.
    assert.deepEqual(SessionClosing.prototype.next.toString().match(/^next\(([^)]*)\)/)[1].split(',').map((p) => p.trim()), ['remaining', 'busy', 'now']);

    // Même temps restant, même résultat, quel que soit le bruit autour de l'élève (rien à lui donner).
    const quiet = run(make());
    const noisy = run(make());
    assert.deepEqual(quiet, noisy);

    // Dans l'écran de séance, les indications de fin ne lisent ni le niveau du micro ni la détection de voix.
    const script = readFileSync(new URL('../../public/js/live-session.js', import.meta.url), 'utf8');
    const tick = script.slice(script.indexOf('function tick()'), script.indexOf('function closingCue('));
    const closing = tick.slice(tick.indexOf('state.closing'), tick.indexOf('// Séance du jour : étape'));
    assert.match(closing, /state\.closing\.next\(seconds, busy, clock\(\)\)/);
    assert.match(tick, /const busy = teacherSpeaking\(\) \|\| state\.generating;/);
    assert.doesNotMatch(tick, /micLevel|voiceActivity|VoiceActivity|rms\(|meterLevel|learnerHeardAt/);
});

test('la phrase de fin entendue ferme le micro, puis la séance se ferme quand sa voix est finie', () => {
    const closing = make();
    run(closing);
    assert.equal(closing.concluding, true);

    // Avant la phrase de fin : micro ouvert.
    assert.equal(closing.teacherSaid('On va s\'arrêter ici pour aujourd\'hui. Vous avez bien travaillé. ', 990), false);
    assert.equal(closing.micOpen, true);
    assert.equal(closing.shouldFinish(992, true), false);

    // « À demain ! » reconnu même découpé en morceaux de transcription.
    assert.equal(closing.teacherSaid('À de', 1000), false);
    assert.equal(closing.teacherSaid('main !', 1000.5), true);
    assert.equal(closing.micOpen, false);
    assert.equal(closing.shouldFinish(1001, true), false);
    closing.turnComplete(1002);
    assert.equal(closing.shouldFinish(1002.5, false), false);
    assert.equal(closing.shouldFinish(1003, false), true);
});

test('« À demain » dit avant l\'indication de conclusion ne ferme rien', () => {
    const closing = make();
    assert.equal(closing.teacherSaid('À demain !', 10), false);
    assert.equal(closing.micOpen, true);
    assert.equal(closing.shouldFinish(20, false), false);
});

test('jamais de Teacher Joe muet à l\'écran : sans un mot après l\'indication, la séance se ferme', () => {
    const closing = make();
    run(closing);
    const asked = 960; // indication de conclusion envoyée à 40 secondes de la fin

    assert.equal(closing.shouldFinish(asked + 5, false), false);
    // Il agit (bilan enregistré, voix reçue) : on attend.
    closing.heard(asked + 6);
    assert.equal(closing.shouldFinish(asked + 12, false), false);
    assert.equal(closing.shouldFinish(asked + 16, false), true);
    // Sa réponse se prépare encore : on attend aussi.
    assert.equal(closing.shouldFinish(asked + 16, true), false);
});

test('minuteur à zéro pendant qu\'il parle : sa phrase finit, puis la conclusion est demandée, puis on ferme', () => {
    const closing = make();
    // Aucune indication encore partie (par exemple après une coupure de connexion).
    assert.equal(closing.timeUp(2000, true), 'wait');
    assert.equal(closing.timeUp(2005, true), 'wait');
    assert.equal(closing.timeUp(2006, false), 'conclude');
    assert.equal(closing.concluding, true);
    assert.equal(closing.timeUp(2020, true), 'wait');
    assert.equal(closing.timeUp(2030, false), 'finish');
});

test('phrase sans fin au minuteur : au plus quelques secondes, puis conclusion', () => {
    const closing = make();
    assert.equal(closing.timeUp(3000, true), 'wait');
    assert.equal(closing.timeUp(3008, true), 'conclude');
});

test('bilan déjà enregistré (séance du jour) : la conclusion n\'est jamais coupée au milieu par le minuteur', () => {
    const closing = make();
    closing.startConclusion(500);
    assert.equal(closing.next(30, false, 501), null);
    assert.equal(closing.timeUp(510, true), 'wait');
    assert.equal(closing.timeUp(539, true), 'wait');
    assert.equal(closing.timeUp(540, true), 'finish');
});
