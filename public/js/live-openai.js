/*
 * Adaptateur OpenAI Realtime (WebRTC) pour l'écran de conversation (live-session.js), secours de Gemini.
 * Testé par tests/js/live-openai.test.mjs avec un faux serveur.
 *
 * Pourquoi WebRTC (et non WebSocket) : c'est la connexion recommandée par OpenAI dans le navigateur ; le son
 * voyage en Opus (environ 4 à 8 Ko par seconde au lieu de 64 Ko de PCM en base64 par WebSocket : décisif sur
 * les réseaux mobiles) avec correction des pertes et tampon de gigue ; et l'appel est ouvert PAR LE SERVEUR
 * (interface « unifiée ») : ni clé, ni jeton, ni consignes dans le navigateur, et le serveur raccroche lui-même
 * à la limite de temps.
 *
 * Déroulé : le navigateur prépare la connexion (micro, canal « oai-events »), envoie sa description (SDP) à
 * l'adresse à usage unique donnée par le serveur (call_url), applique la réponse, puis échange des événements
 * JSON sur le canal. La voix arrive sur une piste audio (onRemoteStream), branchée par l'écran sur la même
 * chaîne que Gemini (fondu, volume, limiteur, lecture labiale du professeur 3D).
 *
 * Demi-duplex : le micro passe par une porte (gain 0 ou 1) avant la piste envoyée ; l'écran l'ouvre et la ferme
 * avec la même porte du micro que pour Gemini (mic-gate.js). La voix « parle » de output_audio_buffer.started à
 * output_audio_buffer.stopped (fin de l'envoi par OpenAI) ; l'écran y ajoute la fin RÉELLE de la lecture.
 */

export const PROVIDER = 'openai';

// Délai maximal d'ouverture (description envoyée, canal ouvert), en millisecondes.
const OPEN_TIMEOUT_MS = 20000;
// Ouverture et fermeture de la porte du micro, en secondes : pas de clic.
const GATE_RAMP = 0.01;

export class OpenAiTransport {
    /**
     * @param {object} config    réponse du serveur à /connexion (call_url)
     * @param {object} handlers  mêmes événements que GeminiTransport, plus onRemoteStream(stream)
     * @param {object} options   post (échange avec Laravel), context et micBus (audio de l'écran), et pour les
     *                           tests : RTCPeerConnectionImpl, createAudioElement, setTimer
     */
    constructor(config, handlers, { post, context, micBus, RTCPeerConnectionImpl = globalThis.RTCPeerConnection, createAudioElement = () => new Audio(), setTimer = (fn, ms) => setTimeout(fn, ms) } = {}) {
        this.config = config;
        this.handlers = handlers;
        this.post = post;
        this.context = context;
        this.micBus = micBus;
        this.RTCPeerConnectionImpl = RTCPeerConnectionImpl;
        this.createAudioElement = createAudioElement;
        this.setTimer = setTimer;
        this.pc = null;
        this.dc = null;
        this.gate = null;
        this.player = null;
        this.ready = false;
        this.closed = false;
        // Voix envoyée par OpenAI en ce moment (output_audio_buffer.started → stopped).
        this.speaking = false;
        this.speakingSince = 0;
        this.stoppedAt = 0;
        this.responding = false;
        // Le son arrive sur une piste : l'écran ne programme aucun tampon.
        this.streamsAudio = true;
        this.learnerDeltas = new Set();
    }

    get provider() {
        return PROVIDER;
    }

    get isOpen() {
        return !this.closed && this.dc?.readyState === 'open';
    }

    /**
     * Ouvre l'appel. Résout quand le canal est ouvert (onReady) ; rejette (avec status et message du serveur)
     * si l'appel n'a pas pu s'ouvrir : rien n'a été échangé, l'écran peut demander un autre fournisseur.
     */
    async connect() {
        const pc = new this.RTCPeerConnectionImpl();
        this.pc = pc;

        // Voix du professeur : la piste est rattachée à un élément audio muet (sans quoi Chrome ne la fait
        // pas circuler vers l'audio de la page), puis branchée par l'écran sur sa chaîne de lecture.
        pc.ontrack = (event) => {
            const stream = event.streams?.[0] ?? null;

            if (!stream) {
                return;
            }

            this.player = this.createAudioElement();
            this.player.muted = true;
            this.player.srcObject = stream;
            this.player.play?.()?.catch?.(() => {});
            this.handlers.onRemoteStream?.(stream);
        };

        // Micro → porte (demi-duplex) → piste envoyée à OpenAI.
        if (this.context && this.micBus) {
            this.gate = this.context.createGain();
            this.gate.gain.value = 0;
            const destination = this.context.createMediaStreamDestination();
            this.micBus.connect(this.gate).connect(destination);
            const track = destination.stream.getAudioTracks()[0];
            pc.addTrack(track, destination.stream);
        }

        const dc = pc.createDataChannel('oai-events');
        this.dc = dc;
        dc.onmessage = (event) => {
            try {
                this.handle(JSON.parse(event.data));
            } catch (error) {
                console.warn('OpenAI : événement illisible', error);
            }
        };

        const opened = new Promise((resolve, reject) => {
            const timer = this.setTimer(() => reject(Object.assign(new Error('timeout'), { status: 0 })), OPEN_TIMEOUT_MS);

            dc.onopen = () => {
                clearTimeout(timer);
                resolve();
            };
            dc.onclose = () => {
                clearTimeout(timer);

                if (!this.ready) {
                    reject(Object.assign(new Error('closed'), { status: 0 }));
                } else {
                    this.lost('closed');
                }
            };
        });
        opened.catch(() => {});

        pc.onconnectionstatechange = () => {
            if (this.ready && ['failed', 'closed'].includes(pc.connectionState)) {
                this.lost(pc.connectionState);
            }
        };

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);

        const result = await this.post(this.config.call_url, { sdp: offer.sdp });

        if (!result.ok || typeof result.data?.sdp !== 'string') {
            this.shutdown();

            throw Object.assign(new Error(result.data?.message || 'call'), {
                status: result.status,
                quota: Boolean(result.data?.quota),
                serverMessage: result.data?.message,
            });
        }

        await pc.setRemoteDescription({ type: 'answer', sdp: result.data.sdp });

        try {
            await opened;
        } catch (error) {
            this.shutdown();

            throw error;
        }

        this.ready = true;
        this.handlers.onReady?.();
    }

    /**
     * Sécurité (live-session.js, mic-gate.js) : fin de la voix tenue pour acquise quand output_audio_buffer.stopped
     * ne vient pas alors que plus rien n'est audible.
     */
    voiceEnded(at) {
        this.speaking = false;
        this.stoppedAt = at;
    }

    /** Connexion perdue en cours de séance (réseau, appel raccroché par le serveur à la limite). */
    lost(reason) {
        if (this.closed) {
            return;
        }

        this.shutdown();
        this.handlers.onClose?.({ reason, code: 0, ready: this.ready });
    }

    /**
     * Événements du serveur, traduits dans le langage commun de l'écran.
     */
    handle(event) {
        const h = this.handlers;

        switch (event.type) {
            case 'response.created':
                this.responding = true;
                h.onGenerating?.();
                break;

            case 'response.output_audio_transcript.delta':
                h.onTeacherText?.(event.delta ?? '');
                break;

            case 'output_audio_buffer.started':
                this.speaking = true;
                this.speakingSince = this.context?.currentTime ?? 0;
                h.onSpeechStarted?.();
                break;

            case 'output_audio_buffer.stopped':
            case 'output_audio_buffer.cleared':
                this.speaking = false;
                this.stoppedAt = this.context?.currentTime ?? 0;
                break;

            case 'input_audio_buffer.speech_started':
                // L'apprenant parle pendant une réponse (rare : micro fermé pendant la voix) : OpenAI l'interrompt.
                if (this.speaking || this.responding) {
                    h.onInterrupted?.();
                }

                h.onLearnerStarted?.();
                break;

            case 'input_audio_buffer.speech_stopped':
                h.onLearnerStopped?.(event.item_id ?? null);
                break;

            case 'conversation.item.input_audio_transcription.delta':
                if (event.delta) {
                    this.learnerDeltas.add(event.item_id);
                    h.onLearnerText?.(event.delta, event.item_id ?? null);
                }
                break;

            case 'conversation.item.input_audio_transcription.completed':
                // Modèles de transcription sans morceaux : le texte complet arrive d'un coup.
                if (!this.learnerDeltas.has(event.item_id) && event.transcript) {
                    h.onLearnerText?.(event.transcript, event.item_id ?? null);
                }

                this.learnerDeltas.delete(event.item_id);
                break;

            case 'response.function_call_arguments.done': {
                let args = {};

                try {
                    args = JSON.parse(event.arguments || '{}');
                } catch {
                    args = {};
                }

                h.onFunctionCall?.({ id: event.call_id, name: event.name, args });
                break;
            }

            case 'response.done': {
                const response = event.response ?? {};
                this.responding = false;

                if (response.usage) {
                    h.onUsage?.(response.usage.input_tokens ?? 0, response.usage.output_tokens ?? 0);
                }

                // Réponse qui appelle une fonction : le professeur reprend la parole après le résultat ;
                // ce n'est pas la fin de son tour (la fin automatique après le verdict attend la suivante).
                const calls = (response.output ?? []).some((item) => item.type === 'function_call');

                if (!calls) {
                    h.onTurnComplete?.();
                }
                break;
            }

            case 'error':
                console.warn('OpenAI :', event.error?.type, event.error?.code, event.error?.message);
                h.onProviderError?.(event.error?.code || event.error?.type || 'error');
                break;

            default:
                break;
        }
    }

    send(payload) {
        if (this.isOpen) {
            this.dc.send(JSON.stringify(payload));
        }
    }

    /**
     * Porte du micro (demi-duplex) : ouverte, la voix de l'apprenant part vers OpenAI ; fermée, du silence
     * (la détection de parole d'OpenAI voit alors la fin de sa phrase).
     */
    setMicOpen(open) {
        if (!this.gate || !this.context) {
            return;
        }

        const target = open ? 1 : 0;
        const param = this.gate.gain;

        if (this.gateTarget === target) {
            return;
        }

        this.gateTarget = target;
        const now = this.context.currentTime;
        param.cancelScheduledValues?.(now);
        param.setValueAtTime?.(param.value, now);
        param.linearRampToValueAtTime?.(target, now + GATE_RAMP);

        if (!param.linearRampToValueAtTime) {
            param.value = target;
        }
    }

    /** Indication au professeur (début, reprise, temps restant…), qui répond aussitôt. */
    sendText(text) {
        if (!text) {
            return;
        }

        this.send({ type: 'conversation.item.create', item: { type: 'message', role: 'user', content: [{ type: 'input_text', text }] } });
        this.send({ type: 'response.create' });
    }

    /**
     * Reprise sur un nouvel appel (réseau coupé) : OpenAI ne garde pas la conversation précédente, on lui
     * redonne ce qui a été dit, comme contexte seulement (aucune réponse demandée).
     */
    sendContext(text) {
        if (text) {
            this.send({ type: 'conversation.item.create', item: { type: 'message', role: 'user', content: [{ type: 'input_text', text }] } });
        }
    }

    sendFunctionResult(call, response) {
        this.send({ type: 'conversation.item.create', item: { type: 'function_call_output', call_id: call.id, output: JSON.stringify(response) } });
        this.send({ type: 'response.create' });
    }

    /**
     * « Interrompre le professeur », pause : la réponse en cours est annulée et la voix pas encore jouée est
     * effacée (OpenAI raccourcit alors la conversation à ce qui a été entendu).
     */
    cancel() {
        if (this.responding) {
            this.send({ type: 'response.cancel' });
        }

        if (this.speaking) {
            this.send({ type: 'output_audio_buffer.clear' });
        }
    }

    shutdown() {
        this.closed = true;

        try { this.dc?.close(); } catch { /* déjà fermé */ }

        try { this.pc?.close(); } catch { /* déjà fermé */ }

        try { this.gate?.disconnect(); } catch { /* déjà débranché */ }

        if (this.player) {
            this.player.srcObject = null;
        }
    }

    /** Fermeture voulue : aucun onClose (le serveur raccroche l'appel à la fin de la séance). */
    close() {
        this.shutdown();
    }
}
