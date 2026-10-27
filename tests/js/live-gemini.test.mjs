// Adaptateur Gemini Live (public/js/live-gemini.js), avec un faux WebSocket. Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { GeminiTransport } from '../../public/js/live-gemini.js';

class FakeSocket {
    static OPEN = 1;

    static last = null;

    constructor(url) {
        this.url = url;
        this.readyState = 0;
        this.sent = [];
        FakeSocket.last = this;
    }

    send(text) {
        this.sent.push(JSON.parse(text));
    }

    open() {
        this.readyState = FakeSocket.OPEN;
        this.onopen?.();
    }

    async emit(message) {
        await this.onmessage({ data: JSON.stringify(message) });
    }

    close() {
        this.readyState = 3;
    }
}

function recorder() {
    const calls = [];

    return { calls, handlers: new Proxy({}, { get: (t, name) => (...args) => calls.push([name, ...args]) }) };
}

test('connexion : la configuration figée est envoyée, puis « prêt » à setupComplete', async () => {
    const events = recorder();
    const transport = new GeminiTransport({ websocket_url: 'wss://gemini/ws?access_token=jeton', setup: { model: 'models/x' } }, events.handlers, { WebSocketImpl: FakeSocket });
    transport.connect();
    const ws = FakeSocket.last;
    ws.open();
    await ws.emit({ setupComplete: {} });

    assert.equal(ws.url, 'wss://gemini/ws?access_token=jeton');
    assert.deepEqual(ws.sent, [{ setup: { model: 'models/x' } }]);
    assert.deepEqual(events.calls, [['onReady']]);
    assert.equal(transport.streamsAudio, false);
});

test('les messages de Google deviennent les événements communs de l\'écran', async () => {
    const events = recorder();
    const transport = new GeminiTransport({ websocket_url: 'wss://x', setup: {} }, events.handlers, { WebSocketImpl: FakeSocket });
    transport.connect();
    const ws = FakeSocket.last;
    ws.open();

    await ws.emit({ serverContent: { modelTurn: { parts: [{ inlineData: { data: 'AAAA' } }] }, outputTranscription: { text: 'Hello' } } });
    await ws.emit({ serverContent: { inputTranscription: { text: 'Hi' } } });
    await ws.emit({ serverContent: { turnComplete: true } });
    await ws.emit({ toolCall: { functionCalls: [{ id: 'f1', name: 'end_lesson', args: { summary: 'Bien' } }] } });
    await ws.emit({ usageMetadata: { promptTokenCount: 10, responseTokenCount: 5 } });
    await ws.emit({ sessionResumptionUpdate: { resumable: true, newHandle: 'h2' } });
    await ws.emit({ goAway: {} });

    assert.deepEqual(events.calls, [
        ['onGenerating'],
        ['onAudio', 'AAAA'],
        ['onTeacherText', 'Hello'],
        ['onLearnerText', 'Hi', null],
        ['onTurnComplete'],
        ['onFunctionCall', { id: 'f1', name: 'end_lesson', args: { summary: 'Bien' } }],
        ['onUsage', 10, 5, { promptTokenCount: 10, responseTokenCount: 5 }],
        ['onResumeHandle', 'h2'],
        ['onGoAway'],
    ]);
});

test('micro, indications et résultat de fonction : mêmes messages qu\'avant l\'adaptateur', () => {
    const transport = new GeminiTransport({ websocket_url: 'wss://x', setup: {} }, {}, { WebSocketImpl: FakeSocket });
    transport.connect();
    const ws = FakeSocket.last;
    ws.open();
    ws.sent.length = 0;

    transport.sendAudio(new Uint8Array([1, 2, 3]).buffer);
    transport.endAudio();
    transport.sendText('Start.');
    transport.sendFunctionResult({ id: 'f1', name: 'end_lesson' }, { result: 'saved' });

    assert.deepEqual(ws.sent, [
        { realtimeInput: { audio: { data: 'AQID', mimeType: 'audio/pcm;rate=16000' } } },
        { realtimeInput: { audioStreamEnd: true } },
        { realtimeInput: { text: 'Start.' } },
        { toolResponse: { functionResponses: [{ id: 'f1', name: 'end_lesson', response: { result: 'saved' } }] } },
    ]);
});

test('fermeture : signalée avec « prêt » ou non (connexion impossible avant toute parole)', async () => {
    const events = recorder();
    const transport = new GeminiTransport({ websocket_url: 'wss://x', setup: {} }, events.handlers, { WebSocketImpl: FakeSocket });
    transport.connect();
    FakeSocket.last.onclose({ code: 1006, reason: '' });

    assert.deepEqual(events.calls, [['onClose', { reason: '', code: 1006, ready: false }]]);
});

test('début et fin de la parole vus par Google (voiceActivity) : transmis à l\'écran pour mesurer le tour', async () => {
    const events = recorder();
    const transport = new GeminiTransport({ websocket_url: 'wss://x', setup: {} }, events.handlers, { WebSocketImpl: FakeSocket });
    transport.connect();
    const ws = FakeSocket.last;
    ws.open();

    await ws.emit({ voiceActivity: { type: 'ACTIVITY_START' } });
    await ws.emit({ voiceActivity: { type: 'ACTIVITY_END' } });
    await ws.emit({ voiceActivity: { type: 'TYPE_UNSPECIFIED' } });

    assert.deepEqual(events.calls, [['onVoiceActivity', true], ['onVoiceActivity', false]]);
});

test('micro fermé pour signaler la fin du tour : « audioStreamEnd », le moyen prévu par Google', () => {
    const transport = new GeminiTransport({ websocket_url: 'wss://x', setup: {} }, {}, { WebSocketImpl: FakeSocket });
    transport.connect();
    const ws = FakeSocket.last;
    ws.open();
    transport.endAudio();

    assert.deepEqual(ws.sent.at(-1), { realtimeInput: { audioStreamEnd: true } });
});

test('séance reprise chez Google après un autre fournisseur : les derniers échanges partent avec l\'indication de reprise, une fois', () => {
    const transport = new GeminiTransport({ websocket_url: 'wss://x', setup: {} }, {}, { WebSocketImpl: FakeSocket });
    transport.connect();
    const ws = FakeSocket.last;
    ws.open();
    ws.sent.length = 0;

    transport.sendContext('CONVERSATION SO FAR: Learner: Hello.');
    assert.deepEqual(ws.sent, [], 'rien n\'est envoyé seul (le professeur parlerait)');

    transport.sendText('Resume.');
    transport.sendText('Next.');

    assert.deepEqual(ws.sent, [
        { realtimeInput: { text: 'CONVERSATION SO FAR: Learner: Hello.\n\nResume.' } },
        { realtimeInput: { text: 'Next.' } },
    ]);
});
