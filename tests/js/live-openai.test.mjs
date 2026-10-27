// Adaptateur OpenAI Realtime (public/js/live-openai.js), avec un faux serveur : fausse connexion WebRTC,
// faux canal « oai-events » et faux Laravel (/en-direct/appel). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { OpenAiTransport } from '../../public/js/live-openai.js';
import { ConversationEnd } from '../../public/js/conversation-end.js';

class FakeParam {
    constructor(value) {
        this.value = value;
        this.ramps = [];
    }

    cancelScheduledValues() {}

    setValueAtTime(value) {
        this.value = value;
    }

    linearRampToValueAtTime(value, time) {
        this.ramps.push([value, time]);
        this.value = value;
    }
}

class FakeNode {
    constructor() {
        this.gain = new FakeParam(1);
        this.targets = [];
    }

    connect(node) {
        this.targets.push(node);

        return node;
    }

    disconnect() {
        this.targets = [];
    }
}

function fakeAudio() {
    const micTrack = { kind: 'audio', id: 'micro' };
    const context = {
        currentTime: 3,
        createGain: () => new FakeNode(),
        createMediaStreamDestination: () => Object.assign(new FakeNode(), { stream: { getAudioTracks: () => [micTrack] } }),
    };

    return { context, micBus: new FakeNode(), micTrack };
}

class FakeChannel {
    constructor(label) {
        this.label = label;
        this.readyState = 'connecting';
        this.sent = [];
    }

    send(text) {
        this.sent.push(JSON.parse(text));
    }

    open() {
        this.readyState = 'open';
        this.onopen?.();
    }

    emit(event) {
        this.onmessage?.({ data: JSON.stringify(event) });
    }

    close() {
        if (this.readyState !== 'closed') {
            this.readyState = 'closed';
            this.onclose?.();
        }
    }
}

// Faux serveur : chaque connexion créée est gardée pour que le test joue le rôle d'OpenAI.
function fakeServer({ answer = { ok: true, status: 200, data: { sdp: 'v=0\r\nreponse' } }, openChannel = true } = {}) {
    const server = { peers: [], posts: [] };

    server.RTCPeerConnectionImpl = class {
        constructor() {
            this.tracks = [];
            this.closed = false;
            this.connectionState = 'new';
            server.peers.push(this);
        }

        addTrack(track) {
            this.tracks.push(track);
        }

        createDataChannel(label) {
            this.channel = new FakeChannel(label);

            return this.channel;
        }

        async createOffer() {
            return { type: 'offer', sdp: 'v=0\r\noffre' };
        }

        async setLocalDescription(description) {
            this.local = description;
        }

        async setRemoteDescription(description) {
            this.remote = description;

            if (openChannel) {
                setImmediate(() => this.channel.open());
            }
        }

        close() {
            this.closed = true;
            this.channel?.close();
        }
    };

    server.post = async (url, body) => {
        server.posts.push({ url, body });

        return answer;
    };

    return server;
}

function recorder() {
    const calls = [];
    const handlers = new Proxy({}, {
        get: (target, name) => (...args) => calls.push([name, ...args]),
    });

    return { calls, handlers, names: () => calls.map(([name]) => name) };
}

async function connected(options = {}) {
    const server = fakeServer(options);
    const audio = fakeAudio();
    const events = recorder();
    const transport = new OpenAiTransport({ call_url: '/en-direct/appel/abc' }, events.handlers, {
        post: server.post,
        context: audio.context,
        micBus: audio.micBus,
        RTCPeerConnectionImpl: server.RTCPeerConnectionImpl,
        createAudioElement: () => ({ play: async () => {} }),
        setTimer: (fn) => setTimeout(fn, 50),
    });
    await transport.connect();

    return { server, audio, events, transport, peer: server.peers[0], channel: server.peers[0].channel };
}

test('la description WebRTC part vers Laravel (jamais vers OpenAI) et la séance est prête quand le canal s\'ouvre', async () => {
    const { server, events, transport, peer, channel, audio } = await connected();

    assert.deepEqual(server.posts, [{ url: '/en-direct/appel/abc', body: { sdp: 'v=0\r\noffre' } }]);
    assert.deepEqual(peer.remote, { type: 'answer', sdp: 'v=0\r\nreponse' });
    assert.equal(channel.label, 'oai-events');
    assert.deepEqual(peer.tracks, [audio.micTrack], 'le micro passe par la porte avant la piste envoyée');
    assert.equal(transport.gate.gain.value, 0, 'porte du micro fermée au départ');
    assert.deepEqual(events.names(), ['onReady']);
    assert.equal(transport.isOpen, true);
    assert.equal(transport.streamsAudio, true);
});

test('appel refusé par le serveur : la connexion échoue avant toute parole, avec la cause, et tout est fermé', async () => {
    const server = fakeServer({ answer: { ok: false, status: 503, data: { message: 'Le professeur est momentanément indisponible.', quota: true } } });
    const audio = fakeAudio();
    const events = recorder();
    const transport = new OpenAiTransport({ call_url: '/x' }, events.handlers, { post: server.post, ...audio, RTCPeerConnectionImpl: server.RTCPeerConnectionImpl });

    await assert.rejects(transport.connect(), (error) => error.status === 503 && error.quota === true && /indisponible/.test(error.serverMessage));
    assert.equal(server.peers[0].closed, true);
    assert.equal(events.names().includes('onReady'), false);
    assert.equal(events.names().includes('onClose'), false, 'pas de fermeture signalée : l\'écran décide (autre fournisseur)');
});

test('canal jamais ouvert : échec après le délai, sans attendre indéfiniment', async () => {
    const server = fakeServer({ openChannel: false });
    const audio = fakeAudio();
    const transport = new OpenAiTransport({ call_url: '/x' }, {}, { post: server.post, ...audio, RTCPeerConnectionImpl: server.RTCPeerConnectionImpl, setTimer: (fn) => setTimeout(fn, 20) });

    await assert.rejects(transport.connect(), /timeout/);
    assert.equal(server.peers[0].closed, true);
});

test('les événements d\'OpenAI deviennent ceux de l\'écran (sous-titres, voix, tours, consommation)', async () => {
    const { events, transport, channel, audio } = await connected();
    events.calls.length = 0;

    channel.emit({ type: 'input_audio_buffer.speech_started' });
    channel.emit({ type: 'input_audio_buffer.speech_stopped', item_id: 'item_1' });
    channel.emit({ type: 'response.created' });
    channel.emit({ type: 'output_audio_buffer.started' });
    assert.equal(transport.speaking, true);
    channel.emit({ type: 'response.output_audio_transcript.delta', delta: 'Great ' });
    channel.emit({ type: 'response.output_audio_transcript.delta', delta: 'answer!' });
    channel.emit({ type: 'conversation.item.input_audio_transcription.delta', item_id: 'item_1', delta: 'I live in Kara.' });
    channel.emit({ type: 'conversation.item.input_audio_transcription.completed', item_id: 'item_1', transcript: 'I live in Kara.' });
    channel.emit({ type: 'response.done', response: { status: 'completed', output: [{ type: 'message' }], usage: { input_tokens: 120, output_tokens: 80 } } });
    audio.context.currentTime = 7.5;
    channel.emit({ type: 'output_audio_buffer.stopped' });

    assert.deepEqual(events.calls, [
        ['onLearnerStarted'],
        ['onLearnerStopped', 'item_1'],
        ['onGenerating'],
        ['onSpeechStarted'],
        ['onTeacherText', 'Great '],
        ['onTeacherText', 'answer!'],
        ['onLearnerText', 'I live in Kara.', 'item_1'],
        ['onUsage', 120, 80, { input_tokens: 120, output_tokens: 80 }],
        ['onTurnComplete'],
    ], 'la transcription complète n\'est pas ajoutée une seconde fois');
    assert.equal(transport.speaking, false);
    assert.equal(transport.stoppedAt, 7.5, 'fin de l\'envoi notée dans l\'horloge audio');
});

test('transcription sans morceaux : le texte complet est affiché une fois', async () => {
    const { events, channel } = await connected();
    events.calls.length = 0;

    channel.emit({ type: 'conversation.item.input_audio_transcription.completed', item_id: 'item_9', transcript: 'Hello teacher' });

    assert.deepEqual(events.calls, [['onLearnerText', 'Hello teacher', 'item_9']]);
});

test('un appel de fonction est relayé une fois ; sa réponse n\'est pas une fin de tour, et le résultat relance le professeur', async () => {
    const { events, transport, channel } = await connected();
    events.calls.length = 0;

    channel.emit({ type: 'response.function_call_arguments.done', call_id: 'call_1', name: 'record_assessment_result', arguments: '{"level":"B1","feedback":"Très bien."}' });
    channel.emit({ type: 'response.done', response: { status: 'completed', output: [{ type: 'function_call', name: 'record_assessment_result' }] } });

    assert.deepEqual(events.calls, [['onFunctionCall', { id: 'call_1', name: 'record_assessment_result', args: { level: 'B1', feedback: 'Très bien.' } }]]);

    transport.sendFunctionResult({ id: 'call_1', name: 'record_assessment_result' }, { result: 'saved', next_step: 'Announce once.' });
    assert.deepEqual(channel.sent, [
        { type: 'conversation.item.create', item: { type: 'function_call_output', call_id: 'call_1', output: '{"result":"saved","next_step":"Announce once."}' } },
        { type: 'response.create' },
    ]);
});

test('arguments illisibles : la fonction est relayée sans arguments (le serveur refusera)', async () => {
    const { events, channel } = await connected();
    events.calls.length = 0;

    channel.emit({ type: 'response.function_call_arguments.done', call_id: 'c', name: 'end_lesson', arguments: '{oups' });

    assert.deepEqual(events.calls, [['onFunctionCall', { id: 'c', name: 'end_lesson', args: {} }]]);
});

test('indications, contexte et interruption : les bons messages partent sur le canal', async () => {
    const { transport, channel } = await connected();

    transport.sendText('Start the lesson now.');
    transport.sendContext('CONVERSATION SO FAR: …');
    transport.cancel();
    assert.deepEqual(channel.sent, [
        { type: 'conversation.item.create', item: { type: 'message', role: 'user', content: [{ type: 'input_text', text: 'Start the lesson now.' }] } },
        { type: 'response.create' },
        { type: 'conversation.item.create', item: { type: 'message', role: 'user', content: [{ type: 'input_text', text: 'CONVERSATION SO FAR: …' }] } },
    ], 'rien à annuler : aucune réponse ni voix en cours ; le contexte ne demande pas de réponse');

    channel.sent.length = 0;
    channel.emit({ type: 'response.created' });
    channel.emit({ type: 'output_audio_buffer.started' });
    transport.cancel();
    assert.deepEqual(channel.sent, [{ type: 'response.cancel' }, { type: 'output_audio_buffer.clear' }]);
});

test('la porte du micro (demi-duplex) s\'ouvre et se ferme en fondu, sans répéter la même consigne', async () => {
    const { transport } = await connected();
    const { gain } = transport.gate;

    transport.setMicOpen(true);
    transport.setMicOpen(true);
    transport.setMicOpen(false);

    assert.deepEqual(gain.ramps.map(([value]) => value), [1, 0]);
    assert.ok(gain.ramps.every(([, time]) => time > 3 && time <= 3.011), 'fondu de 10 millisecondes');
});

test('la phrase fixe de la conclusion est reconnue dans la transcription d\'OpenAI, même découpée', async () => {
    const { events, channel } = await connected();
    const end = new ConversationEnd({ closingPhrase: 'On se retrouve de l\'autre côté !' });
    end.recordVerdict(10, { questions: true });
    events.calls.length = 0;

    for (const delta of ['Merci Ama ! On se retr', 'ouve de l’autre', ' côté !']) {
        channel.emit({ type: 'response.output_audio_transcript.delta', delta });
    }

    const heard = events.calls.filter(([name]) => name === 'onTeacherText').map(([, text]) => end.teacherSaid(text, 20));

    assert.deepEqual(heard, [false, false, true]);
    assert.equal(end.micOpen, false, 'micro fermé définitivement après la phrase fixe');
});

test('coupure en cours de séance (raccrochage du serveur à la limite) : signalée une fois ; fermeture voulue : silencieuse', async () => {
    const first = await connected();
    first.events.calls.length = 0;
    first.channel.close();
    first.channel.close();
    assert.deepEqual(first.events.calls, [['onClose', { reason: 'closed', code: 0, ready: true }]]);

    const second = await connected();
    second.events.calls.length = 0;
    second.transport.close();
    assert.deepEqual(second.events.calls, []);
    assert.equal(second.peer.closed, true);
});

test('fin de voix perdue : l\'écran peut la déclarer finie ; une erreur d\'OpenAI est signalée pour le journal', async () => {
    const { events, transport, channel, audio } = await connected();
    events.calls.length = 0;

    audio.context.currentTime = 3;
    channel.emit({ type: 'output_audio_buffer.started' });
    assert.equal(transport.speaking, true);
    assert.equal(transport.speakingSince, 3);

    // output_audio_buffer.stopped n'arrive jamais : la sécurité de l'écran (voiceLooksOver) déclare la voix finie.
    transport.voiceEnded(4.2);
    assert.equal(transport.speaking, false);
    assert.equal(transport.stoppedAt, 4.2);

    channel.emit({ type: 'error', error: { type: 'invalid_request_error', code: 'session_expired', message: '…' } });
    assert.deepEqual(events.calls.at(-1), ['onProviderError', 'session_expired']);
});
