// Tableau de la séance du jour (public/js/lesson-board.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { BoardTracker, keysFor, normalizeSpeech } from '../../public/js/lesson-board.js';

const phases = {
    phrases: { practice: "Now, let's practice!", conversation: "Now, let's talk!", summary: "Let's sum up!" },
    starts: { lesson: 0, practice: 300, conversation: 480, summary: 780 },
    merged: false,
};

const phrase = (id, text) => ({ id, kind: 'phrase', keys: keysFor(text), span: normalizeSpeech(text).length });
const items = [
    phrase('p1', 'This is my sister.'),
    phrase('p2', 'Her name is Ama.'),
    phrase('p3', 'First I went to the market, then I cooked, and after that I watched the match.'),
    { id: 'v1', kind: 'word', keys: keysFor('mother / mom', 'word') },
    { id: 'v2', kind: 'word', keys: keysFor('went (go)', 'word') },
    { id: 'v3', kind: 'word', keys: keysFor('then / after that', 'word') },
    { id: 'b1', kind: 'verb', keys: keysFor('go / went / gone', 'verb') },
];

test('les clés : phrase normalisée, chaque forme d\'un mot, formes d\'un verbe', () => {
    assert.deepEqual(keysFor('This is my sister.'), ['this is my sister']);
    assert.deepEqual(keysFor("First I went to the market, then I cooked, and after that I watched."), ['first i went to the market']);
    assert.deepEqual(keysFor('mother / mom', 'word'), ['mother', 'mom']);
    assert.deepEqual(keysFor('went (go)', 'word'), ['went']);
    assert.deepEqual(keysFor('go / went / gone', 'verb'), ['go went gone', 'went', 'gone']);
    assert.equal(normalizeSpeech("Let’s SUM up!"), 'lets sum up');
});

test('un élément s\'allume quand Teacher Joe le dit, même en morceaux, et jamais au milieu d\'un mot', () => {
    const board = new BoardTracker(items, phases);

    assert.equal(board.feed('Repeat after me: This is my sis'), null);
    assert.equal(board.feed('ter. '), 'p1');
    assert.equal(board.current, 'p1');
    // « mom » dans « moment » ne compte pas.
    assert.equal(board.feed('Wait a moment. '), null);
    assert.equal(board.feed('Your mom, your mother. '), 'v1');
    assert.equal(board.feed('Go, went, gone. '), 'b1');
    // Le même élément redit plus tard se rallume.
    assert.equal(board.feed('Again: this is my sister! '), 'p1');
    // Une phrase du tableau l'emporte sur les mots qu'elle contient (« then », « went »).
    assert.equal(board.feed('Repeat after me: First I went to the market, then I cooked, and after that I watched the match. '), 'p3');
    // Le mot dit seul, ensuite, s'allume.
    assert.equal(board.feed('Then. Say it: then. '), 'v3');
});

test('l\'étape suit la phrase de passage, sinon le temps écoulé avec un retard toléré', () => {
    const board = new BoardTracker(items, phases);

    assert.equal(board.phase(10), 'lesson');
    // Le professeur reste sur la leçon (apprenant bloqué) : la pratique ne s'affiche qu'après le retard toléré.
    assert.equal(board.phase(300 + 60), 'lesson');
    assert.equal(board.phase(300 + 91), 'practice');

    const spoken = new BoardTracker(items, phases);
    spoken.feed("Great! Now, let's practice! Repeat after me.");
    assert.equal(spoken.phase(120), 'practice');
    spoken.feed("Now, let's talk! Imagine you are at the market.");
    assert.equal(spoken.phase(200), 'conversation');
    // Jamais de retour en arrière, même s'il revient un instant sur la leçon.
    spoken.feed('Remember, we said: this is my sister.');
    assert.equal(spoken.phase(210), 'conversation');
    // Le bilan suit l'heure exacte (indication de fin envoyée à 2 minutes de la fin).
    assert.equal(spoken.phase(780), 'summary');
    spoken.feed("Let's sum up! Today you learned…");
    assert.equal(spoken.phase(500), 'summary');
});

test('séance courte : leçon et pratique fusionnées', () => {
    const board = new BoardTracker(items, { ...phases, starts: { lesson: 0, practice: 120, conversation: 120, summary: 180 }, merged: true });
    board.feed("Now, let's practice!");
    assert.equal(board.phase(30), 'lesson');
    board.feed("Now, let's talk!");
    assert.equal(board.phase(40), 'conversation');
});

test('une seule relance douce vers la conversation, une minute après l\'heure prévue', () => {
    const board = new BoardTracker(items, phases);

    assert.equal(board.needsConversationNudge(480 + 30), false);
    assert.equal(board.needsConversationNudge(480 + 61), true);
    board.feed("Now, let's talk!");
    assert.equal(board.needsConversationNudge(480 + 70), false);
    // Pendant le bilan, plus de relance.
    assert.equal(new BoardTracker(items, phases).needsConversationNudge(790), false);
});
