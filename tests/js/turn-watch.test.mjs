// Mesure des tours de parole (public/js/turn-watch.js), sécurité de la porte du micro (mic-gate.js) et
// enregistreur du texte (transcript.js : heure réelle, mesures, événements). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { VoiceActivity, TurnWatch, AnswerRecovery, quietMoment, learnerMetrics, teacherMetrics } from '../../public/js/turn-watch.js';
import { voiceLooksOver } from '../../public/js/mic-gate.js';
import { TranscriptRecorder } from '../../public/js/transcript.js';

// Rejoue un niveau de micro image par image (toutes les 20 millisecondes) ; renvoie les changements.
function play(vad, from, to, level) {
    const changes = [];

    for (let t = from; t < to; t += 20) {
        const change = vad.update(t, typeof level === 'function' ? level(t) : level);

        if (change) {
            changes.push(change);
        }
    }

    return changes;
}

// Voix d'une phrase : forte, avec une petite pause de 300 millisecondes toutes les 2 secondes (entre les mots).
const speech = (t) => (t % 2000 < 1700 ? 0.12 : 0.004);

test('la voix de l\'apprenant est détectée au-dessus du bruit de fond, et sa fin après une vraie pause', () => {
    const vad = new VoiceActivity();
    assert.deepEqual(play(vad, 0, 2000, 0.003), []);

    const changes = play(vad, 2000, 6000, (t) => (t < 4000 ? 0.1 : 0.003));
    assert.deepEqual(changes.map((c) => c.type), ['start', 'end']);
    assert.equal(changes[0].at, 2000);
    assert.ok(changes[1].at >= 3980 && changes[1].at < 4000, 'fin = dernier instant de voix');
});

test('un bruit de fond continu (ventilateur, rue) ne passe jamais pour de la voix', () => {
    const vad = new VoiceActivity();
    // Bruit constant au-dessus du seuil minimal : le seuil suit le bruit (le plus petit niveau des dernières secondes).
    const changes = play(vad, 0, 20000, (t) => 0.03 + 0.002 * Math.sin(t / 50));
    const last = changes.at(-1);

    assert.ok(!last || last.type === 'end', 'au bout de quelques secondes, le bruit n\'est plus de la voix');
    assert.equal(vad.speaking, false);
    assert.ok(vad.threshold > 0.06);

    // La voix de l'apprenant, plus forte que le bruit, est bien détectée par-dessus.
    const voice = play(vad, 20000, 23000, (t) => (t < 22000 ? 0.2 : 0.03));
    assert.deepEqual(voice.map((c) => c.type), ['start', 'end']);
});

test('une longue réponse de 30 secondes, avec des pauses entre les mots, reste UN seul tour sans fin de tour forcée', () => {
    const vad = new VoiceActivity();
    const turns = new TurnWatch();
    play(vad, 0, 1000, 0.003);

    for (let t = 1000; t < 31000; t += 20) {
        const change = vad.update(t, speech(t));

        if (change) {
            turns.micVoice(change.type === 'start', change.at);
        }

        assert.equal(turns.shouldEndTurn(t), false, `aucune fin de tour à ${t} millisecondes`);
        assert.equal(turns.waiting(t), null);
    }

    assert.equal(turns.turn.micStart, 1000);
    assert.equal(turns.turn.micActive, true);
});

test('le fournisseur ne clôt pas le tour (bruit de fond) : le navigateur signale la fin 3 secondes après la voix', () => {
    const turns = new TurnWatch();
    turns.micVoice(true, 1000);
    turns.micVoice(false, 9000);

    assert.equal(turns.shouldEndTurn(11999), false);
    assert.equal(turns.shouldEndTurn(12000), true);
    turns.markEndOfTurn(12000);
    assert.equal(turns.endingTurn, true);
    assert.equal(turns.shouldEndTurn(13000), false, 'une seule fois');

    // L'apprenant reprend la parole : le micro se rouvre, la fin mesurée ne compte plus.
    turns.micVoice(true, 13000);
    assert.equal(turns.endingTurn, false);
    assert.equal(turns.turn.micStart, 1000);
});

test('pas de fin de tour signalée quand le fournisseur l\'a vue, qu\'il répond déjà, ou pour un bruit très court', () => {
    const openai = new TurnWatch();
    openai.micVoice(true, 0);
    openai.providerStart(100);
    openai.micVoice(false, 5000);
    openai.providerEnd(6200);
    assert.equal(openai.shouldEndTurn(20000), false);

    const gemini = new TurnWatch();
    gemini.micVoice(true, 0);
    gemini.micVoice(false, 5000);
    gemini.providerResponding(6500);
    assert.equal(gemini.shouldEndTurn(20000), false);

    const cough = new TurnWatch();
    cough.micVoice(true, 0);
    cough.micVoice(false, 300);
    assert.equal(cough.shouldEndTurn(20000), false);
});

test('aucune réponse : « réfléchit » après 8 secondes, puis la solution après 20 secondes, une seule fois chacune', () => {
    const turns = new TurnWatch();
    turns.micVoice(true, 0);
    turns.micVoice(false, 30000);

    assert.equal(turns.waiting(37999), null);
    assert.equal(turns.waiting(38000), 'thinking');
    assert.equal(turns.warnOnce('thinking'), true);
    assert.equal(turns.warnOnce('thinking'), false);
    assert.equal(turns.waiting(50000), 'stuck');
    assert.equal(turns.warnOnce('stuck'), true);

    // Le premier son du professeur ferme le tour : plus d'attente.
    turns.teacherSound(51000);
    assert.equal(turns.waiting(60000), null);
});

test('le délai de réponse va de la fin de la parole (micro, sinon fournisseur) au premier son du professeur', () => {
    const turns = new TurnWatch();
    turns.micVoice(true, 10000);
    turns.providerStart(10300);
    turns.micVoice(false, 40000);
    turns.providerEnd(41200);
    const record = turns.teacherSound(41400);

    assert.equal(record.delay_ms, 1400);
    assert.deepEqual(learnerMetrics(record), { mic_start: 10000, mic_end: 40000, provider_start: 10300, provider_end: 41200, turn_end: 41200 });
    assert.deepEqual(teacherMetrics(record, 41300), { delay_ms: 1400, learner_end: 40000, turn_end: 41200, audio_at: 41300 });

    // Sans mesure du micro (OpenAI seul) : la fin vue par le fournisseur.
    const provider = new TurnWatch();
    provider.providerStart(0);
    provider.providerEnd(5000);
    assert.equal(provider.teacherSound(6500).delay_ms, 1500);

    // Réponse à une indication (début de séance), sans parole de l'apprenant : rien à mesurer.
    assert.equal(new TurnWatch().teacherSound(1000), null);
});

test('une réplique transcrite après la réponse appartient encore au tour qui vient de se fermer', () => {
    const turns = new TurnWatch();
    turns.micVoice(true, 1000);
    turns.micVoice(false, 31000);
    const record = turns.teacherSound(32000);

    assert.deepEqual(turns.turnFor(33000), { id: record.id, start: 1000, record: turns.last });
    assert.equal(turns.turnFor(60000), null);
});

test('sécurité de la porte du micro : voix annoncée mais plus rien d\'audible depuis 4 secondes = voix finie', () => {
    assert.equal(voiceLooksOver({ now: 10, speakingSince: 5, lastAudibleAt: 9 }), false);
    assert.equal(voiceLooksOver({ now: 13, speakingSince: 5, lastAudibleAt: 9 }), true);
    // Voix annoncée et jamais audible (événement de fin perdu dès le début).
    assert.equal(voiceLooksOver({ now: 9.5, speakingSince: 5, lastAudibleAt: 0 }), true);
    assert.equal(voiceLooksOver({ now: 8, speakingSince: 5, lastAudibleAt: 0 }), false);
});

test('une réplique reçoit plus tard son heure réelle, sa fin et ses mesures, et repart avec le même numéro', () => {
    const recorder = new TranscriptRecorder({ now: () => 50000 });
    const line = recorder.start('learner');
    recorder.append(line, 'I clean the office');
    assert.equal(recorder.takeBatch().length, 1);

    recorder.update(line, { at: 20000, end: 49000, metrics: { mic_start: 20000 } });
    recorder.update(line, { metrics: { mic_end: 49000 } });

    assert.deepEqual(TranscriptRecorder.payload(recorder.takeBatch()), [
        { seq: 1, speaker: 'learner', text: 'I clean the office', at: 20000, end: 49000, metrics: { mic_start: 20000, mic_end: 49000 } },
    ]);
});

test('les événements partent par lots, reviennent après un échec, et passent aussi par le dernier envoi de la page', () => {
    const recorder = new TranscriptRecorder({ maxEvents: 2, now: () => 1000 });
    recorder.event('gate_closed', 'voix du professeur');
    recorder.event('gate_opened');
    recorder.event('reconnect', 'automatique');
    assert.equal(recorder.hasPending, true);

    const first = recorder.takeEvents();
    assert.deepEqual(TranscriptRecorder.eventsPayload(first), [
        { kind: 'gate_closed', detail: 'voix du professeur', at: 1000 },
        { kind: 'gate_opened', detail: '', at: 1000 },
    ]);
    recorder.restore([], first);
    assert.equal(recorder.takeEvents().length, 2);
    assert.equal(recorder.takeEvents()[0].kind, 'reconnect');

    const fields = TranscriptRecorder.formFields(
        [{ seq: 3, speaker: 'teacher', text: 'Hello', at: 2000, metrics: { delay_ms: 1400 } }],
        [{ kind: 'pause', detail: '', at: 3000 }],
    );
    assert.deepEqual(fields, [
        ['lines[0][seq]', '3'], ['lines[0][speaker]', 'teacher'], ['lines[0][text]', 'Hello'], ['lines[0][at]', '2000'],
        ['lines[0][metrics][delay_ms]', '1400'],
        ['events[0][kind]', 'pause'], ['events[0][detail]', ''], ['events[0][at]', '3000'],
    ]);
});

test('reprise automatique : relance, puis nouvelle connexion, puis l\'autre fournisseur, une fois chacune', () => {
    const recovery = new AnswerRecovery({ nudgeMs: 6000, reconnectMs: 8000, switchMs: 10000 });
    const end = 1000;

    assert.equal(recovery.next(end + 5000, end), null);
    assert.equal(recovery.next(end + 6000, end), 'nudge');
    assert.equal(recovery.next(end + 6100, end), null);
    assert.equal(recovery.next(end + 14000, end), 'reconnect');
    assert.equal(recovery.next(end + 23000, end), null);
    assert.equal(recovery.next(end + 24000, end), 'switch');
    // Plus rien ensuite : le bouton « Relancer » reste le dernier recours.
    assert.equal(recovery.next(end + 60000, end), null);

    // Sa voix arrive : tout repart de zéro.
    assert.equal(recovery.next(end + 61000, null), null);
    assert.equal(recovery.next(100000 + 6000, 100000), 'nudge');
});

test('réponse coupée en plein milieu : nouvelle connexion tout de suite', () => {
    const recovery = new AnswerRecovery({ nudgeMs: 6000, reconnectMs: 8000, switchMs: 10000 });

    recovery.stalled(5000);
    assert.equal(recovery.next(5000, 5000), 'reconnect');
    assert.equal(recovery.next(15000, 5000), 'switch');
});

test('attente d\'une réponse : après une vraie parole, même un « Yes » court, jamais un claquement', () => {
    const watch = new TurnWatch({ minSpeechMs: 600 });

    watch.micVoice(true, 1000);
    watch.micVoice(false, 1100);
    assert.equal(watch.waitingSince(), null, 'un claquement de 0,1 seconde');

    const yes = new TurnWatch({ minSpeechMs: 600 });
    yes.micVoice(true, 1500);
    yes.micVoice(false, 1900);
    assert.equal(yes.waitingSince(), 1900, 'un « Yes » de 0,4 seconde');

    watch.micVoice(true, 2000);
    assert.equal(watch.waitingSince(), null, 'il parle encore');
    watch.micVoice(false, 3500);
    assert.equal(watch.waitingSince(), 3500);

    watch.teacherSound(5000);
    assert.equal(watch.waitingSince(), null);
});

test('moment calme pour changer de connexion', () => {
    const calm = { teacherSpeaking: false, responding: false, learnerSpeaking: false, awaitingAnswer: false };

    assert.equal(quietMoment(calm), true);
    for (const key of Object.keys(calm)) {
        assert.equal(quietMoment({ ...calm, [key]: true }), false, key);
    }
});
