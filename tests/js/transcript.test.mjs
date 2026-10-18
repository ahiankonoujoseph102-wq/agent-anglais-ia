// Texte de la conversation (public/js/transcript.js) : répliques numérotées, lots, reprise après un échec.
// Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { TranscriptRecorder } from '../../public/js/transcript.js';

test('chaque réplique a son numéro, son locuteur et son heure, dans l\'ordre', () => {
    let clock = 1000;
    const recorder = new TranscriptRecorder({ now: () => clock });
    const teacher = recorder.start('teacher');
    recorder.append(teacher, 'Hello ');
    recorder.append(teacher, 'Ama!');
    clock = 5000;
    const learner = recorder.start('learner');
    recorder.append(learner, ' Hello teacher ');

    assert.deepEqual(TranscriptRecorder.payload(recorder.takeBatch()), [
        { seq: 1, speaker: 'teacher', text: 'Hello Ama!', at: 1000 },
        { seq: 2, speaker: 'learner', text: 'Hello teacher', at: 5000 },
    ]);
    assert.equal(recorder.hasPending, false);
});

test('une réplique qui grandit repart avec le même numéro', () => {
    const recorder = new TranscriptRecorder();
    const teacher = recorder.start('teacher');
    recorder.append(teacher, 'How are');
    assert.equal(recorder.takeBatch()[0].text, 'How are');

    recorder.append(teacher, ' you?');
    const batch = TranscriptRecorder.payload(recorder.takeBatch());
    assert.deepEqual(batch.map((line) => [line.seq, line.text]), [[1, 'How are you?']]);
});

test('un lot raté est repris au prochain envoi, rien n\'est perdu', () => {
    const recorder = new TranscriptRecorder();
    recorder.append(recorder.start('learner'), 'I am fine');
    const batch = recorder.takeBatch();
    recorder.restore(batch);

    assert.equal(recorder.hasPending, true);
    assert.equal(recorder.takeBatch()[0].text, 'I am fine');
});

test('les envois sont limités en nombre de répliques et en longueur', () => {
    const recorder = new TranscriptRecorder({ maxBatch: 2, maxChars: 10 });

    for (let i = 0; i < 5; i++) {
        recorder.append(recorder.start('teacher'), 'une très longue réplique');
    }

    const first = recorder.takeBatch();
    assert.equal(first.length, 2);
    assert.equal(first[0].text.length, 10);
    assert.equal(recorder.takeBatch().length, 2);
    assert.equal(recorder.takeBatch().length, 1);
    assert.equal(recorder.takeBatch().length, 0);
});

test('une réplique vide n\'est jamais envoyée', () => {
    const recorder = new TranscriptRecorder();
    recorder.start('learner');
    recorder.append(recorder.start('teacher'), '   ');

    assert.deepEqual(recorder.takeBatch(), []);
});
