// Fin automatique après le verdict (public/js/conversation-end.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ConversationEnd, normalizeSpeech } from '../../public/js/conversation-end.js';

test('sans verdict, la conversation continue (jusqu\'au minuteur)', () => {
    const end = new ConversationEnd();
    end.turnComplete(10);
    assert.equal(end.shouldFinish(500, false), false);
});

test('après le verdict : on attend la fin de l\'annonce, puis on coupe', () => {
    const end = new ConversationEnd({ settle: 1 });

    end.recordVerdict(100);
    assert.equal(end.shouldFinish(101, true), false, 'il annonce le niveau');
    end.turnComplete(104);
    assert.equal(end.shouldFinish(105, true), false, 'sa voix sort encore du haut-parleur');
    assert.equal(end.shouldFinish(108, false), true, 'annonce finie : fin de la conversation');
});

test('le verdict n\'est compté qu\'une fois (deuxième appel du modèle ignoré)', () => {
    const end = new ConversationEnd({ settle: 1 });

    end.recordVerdict(100);
    end.recordVerdict(130);
    end.turnComplete(102);
    assert.equal(end.shouldFinish(103.5, false), true);
});

test('si le modèle ne dit plus rien ou parle sans fin, on coupe quand même', () => {
    const silent = new ConversationEnd({ maxWait: 25, hardStop: 45 });
    silent.recordVerdict(0);
    assert.equal(silent.shouldFinish(24, false), false);
    assert.equal(silent.shouldFinish(25, false), true);

    const talkative = new ConversationEnd({ maxWait: 25, hardStop: 45 });
    talkative.recordVerdict(0);
    assert.equal(talkative.shouldFinish(44, true), false);
    assert.equal(talkative.shouldFinish(45, true), true);
});

test('doublon reproduit : après le verdict, plus aucune indication ni micro ouvert', () => {
    // Avant ce correctif, l'indication « il reste 1 minute » partait aussi après l'annonce
    // du niveau : le professeur réannonçait le niveau puis reposait une question.
    const end = new ConversationEnd();
    assert.equal(end.acceptsCues, true);
    assert.equal(end.micOpen, true);

    end.recordVerdict(100);
    assert.equal(end.acceptsCues, false);
    assert.equal(end.micOpen, false);
});

/* ---------- Évaluation : questions libres puis conclusion qui finit par la phrase fixe ---------- */

const CLOSING = 'On se retrouve de l\'autre côté !';

function assessmentEnd() {
    return new ConversationEnd({ settle: 1, closingPhrase: CLOSING, closingWait: 20, cueSeconds: 30, grace: 6 });
}

test('la phrase fixe est reconnue malgré les accents, l\'apostrophe courbe et les morceaux de transcription', () => {
    assert.equal(normalizeSpeech('On se retrouve de l’autre côté !'), 'on se retrouve de l autre cote');

    const end = assessmentEnd();
    end.recordVerdict(0, { questions: true });
    assert.equal(end.teacherSaid(' Je serai avec vous chaque jour. On se retr', 10), false);
    assert.equal(end.teacherSaid('ouve de l’autre cote !', 11), true, 'mot coupé entre deux morceaux');
    assert.equal(end.teacherSaid(' On se retrouve de l\'autre côté !', 12), false, 'détectée une seule fois');
});

test('après le verdict : une seule annonce, micro ouvert pendant les questions, aucune indication ordinaire', () => {
    const end = assessmentEnd();
    end.recordVerdict(100, { questions: true });
    end.recordVerdict(130, { questions: false }); // Deuxième appel du modèle : ignoré.

    assert.equal(end.verdictAt, 100);
    assert.equal(end.questions, true);
    assert.equal(end.inQuestions, true);
    assert.equal(end.micOpen, true, 'la personne peut poser ses questions');
    assert.equal(end.acceptsCues, false, 'pas de « il reste N minutes » ni de reprise');

    // Annonce du niveau puis réponses aux questions : rien ne coupe la conversation.
    end.turnComplete(110);
    end.turnComplete(150);
    assert.equal(end.shouldFinish(200, false), false);
    assert.equal(end.shouldFinish(500, false), false, 'seule la phrase fixe (ou la limite) termine');
});

test('la phrase fixe avant le verdict ou sans moment des questions est ignorée', () => {
    const before = assessmentEnd();
    assert.equal(before.teacherSaid(CLOSING, 5), false);

    const late = assessmentEnd(); // Verdict arrivé après la coupure du serveur : pas de questions.
    late.recordVerdict(0, { questions: false });
    assert.equal(late.teacherSaid(CLOSING, 1), false);
    assert.equal(late.micOpen, false);
});

test('phrase fixe entendue : micro fermé, coupure une fois la voix réellement terminée', () => {
    const end = assessmentEnd();
    end.recordVerdict(0, { questions: true });
    end.turnComplete(10); // Fin de l'annonce : ne compte pas.
    end.teacherSaid(`Je serai avec vous chaque jour. ${CLOSING}`, 60);

    assert.equal(end.micOpen, false, 'fermé définitivement après la conclusion');
    assert.equal(end.inQuestions, false, 'le minuteur quitte « Vos questions »');
    assert.equal(end.shouldFinish(61, true), false, 'la voix sort encore du haut-parleur');
    end.turnComplete(61.5);
    assert.equal(end.shouldFinish(63, true), false, 'toujours la voix (reçue plus vite que jouée)');
    assert.equal(end.shouldFinish(64, false), true, 'voix finie depuis plus d\'une seconde après la fin du tour');
});

test('phrase fixe entendue sans fin de tour : coupure peu après la fin de la voix, ou au plus tard 20 secondes après', () => {
    const quiet = assessmentEnd();
    quiet.recordVerdict(0, { questions: true });
    quiet.teacherSaid(CLOSING, 50);
    assert.equal(quiet.shouldFinish(52, false), false);
    assert.equal(quiet.shouldFinish(54, false), true);

    const endless = assessmentEnd();
    endless.recordVerdict(0, { questions: true });
    endless.teacherSaid(CLOSING, 50);
    assert.equal(endless.shouldFinish(69, true), false);
    assert.equal(endless.shouldFinish(70, true), true);
});

test('UNE seule indication de conclusion, environ 30 secondes avant la fin, pas pendant qu\'il parle', () => {
    const end = assessmentEnd();
    end.recordVerdict(0, { questions: true });

    assert.equal(end.shouldConclude(90, false), false, 'trop tôt');
    assert.equal(end.shouldConclude(30, true), false, 'il parle : on attend');
    assert.equal(end.shouldConclude(20, true), false);
    assert.equal(end.shouldConclude(15, true), true, 'à la moitié du délai, on l\'envoie quand même');
    assert.equal(end.shouldConclude(10, false), false, 'jamais une deuxième fois');
    assert.equal(end.micOpen, false, 'conclusion demandée : micro fermé');
});

test('pas d\'indication de conclusion si elle est déjà faite, ni sans moment des questions', () => {
    const done = assessmentEnd();
    done.recordVerdict(0, { questions: true });
    done.teacherSaid(CLOSING, 40);
    assert.equal(done.shouldConclude(25, false), false);

    const lesson = new ConversationEnd();
    lesson.recordVerdict(0);
    assert.equal(lesson.shouldConclude(25, false), false);
});

test('limite de temps : la phrase en cours finit (6 secondes au plus), puis coupure ; ailleurs, coupure immédiate', () => {
    const end = assessmentEnd();
    end.recordVerdict(0, { questions: true });
    assert.equal(end.timeUp(120, true), false, 'il termine sa phrase');
    assert.equal(end.timeUp(123, true), false);
    assert.equal(end.timeUp(124, false), true, 'voix finie : on coupe');

    const longer = assessmentEnd();
    longer.recordVerdict(0, { questions: true });
    longer.timeUp(120, true);
    assert.equal(longer.timeUp(126, true), true, 'filet de sécurité : 6 secondes au plus');

    const before = assessmentEnd(); // Pas encore de verdict : la limite des 5 minutes coupe tout de suite.
    assert.equal(before.timeUp(300, true), true);
    assert.equal(new ConversationEnd().timeUp(10, true), true, 'séance : coupure immédiate');
});
