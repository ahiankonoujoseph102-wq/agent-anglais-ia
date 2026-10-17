// Fin automatique après le verdict (public/js/conversation-end.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ConversationEnd } from '../../public/js/conversation-end.js';

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
