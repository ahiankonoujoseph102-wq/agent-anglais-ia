// Tableau de la séance du jour (public/js/lesson-board.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { BoardTracker, BoardView, keysFor, normalizeSpeech, phaseNote } from '../../public/js/lesson-board.js';

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

test('changement de temps : annonce courte avec le nom du professeur, rien au début de la séance', () => {
    assert.equal(phaseNote('lesson', 'Teacher Joe'), null);
    assert.equal(phaseNote('practice', 'Teacher Joe'), 'Maintenant : la pratique. Répétez et complétez les phrases avec Teacher Joe.');
    assert.match(phaseNote('conversation', 'Teacher Joe'), /^Maintenant : la conversation\. Parlez avec Teacher Joe/);
    assert.match(phaseNote('summary', 'Teacher Joe'), /^Maintenant : le bilan\. Teacher Joe résume/);
    assert.doesNotMatch(Object.values(['practice', 'conversation', 'summary'].map((p) => phaseNote(p, 'X'))).join(' '), /\bmin\b/);
});

test('le tableau dit quand la séance passe à un autre temps (pas au premier affichage)', () => {
    const root = { dataset: {}, querySelector: () => null, querySelectorAll: () => [] };
    const view = new BoardView(root);

    assert.equal(view.setPhase('lesson'), false);
    assert.equal(view.setPhase('lesson'), false);
    assert.equal(view.setPhase('practice'), true);
    assert.equal(root.dataset.phase, 'practice');
    assert.equal(view.setPhase('practice'), false);
    assert.equal(view.setPhase('summary'), true);
});

// Séance réelle du 2 novembre 2026 : « Let's sum up! » à la quatrième minute, « À demain ! » à la huitième.
test('un bilan annoncé bien trop tôt ramène le professeur à l\'étape prévue', () => {
    const board = new BoardTracker(items, { ...phases, endings: ['À demain', 'See you'] });

    board.feed("Fantastic! Let's sum up! You can now greet guests. ");
    assert.equal(board.phase(240), 'summary');
    assert.equal(board.earlyMove(240), 'lesson');
    assert.equal(board.phase(240), 'lesson');
    // Une seule fois pour ce bilan-là.
    assert.equal(board.earlyMove(250), null);

    // Au revoir bien avant le bilan : retour à l'étape prévue par le temps (ici la conversation).
    board.feed("On va s'arrêter ici pour aujourd'hui. À demain ! ");
    assert.equal(board.earlyMove(480 + 100), 'conversation');
    assert.equal(board.earlyMove(480 + 110), null);
});

test('le bilan et l\'au revoir à leur heure ne sont jamais corrigés', () => {
    const board = new BoardTracker(items, { ...phases, endings: ['À demain'] });

    board.feed("Now, let's practice! ");
    assert.equal(board.earlyMove(290), null);
    board.feed("Let's sum up! À demain ! ");
    assert.equal(board.earlyMove(760), null);
    assert.equal(board.earlyMove(800), null);
});

test('au plus trois corrections par séance', () => {
    const board = new BoardTracker(items, { ...phases, endings: ['See you'] });

    for (let i = 0; i < 3; i += 1) {
        board.feed('See you! ');
        assert.equal(board.earlyMove(60 + i), 'lesson');
    }

    board.feed('See you! ');
    assert.equal(board.earlyMove(70), null);
});

test("l'élément allumé vient tout seul au tiers haut du tableau (seul le tableau défile)", async () => {
    const { followTop } = await import('../../public/js/lesson-board.js');
    // Tableau de 300 pixels de haut, élément de 30 pixels à 600 pixels du haut : placé à 90 pixels du haut visible.
    assert.equal(followTop(600, 30, 300), 510);
    // Élément tout en haut : jamais une position négative après Math.max (faite par l'appelant).
    assert.ok(followTop(10, 30, 300) < 0);
    // Élément plus haut que le tableau : son début reste visible.
    assert.equal(followTop(400, 500, 300), 400);
});

test('light() fait défiler le tableau, jamais la page', () => {
    const calls = [];
    const body = { scrollTo: (options) => calls.push(options), clientHeight: 300 };
    const el = {
        dataset: { boardItem: 'p1', say: 'Hello' }, offsetTop: 600, offsetHeight: 30,
        classList: { add() {}, remove() {} }, closest: (selector) => (selector === '.board-body' ? body : null),
    };
    const root = { querySelector: () => null, querySelectorAll: (selector) => (selector === '[data-board-item]' ? [el] : []) };
    const view = new BoardView(root);
    view.light('p1');
    assert.equal(calls.length, 1);
    assert.equal(calls[0].top, 510);
});
