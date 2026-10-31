// Bandeau « À vous de parler » (public/js/answer-hint.js) : l'exemple suit la dernière question réellement posée.
// Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { answerHint, lastQuestion, listeningText } from '../../public/js/answer-hint.js';

const welcome = "Hello! I'm Teacher Rose, your English teacher at Talkami. Votre évaluation orale gratuite de 5 minutes commence maintenant. "
    + "Répondez comme vous pouvez. Si quelque chose n'est pas clair ou si vous avez un souci, dites-le-moi à tout moment, même en français : je suis là pour vous aider. ";

test('le cas signalé : après « How are you today? », jamais « My name is … »', () => {
    const text = "Nice to meet you, Afi! How are you today?";

    assert.equal(answerHint(text), "I'm fine, thank you.");
    assert.equal(listeningText(text, { firstAnswer: true }), 'À vous de parler : par exemple « I\'m fine, thank you. »');
    assert.doesNotMatch(listeningText(text, { firstAnswer: true }), /My name is/);
});

test('premier tour de l\'évaluation : la question du nom donne « My name is … »', () => {
    assert.equal(answerHint(`${welcome}What's your name?`), 'My name is …');
    assert.equal(listeningText(`${welcome}What's your name?`, { firstAnswer: true }), 'À vous de parler : par exemple « My name is … »');
    // Apostrophe typographique et majuscules de la transcription.
    assert.equal(answerHint('WHAT’S YOUR NAME?'), 'My name is …');
});

test('chaque question connue a son exemple', () => {
    const cases = {
        'Where do you live?': 'I live in …',
        'And where are you from?': "I'm from …",
        'How old are you?': "I'm … years old.",
        'What do you do?': "I'm a …",
        'Why do you want to learn English?': 'I want to learn English because …',
        'What do you like to do on weekends?': 'I like …',
        'Comment vous appelez-vous ?': 'My name is …',
    };

    for (const [question, example] of Object.entries(cases)) {
        assert.equal(answerHint(`Great! ${question}`), example, question);
    }
});

test('seule la DERNIÈRE question compte', () => {
    assert.equal(answerHint("What's your name? Oh, sorry. Where do you live?"), 'I live in …');
    assert.equal(answerHint("Thank you, Afi. How are you today?"), "I'm fine, thank you.");
});

test('pas sûr : aucun exemple, seulement le conseil général', () => {
    // Aucune question.
    assert.equal(answerHint('Very good. Repeat after me: I live in Lomé.'), null);
    // Question inconnue.
    assert.equal(answerHint('What did you eat this morning?'), null);
    // Deux questions dans la même phrase.
    assert.equal(answerHint("What's your name and where do you live?"), null);
    // Consigne après la question : l'élève doit répéter, pas répondre.
    assert.equal(answerHint('How are you today? Repeat after me: I am fine.'), null);
    // « How are you going to … » n'est pas « How are you? ».
    assert.equal(answerHint('How are you going to travel?'), null);
    // « What do you do on Sundays? » n'est pas le métier.
    assert.equal(answerHint('What do you do on Sundays?'), null);
    assert.equal(listeningText('What did you eat?', { firstAnswer: true }), 'À vous de parler : répondez à voix haute');
    assert.equal(listeningText('What did you eat?'), 'À vous de parler');
    assert.equal(listeningText(''), 'À vous de parler');
});

test('lastQuestion lit la dernière phrase interrogative', () => {
    assert.equal(lastQuestion('Hello. How are you today?'), 'how are you today');
    assert.equal(lastQuestion('How are you today? Take your time.'), 'how are you today');
    assert.equal(lastQuestion('No question here.'), null);
});
