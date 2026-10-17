/*
 * Expressions du professeur 3D déduites de ses paroles (sous-titres), sans appel
 * de fonction à Gemini : l'expression arrive au moment où il dit la phrase.
 * Testé par tests/js/teacher-cues.test.mjs.
 */

// Dans l'ordre de priorité : la première trouvée l'emporte.
const CUES = [
    ['goodbye', /\b(good ?bye|bye|see you|au revoir)\b/i],
    ['happy', /\b(great|well done|well said|excellent|perfect|good job|very good|bravo|congratulations|fantastic|wonderful|that's right|exactly)\b/i],
    ['attentive', /\b(you said|we say|we usually say|on dit|say it again|repeat after me|listen)\b/i],
    ['encouraging', /\b(don't worry|no problem|take your time|it's ok(ay)?|good try|you can do it|nice try|pas de souci|essayez)\b/i],
    ['thinking', /\b(let me think|let's see|hmm+|interesting)\b/i],
    ['welcome', /\b(hello|hi|welcome|good (morning|afternoon|evening))\b/i],
];

export function expressionFor(text) {
    for (const [name, pattern] of CUES) {
        if (pattern.test(text)) {
            return name;
        }
    }

    return null;
}

/**
 * Suit la phrase en cours du professeur et donne une expression au plus une
 * fois par phrase (les sous-titres arrivent par petits morceaux).
 */
export class CueTracker {
    constructor() {
        this.reset();
    }

    /** Nouvelle réplique du professeur. */
    reset() {
        this.text = '';
        this.shown = new Set();
    }

    /**
     * @return {string|null} expression à montrer maintenant
     */
    feed(chunk) {
        this.text += chunk;
        // Seule la phrase en cours compte (après le dernier point, point d'interrogation…).
        const sentences = this.text.split(/(?<=[.!?])\s+/);
        const expression = expressionFor(sentences.slice(-2).join(' '));

        if (!expression || this.shown.has(expression)) {
            return null;
        }

        this.shown.add(expression);

        return expression;
    }
}
