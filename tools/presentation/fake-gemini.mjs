// Faux fournisseur des séances en direct, pour le site LOCAL de démonstration des vidéos de présentation : jetons
// (POST /{version}/auth_tokens), puis la conversation par WebSocket, jouée au fil de l'enregistrement (POST /__step).
// Aucune intelligence artificielle, aucun appel à l'extérieur : l'écran montré dans les vidéos est un ÉCRAN DE
// DÉMONSTRATION, jamais une vraie conversation.

import { createServer } from 'node:http';
import { WebSocketServer } from 'ws';

const RATE = 24000;
const CHUNK_MS = 100;

/** Son « parlé » de démonstration (bruit modulé en syllabes, faible) : la bouche de l'écran bouge, rien n'est dit. */
export function speechLike(seconds, seed = 1) {
    const samples = Math.round(seconds * RATE);
    const pcm = Buffer.alloc(samples * 2);
    let a = seed >>> 0;
    const random = () => {
        a = (a * 1664525 + 1013904223) >>> 0;

        return a / 4294967296 - 0.5;
    };
    let low = 0;

    for (let i = 0; i < samples; i++) {
        const t = i / RATE;
        const syllable = Math.max(0, Math.sin(2 * Math.PI * 4.2 * t)) ** 0.7 * (0.6 + 0.4 * Math.sin(2 * Math.PI * 0.5 * t));
        low = low * 0.85 + random() * 0.15;
        const voice = Math.sin(2 * Math.PI * 140 * t) * 0.5 + Math.sin(2 * Math.PI * 280 * t) * 0.25 + low * 2;
        pcm.writeInt16LE(Math.max(-32767, Math.min(32767, Math.round(voice * syllable * 6000))), i * 2);
    }

    return pcm;
}

const sleep = (ms) => new Promise((ok) => setTimeout(ok, ms));

/**
 * Démarre le faux fournisseur. Ce que dit le faux professeur est poussé pendant l'enregistrement, au bon moment, par
 * POST /__step : { teacher: "texte", seconds }, { learner: "texte" } ou { call: { name, args } }, sur la séance ouverte.
 */
export function startFakeGemini(port) {
    let current = null;
    let calls = 0;

    const server = createServer((request, response) => {
        let body = '';
        request.on('data', (chunk) => { body += chunk; });
        request.on('end', () => {
            if (request.url.startsWith('/__step')) {
                const step = JSON.parse(body || '{}');
                const ok = current !== null;

                if (ok) {
                    play(current, step, ++calls);
                }

                response.writeHead(ok ? 200 : 409, { 'Content-Type': 'application/json' }).end(JSON.stringify({ ok }));

                return;
            }

            if (request.url.includes('/auth_tokens')) {
                response.writeHead(200, { 'Content-Type': 'application/json' }).end(JSON.stringify({ name: 'auth_tokens/demonstration' }));

                return;
            }

            // Modèle texte, synthèse vocale : indisponibles (le site prend ses textes de secours, sans attendre).
            response.writeHead(503, { 'Content-Type': 'application/json' }).end('{"error":{"code":503,"message":"demonstration"}}');
        });
    });

    const wss = new WebSocketServer({ server });

    wss.on('connection', (socket) => {
        const send = (message) => socket.readyState === socket.OPEN && socket.send(JSON.stringify(message));

        socket.on('message', (raw) => {
            const message = JSON.parse(String(raw));

            if (message.setup) {
                current = send;
                send({ setupComplete: {} });
            }
        });

        socket.on('close', () => {
            if (current === send) {
                current = null;
            }
        });
    });

    return new Promise((ok) => server.listen(port, '127.0.0.1', () => ok({
        close: () => new Promise((done) => { wss.clients.forEach((client) => client.terminate()); wss.close(); server.close(() => done()); }),
    })));
}

function play(send, step, seed) {
    if (step.learner) {
        send({ serverContent: { inputTranscription: { text: step.learner } } });
    } else if (step.call) {
        send({ toolCall: { functionCalls: [{ id: `appel-${seed}`, name: step.call.name, args: step.call.args }] } });
    } else if (step.teacher) {
        speak(send, step.teacher, step.seconds ?? Math.max(1.5, step.teacher.split(/\s+/).length / 2.6), seed);
    }
}

/** Une réplique : morceaux de son et de texte au fil du temps, puis la fin du tour. */
async function speak(send, text, seconds, seed) {
    const words = text.split(/\s+/);
    const chunks = Math.max(1, Math.round((seconds * 1000) / CHUNK_MS));
    const pcm = speechLike(seconds, seed);
    const bytesPerChunk = Math.floor(pcm.length / chunks / 2) * 2;
    let sentWords = 0;

    for (let i = 0; i < chunks; i++) {
        const parts = [{ inlineData: { mimeType: `audio/pcm;rate=${RATE}`, data: pcm.subarray(i * bytesPerChunk, (i + 1) * bytesPerChunk).toString('base64') } }];
        send({ serverContent: { modelTurn: { parts } } });
        const upTo = Math.round(((i + 1) / chunks) * words.length);

        if (upTo > sentWords) {
            send({ serverContent: { outputTranscription: { text: (sentWords > 0 ? ' ' : '') + words.slice(sentWords, upTo).join(' ') } } });
            sentWords = upTo;
        }

        // Le son part un peu plus vite que la lecture, comme chez le vrai fournisseur.
        await sleep(CHUNK_MS * 0.8);
    }

    send({ serverContent: { turnComplete: true } });
    send({ usageMetadata: { promptTokenCount: 10, responseTokenCount: 10 } });
}
