/*
 * Jetons consommés par une séance en direct, répartis par nature (texte, audio, mis en cache), pour une estimation
 * juste du coût : chez les deux fournisseurs, les consignes (TEXTE) coûtent beaucoup moins cher que l'audio, et
 * OpenAI facture très peu ce qui est déjà en cache. Sans dépendance au navigateur : testé par
 * tests/js/token-usage.test.mjs.
 */

/** Champs envoyés au serveur avec chaque signal (mêmes noms que les colonnes des séances). */
export const DETAIL_FIELDS = [
    'input_text_tokens', 'input_audio_tokens', 'cached_text_tokens', 'cached_audio_tokens',
    'output_text_tokens', 'output_audio_tokens',
];

function count(value) {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? Math.round(number) : 0;
}

/** Somme des jetons d'une modalité dans une liste Gemini [{ modality: 'TEXT', tokenCount: 12 }, …]. */
function byModality(list, modality) {
    return (Array.isArray(list) ? list : [])
        .filter((item) => String(item?.modality ?? '').toUpperCase() === modality)
        .reduce((sum, item) => sum + count(item.tokenCount), 0);
}

/**
 * Un rapport Gemini Live (usageMetadata) : chaque tour refacture tout le contexte (consignes + échanges).
 * Ce qui n'est pas détaillé est compté comme de l'audio (le plus cher : jamais sous-estimé).
 */
export function geminiUsage(meta = {}) {
    const input = count(meta.promptTokenCount);
    const output = count(meta.responseTokenCount ?? meta.candidatesTokenCount) + count(meta.thoughtsTokenCount);
    const cachedText = byModality(meta.cacheTokensDetails, 'TEXT');
    const cachedAudio = Math.max(0, count(meta.cachedContentTokenCount) - cachedText);
    const text = Math.max(0, byModality(meta.promptTokensDetails, 'TEXT') - cachedText);
    const outputText = Math.min(output, byModality(meta.responseTokensDetails, 'TEXT') + count(meta.thoughtsTokenCount));

    return {
        input,
        output,
        details: {
            input_text_tokens: Math.min(text, input),
            input_audio_tokens: Math.max(0, input - text - cachedText - cachedAudio),
            cached_text_tokens: cachedText,
            cached_audio_tokens: cachedAudio,
            output_text_tokens: outputText,
            output_audio_tokens: Math.max(0, output - outputText),
        },
    };
}

/**
 * Un rapport OpenAI Realtime (response.usage) : input_token_details donne texte, audio et cache.
 */
export function openAiUsage(usage = {}) {
    const input = count(usage.input_tokens);
    const output = count(usage.output_tokens);
    const inputDetails = usage.input_token_details ?? {};
    const cachedDetails = inputDetails.cached_tokens_details ?? {};
    const cachedText = count(cachedDetails.text_tokens);
    const cachedAudio = Math.max(count(cachedDetails.audio_tokens), count(inputDetails.cached_tokens) - cachedText - count(cachedDetails.image_tokens));
    const text = Math.max(0, count(inputDetails.text_tokens) - cachedText);
    const outputText = Math.min(output, count(usage.output_token_details?.text_tokens));

    return {
        input,
        output,
        details: {
            input_text_tokens: Math.min(text, input),
            input_audio_tokens: Math.max(0, input - text - cachedText - cachedAudio),
            cached_text_tokens: cachedText,
            cached_audio_tokens: cachedAudio,
            output_text_tokens: outputText,
            output_audio_tokens: Math.max(0, output - outputText),
        },
    };
}

/**
 * Cumul entre deux signaux : jetons, détail, nombre de tours (rapports) et plus grand contexte d'un tour.
 */
export class TokenTally {
    constructor() {
        this.reset();
    }

    reset() {
        this.input = 0;
        this.output = 0;
        this.turns = 0;
        this.maxContext = 0;
        this.details = Object.fromEntries(DETAIL_FIELDS.map((field) => [field, 0]));
    }

    add({ input = 0, output = 0, details = null } = {}) {
        this.input += count(input);
        this.output += count(output);
        this.turns += 1;
        this.maxContext = Math.max(this.maxContext, count(input));

        for (const field of DETAIL_FIELDS) {
            // Sans détail (ancien fournisseur) : tout l'entrant est compté en audio, tout le sortant aussi.
            const fallback = field === 'input_audio_tokens' ? count(input) : (field === 'output_audio_tokens' ? count(output) : 0);
            this.details[field] += details ? count(details[field]) : fallback;
        }
    }

    /** Ce qui part avec le signal, puis remise à zéro. */
    take() {
        const payload = {
            input_tokens: this.input,
            output_tokens: this.output,
            ...(this.turns > 0 ? { ...this.details, turns: this.turns, max_context_tokens: this.maxContext } : {}),
        };
        this.reset();

        return payload;
    }

    /** Un signal refusé : ses jetons sont remis pour le suivant. */
    restore(payload) {
        if (!payload) {
            return;
        }

        this.input += count(payload.input_tokens);
        this.output += count(payload.output_tokens);

        if (count(payload.turns) > 0) {
            this.turns += count(payload.turns);
            this.maxContext = Math.max(this.maxContext, count(payload.max_context_tokens));

            for (const field of DETAIL_FIELDS) {
                this.details[field] += count(payload[field]);
            }
        }
    }
}
