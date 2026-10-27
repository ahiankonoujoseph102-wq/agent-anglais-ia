import { test } from 'node:test';
import assert from 'node:assert/strict';
import { geminiUsage, openAiUsage, TokenTally, DETAIL_FIELDS } from '../../public/js/token-usage.js';

test('Gemini : consignes en texte, échanges en audio, réflexion comptée en texte de sortie', () => {
    const usage = geminiUsage({
        promptTokenCount: 12000,
        responseTokenCount: 400,
        thoughtsTokenCount: 50,
        promptTokensDetails: [{ modality: 'TEXT', tokenCount: 10000 }, { modality: 'AUDIO', tokenCount: 2000 }],
        responseTokensDetails: [{ modality: 'AUDIO', tokenCount: 400 }],
    });

    assert.equal(usage.input, 12000);
    assert.equal(usage.output, 450);
    assert.deepEqual(usage.details, {
        input_text_tokens: 10000,
        input_audio_tokens: 2000,
        cached_text_tokens: 0,
        cached_audio_tokens: 0,
        output_text_tokens: 50,
        output_audio_tokens: 400,
    });
});

test('Gemini sans détail : tout est compté en audio (jamais sous-estimé)', () => {
    const usage = geminiUsage({ promptTokenCount: 900, responseTokenCount: 100 });

    assert.equal(usage.details.input_audio_tokens, 900);
    assert.equal(usage.details.input_text_tokens, 0);
    assert.equal(usage.details.output_audio_tokens, 100);
});

test('Gemini : la partie en cache est sortie du texte et de l\'audio', () => {
    const usage = geminiUsage({
        promptTokenCount: 1000,
        cachedContentTokenCount: 600,
        promptTokensDetails: [{ modality: 'TEXT', tokenCount: 800 }, { modality: 'AUDIO', tokenCount: 200 }],
        cacheTokensDetails: [{ modality: 'TEXT', tokenCount: 500 }, { modality: 'AUDIO', tokenCount: 100 }],
    });

    assert.equal(usage.details.cached_text_tokens, 500);
    assert.equal(usage.details.cached_audio_tokens, 100);
    assert.equal(usage.details.input_text_tokens, 300);
    assert.equal(usage.details.input_audio_tokens, 100);
});

test('OpenAI : texte, audio et cache tirés de input_token_details', () => {
    const usage = openAiUsage({
        input_tokens: 5000,
        output_tokens: 300,
        input_token_details: { text_tokens: 4000, audio_tokens: 1000, cached_tokens: 3500, cached_tokens_details: { text_tokens: 3000, audio_tokens: 500 } },
        output_token_details: { text_tokens: 60, audio_tokens: 240 },
    });

    assert.deepEqual(usage.details, {
        input_text_tokens: 1000,
        input_audio_tokens: 500,
        cached_text_tokens: 3000,
        cached_audio_tokens: 500,
        output_text_tokens: 60,
        output_audio_tokens: 240,
    });
});

test('cumul : tours, plus grand contexte, envoi puis remise à zéro, et remise après un échec', () => {
    const tally = new TokenTally();
    tally.add(geminiUsage({ promptTokenCount: 100, responseTokenCount: 10, promptTokensDetails: [{ modality: 'TEXT', tokenCount: 80 }] }));
    tally.add(geminiUsage({ promptTokenCount: 300, responseTokenCount: 20, promptTokensDetails: [{ modality: 'TEXT', tokenCount: 80 }] }));

    const payload = tally.take();
    assert.equal(payload.input_tokens, 400);
    assert.equal(payload.output_tokens, 30);
    assert.equal(payload.turns, 2);
    assert.equal(payload.max_context_tokens, 300);
    assert.equal(payload.input_text_tokens, 160);
    assert.equal(payload.input_audio_tokens, 240);

    assert.deepEqual(tally.take(), { input_tokens: 0, output_tokens: 0 }, 'rien à envoyer : pas de détail');

    tally.restore(payload);
    const again = tally.take();
    assert.equal(again.input_text_tokens, 160);
    assert.equal(again.turns, 2);

    for (const field of DETAIL_FIELDS) {
        assert.ok(field in again);
    }
});
