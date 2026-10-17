// Expressions déduites des paroles (public/js/teacher-cues.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { expressionFor, CueTracker } from '../../public/js/teacher-cues.js';

test('expressions selon ce que dit le professeur', () => {
    assert.equal(expressionFor('Great answer!'), 'happy');
    assert.equal(expressionFor('Well said.'), 'happy');
    assert.equal(expressionFor('You said "I am agree". We say "I agree".'), 'attentive');
    assert.equal(expressionFor('Don\'t worry, take your time.'), 'encouraging');
    assert.equal(expressionFor('Hello Afi, welcome!'), 'welcome');
    assert.equal(expressionFor('Your level is A2. Goodbye!'), 'goodbye');
    assert.equal(expressionFor('What is your name?'), null);
});

test('une expression au plus une fois par réplique, même en petits morceaux', () => {
    const cues = new CueTracker();

    assert.equal(cues.feed('Gr'), null);
    assert.equal(cues.feed('eat ans'), 'happy');
    assert.equal(cues.feed('wer! Really great. '), null);
    assert.equal(cues.feed('Now, what do you do?'), null);

    cues.reset();
    assert.equal(cues.feed('Well done!'), 'happy');
});
