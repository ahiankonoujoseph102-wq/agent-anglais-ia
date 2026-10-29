// « Écouter en séance » (public/js/voice-live-sample.js) : WAV recomposé à partir de la voix reçue, et dialogue avec
// un faux Gemini Live (configuration envoyée, phrase demandée, voix gardée jusqu'à la fin du tour). Lancé par
// tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { rateFrom, speakWithGemini, wavFromPcm } from '../../public/js/voice-live-sample.js';

test('le WAV a un en-tête juste et toute la voix reçue', async () => {
    const wav = wavFromPcm([new Uint8Array([1, 0, 2, 0]).buffer, new Uint8Array([3, 0]).buffer], 24000);
    const bytes = new Uint8Array(await wav.arrayBuffer());
    const view = new DataView(bytes.buffer);

    assert.equal(new TextDecoder().decode(bytes.slice(0, 4)), 'RIFF');
    assert.equal(view.getUint32(24, true), 24000);
    assert.equal(view.getUint32(40, true), 6);
    assert.deepEqual([...bytes.slice(44)], [1, 0, 2, 0, 3, 0]);
    assert.equal(rateFrom('audio/pcm;rate=16000'), 16000);
    assert.equal(rateFrom(undefined), 24000);
});

class FakeGemini {
    static OPEN = 1;

    constructor(url) {
        this.url = url;
        this.sent = [];
        FakeGemini.last = this;
        setTimeout(() => this.onopen?.(), 0);
    }

    send(data) {
        const message = JSON.parse(data);
        this.sent.push(message);

        if (message.setup) {
            setTimeout(() => this.onmessage?.({ data: JSON.stringify({ setupComplete: {} }) }), 0);
        }

        if (message.realtimeInput?.text === 'START') {
            const audio = Buffer.from([5, 0, 6, 0]).toString('base64');
            setTimeout(() => {
                this.onmessage?.({ data: JSON.stringify({ serverContent: { modelTurn: { parts: [{ inlineData: { mimeType: 'audio/pcm;rate=24000', data: audio } }] } } }) });
                this.onmessage?.({ data: JSON.stringify({ serverContent: { turnComplete: true } }) });
            }, 0);
        }
    }

    close() {
        this.closed = true;
    }
}

test('la phrase est demandée après la configuration, sans micro, et la voix reçue est gardée', async () => {
    const wav = await speakWithGemini({ websocket_url: 'wss://faux', setup: { model: 'm' } }, { WebSocketImpl: FakeGemini });
    const bytes = new Uint8Array(await wav.arrayBuffer());

    assert.deepEqual(FakeGemini.last.sent.map((message) => Object.keys(message)[0]), ['setup', 'realtimeInput']);
    assert.ok(!FakeGemini.last.sent.some((message) => message.realtimeInput?.audio), 'aucun son de micro envoyé');
    assert.deepEqual([...bytes.slice(44)], [5, 0, 6, 0]);
    assert.ok(FakeGemini.last.closed);
});
