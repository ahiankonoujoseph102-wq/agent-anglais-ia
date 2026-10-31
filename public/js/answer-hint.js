/**
 * Bandeau « À vous de parler » : l'exemple de réponse suit la DERNIÈRE question réellement posée par le professeur
 * (sa transcription). Règle : jamais un exemple faux. Si la question n'est pas reconnue avec certitude (aucune
 * question, question inconnue, deux questions dans la même phrase, ou consigne après la question comme « Repeat
 * after me »), pas d'exemple : seulement un conseil général. Fonctions pures, testées par tests/js/answer-hint.test.mjs.
 */

// Questions reconnues et leur exemple de réponse (anglais américain, niveau débutant). Une seule réponse sûre chacune.
export const HINTS = [
    { example: 'My name is …', patterns: [/\bwhat'?s your (first )?name\b/, /\bwhat is your (first )?name\b/, /\bcan you tell me your name\b/, /\bmay i (have|know) your name\b/, /\bcomment vous appelez[- ]vous\b/, /\bquel est votre (pre)?nom\b/] },
    { example: "I'm fine, thank you.", patterns: [/\bhow are you( doing)?( today| this morning| this afternoon| this evening| now)?\s*$/, /\bhow'?s it going\b/, /\bhow is it going\b/, /\bcomment allez[- ]vous\b/] },
    { example: 'I live in …', patterns: [/\bwhere do you live\b/] },
    { example: "I'm from …", patterns: [/\bwhere are you from\b/, /\bwhere do you come from\b/] },
    { example: "I'm … years old.", patterns: [/\bhow old are you\b/] },
    { example: "I'm a …", patterns: [/\bwhat do you do( for a living)?\s*$/, /\bwhat'?s your job\b/, /\bwhat is your job\b/] },
    { example: 'I want to learn English because …', patterns: [/\bwhy do you want to (learn|speak|study|improve)( your)? english\b/] },
    { example: 'I like …', patterns: [/\bwhat do you like to do\b/, /\bwhat are your hobbies\b/, /\bwhat do you do in your free time\b/] },
];

// Après la question, une de ces consignes change ce que l'élève doit dire : pas d'exemple.
const INSTRUCTION = /\b(repeat|say|your turn|try|listen|read|repetez|dites|ecoutez|essayez)\b/;

/** Minuscules, sans accents, apostrophes droites, espaces simples. */
export function normalize(text) {
    return String(text ?? '')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[’‘`]/g, "'")
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Dernière question de ce tour du professeur, ou null : la dernière phrase terminée par « ? », à condition que la
 * suite (s'il y en a une) soit courte et ne donne aucune consigne.
 */
export function lastQuestion(teacherText) {
    const text = normalize(teacherText);
    const end = text.lastIndexOf('?');

    if (end < 0) {
        return null;
    }

    const after = text.slice(end + 1).trim();

    if (after !== '' && (after.split(' ').length > 6 || INSTRUCTION.test(after))) {
        return null;
    }

    const before = text.slice(0, end);
    const start = Math.max(before.lastIndexOf('.'), before.lastIndexOf('!'), before.lastIndexOf('?'), before.lastIndexOf(':')) + 1;

    return before.slice(start).trim();
}

/** Exemple de réponse à la dernière question du professeur (« My name is … »), ou null si ce n'est pas sûr. */
export function answerHint(teacherText) {
    const question = lastQuestion(teacherText);

    if (!question) {
        return null;
    }

    const found = HINTS.filter((hint) => hint.patterns.some((pattern) => pattern.test(question)));

    // Deux questions reconnues dans la même phrase (« What's your name and where do you live? ») : pas d'exemple.
    return found.length === 1 ? found[0].example : null;
}

/**
 * Texte du bandeau quand c'est à l'élève de parler. Toujours « À vous de parler » (le mot attendu par les conseils
 * de l'écran), puis l'exemple s'il est sûr ; sinon, au tout début, le conseil général.
 */
export function listeningText(teacherText, { firstAnswer = false } = {}) {
    const hint = answerHint(teacherText);

    if (hint) {
        return `À vous de parler : par exemple « ${hint} »`;
    }

    return firstAnswer ? 'À vous de parler : répondez à voix haute' : 'À vous de parler';
}
