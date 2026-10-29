/*
 * « Essai des voix » : bouton « Écouter en séance ». Vraie connexion de séance avec cette voix (Gemini Live en
 * WebSocket, ou OpenAI Realtime en WebRTC), SANS micro : la phrase d'essai est dite, jouée, puis envoyée au serveur
 * qui la garde (les écoutes suivantes ne se connectent plus). Le serveur raccroche de lui-même après 30 secondes.
 */

const error = globalThis.document?.getElementById('voice-error');
const csrf = globalThis.document?.querySelector('meta[name="csrf-token"]')?.content ?? '';
let current = null;

/** WAV mono 16 bits à partir de morceaux PCM (Gemini envoie du PCM 16 bits, 24 kHz). */
export function wavFromPcm(chunks, rate = 24000) {
    const length = chunks.reduce((sum, chunk) => sum + chunk.byteLength, 0);
    const buffer = new ArrayBuffer(44 + length);
    const view = new DataView(buffer);
    const text = (offset, value) => [...value].forEach((char, i) => view.setUint8(offset + i, char.charCodeAt(0)));

    text(0, 'RIFF');
    view.setUint32(4, 36 + length, true);
    text(8, 'WAVE');
    text(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, rate, true);
    view.setUint32(28, rate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    text(36, 'data');
    view.setUint32(40, length, true);

    let offset = 44;

    for (const chunk of chunks) {
        new Uint8Array(buffer, offset, chunk.byteLength).set(new Uint8Array(chunk));
        offset += chunk.byteLength;
    }

    return new Blob([buffer], { type: 'audio/wav' });
}

/** Fréquence lue dans le type annoncé (« audio/pcm;rate=24000 »). */
export function rateFrom(mimeType) {
    const match = /rate=(\d+)/.exec(mimeType ?? '');

    return match ? Number(match[1]) : 24000;
}

function bytesFromBase64(data) {
    const binary = atob(data);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes.buffer;
}

/** Gemini Live : la phrase dite par cette voix, en WAV. */
export function speakWithGemini(config, { WebSocketImpl = globalThis.WebSocket, timeoutMs = 30000 } = {}) {
    return new Promise((resolve, reject) => {
        const ws = new WebSocketImpl(config.websocket_url);
        const chunks = [];
        let rate = 24000;
        const timer = setTimeout(() => finish(new Error('Pas de voix reçue en 30 secondes.')), timeoutMs);

        function finish(failure) {
            clearTimeout(timer);
            ws.onclose = null;

            try {
                ws.close();
            } catch {
                // déjà fermée
            }

            if (failure) {
                reject(failure);
            } else if (chunks.length === 0) {
                reject(new Error('La séance n\'a renvoyé aucune voix.'));
            } else {
                resolve(wavFromPcm(chunks, rate));
            }
        }

        ws.onopen = () => ws.send(JSON.stringify({ setup: config.setup }));
        ws.onerror = () => finish(new Error('Connexion à la séance impossible.'));
        ws.onclose = (event) => finish(chunks.length ? null : new Error(`Connexion fermée par le fournisseur${event?.reason ? ` : ${event.reason}` : ''}.`));
        ws.onmessage = async (event) => {
            const message = JSON.parse(typeof event.data === 'string' ? event.data : await event.data.text());

            if (message.setupComplete) {
                ws.send(JSON.stringify({ realtimeInput: { text: 'START' } }));
            }

            for (const part of message.serverContent?.modelTurn?.parts ?? []) {
                if (part.inlineData?.data) {
                    rate = rateFrom(part.inlineData.mimeType);
                    chunks.push(bytesFromBase64(part.inlineData.data));
                }
            }

            if (message.serverContent?.turnComplete) {
                finish(null);
            }
        };
    });
}

/** OpenAI Realtime : la phrase dite par cette voix, enregistrée telle qu'elle arrive (WebM). */
export async function speakWithOpenAi(config, { timeoutMs = 30000 } = {}) {
    const pc = new RTCPeerConnection();
    pc.addTransceiver('audio', { direction: 'recvonly' });
    const dc = pc.createDataChannel('oai-events');
    const chunks = [];
    let recorder = null;

    const track = new Promise((resolve) => {
        pc.ontrack = (event) => resolve(event.streams[0] ?? new MediaStream([event.track]));
    });

    const done = new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('Pas de voix reçue en 30 secondes.')), timeoutMs);

        dc.onopen = () => {
            dc.send(JSON.stringify({ type: 'conversation.item.create', item: { type: 'message', role: 'user', content: [{ type: 'input_text', text: 'START' }] } }));
            dc.send(JSON.stringify({ type: 'response.create' }));
        };

        dc.onmessage = async (event) => {
            const message = JSON.parse(event.data);

            if (message.type === 'output_audio_buffer.started' && !recorder) {
                recorder = new MediaRecorder(await track, { mimeType: 'audio/webm;codecs=opus' });
                recorder.ondataavailable = (chunk) => chunk.data.size && chunks.push(chunk.data);
                recorder.start();
            }

            if (message.type === 'output_audio_buffer.stopped' && recorder) {
                // Fin de l'envoi : la fin de la voix arrive encore par le réseau.
                setTimeout(() => {
                    recorder.onstop = () => {
                        clearTimeout(timer);
                        resolve(new Blob(chunks, { type: 'audio/webm' }));
                    };
                    recorder.stop();
                }, 600);
            }

            if (message.type === 'error') {
                clearTimeout(timer);
                reject(new Error(message.error?.message || 'Erreur du fournisseur.'));
            }
        };
    });

    try {
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        const response = await post(config.call_url, JSON.stringify({ sdp: offer.sdp }), { 'Content-Type': 'application/json' });
        await pc.setRemoteDescription({ type: 'answer', sdp: response.sdp });

        return await done;
    } finally {
        pc.close();
    }
}

async function post(url, body, headers = {}) {
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...headers } });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || 'La séance d\'essai n\'a pas pu être ouverte.');
    }

    return data;
}

function play(source) {
    current?.pause();
    current = new Audio(source);

    return current.play();
}

globalThis.document?.querySelectorAll('[data-voice-live]').forEach((button) => {
    const label = button.innerHTML;

    button.addEventListener('click', async () => {
        current?.pause();
        error.hidden = true;
        button.disabled = true;
        button.textContent = 'Connexion à la séance…';

        try {
            const config = await post(button.dataset.voiceLive, null);

            if (config.stored) {
                await play(config.stored);

                return;
            }

            button.textContent = 'Écoute en cours…';
            const audio = config.provider === 'openai' ? await speakWithOpenAi(config) : await speakWithGemini(config);
            await play(URL.createObjectURL(audio));

            const form = new FormData();
            form.append('audio', audio, config.provider === 'openai' ? 'extrait.webm' : 'extrait.wav');
            await post(config.store_url, form);
        } catch (failure) {
            error.textContent = failure.message;
            error.hidden = false;
            post(button.dataset.voiceLiveFinish, null).catch(() => {});
        } finally {
            button.disabled = false;
            button.innerHTML = label;
        }
    });
});
