/*
 * Adaptateur Gemini Live (WebSocket, jeton éphémère délivré par le serveur) pour l'écran de conversation
 * (live-session.js). Tout ce qui est propre au protocole de Google est ici : l'écran reçoit les mêmes
 * événements quel que soit le fournisseur (voir live-openai.js).
 *
 * Audio : micro en PCM 16 bits 16 kHz vers Gemini, voix du professeur reçue en PCM 16 bits 24 kHz
 * (événement onAudio, joué par l'écran avec pcm-audio.js).
 */

export const PROVIDER = 'gemini';

function base64FromBuffer(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    for (let i = 0; i < bytes.length; i += 0x8000) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    }

    return btoa(binary);
}

export class GeminiTransport {
    /**
     * @param {object} config    réponse du serveur à /connexion (websocket_url, setup)
     * @param {object} handlers  onReady, onAudio, onGenerating, onTeacherText, onLearnerText, onInterrupted,
     *                           onTurnComplete, onFunctionCall, onUsage, onResumeHandle, onGoAway, onClose,
     *                           onVoiceActivity (début ou fin de la parole de l'apprenant, si Google l'envoie)
     */
    constructor(config, handlers, { WebSocketImpl = globalThis.WebSocket, inputRate = 16000 } = {}) {
        this.config = config;
        this.handlers = handlers;
        this.WebSocketImpl = WebSocketImpl;
        this.inputRate = inputRate;
        this.ws = null;
        this.ready = false;
        // Le son passe par nos tampons : l'écran sait quand la voix finit vraiment (pcm-audio.js).
        this.streamsAudio = false;
    }

    get provider() {
        return PROVIDER;
    }

    get isOpen() {
        return this.ws?.readyState === this.WebSocketImpl.OPEN;
    }

    /**
     * Ouvre la connexion ; onReady une fois la configuration acceptée (setupComplete), onClose ensuite
     * (avec ready = false si la connexion n'a jamais été prête : rien n'a été échangé).
     */
    connect() {
        const ws = new this.WebSocketImpl(this.config.websocket_url);
        this.ws = ws;

        ws.onopen = () => ws.send(JSON.stringify({ setup: this.config.setup }));

        ws.onmessage = async (event) => {
            const text = typeof event.data === 'string' ? event.data : await event.data.text();
            const message = JSON.parse(text);

            if (message.setupComplete && !this.ready) {
                this.ready = true;
                this.handlers.onReady?.();
            }

            this.handle(message);
        };

        ws.onclose = (event) => this.handlers.onClose?.({ reason: event.reason ?? '', code: event.code, ready: this.ready });
    }

    handle(message) {
        const h = this.handlers;
        const content = message.serverContent;

        if (content) {
            if (content.modelTurn) {
                h.onGenerating?.();
            }

            for (const part of content.modelTurn?.parts ?? []) {
                if (part.inlineData?.data) {
                    h.onAudio?.(part.inlineData.data);
                }
            }

            if (content.inputTranscription?.text) {
                h.onLearnerText?.(content.inputTranscription.text, null);
            }

            if (content.outputTranscription?.text) {
                h.onTeacherText?.(content.outputTranscription.text);
            }

            if (content.interrupted) {
                h.onInterrupted?.();
            }

            if (content.turnComplete) {
                h.onTurnComplete?.();
            }
        }

        for (const call of message.toolCall?.functionCalls ?? []) {
            h.onFunctionCall?.({ id: call.id, name: call.name, args: call.args ?? {} });
        }

        // Début et fin de la parole de l'apprenant vus par Google, quand il les envoie (voiceActivity).
        if (message.voiceActivity?.type === 'ACTIVITY_START' || message.voiceActivity?.type === 'ACTIVITY_END') {
            h.onVoiceActivity?.(message.voiceActivity.type === 'ACTIVITY_START');
        }

        if (message.usageMetadata) {
            // Le rapport complet suit : l'écran en tire le détail texte / audio (token-usage.js).
            h.onUsage?.(
                message.usageMetadata.promptTokenCount ?? 0,
                message.usageMetadata.responseTokenCount ?? message.usageMetadata.candidatesTokenCount ?? 0,
                message.usageMetadata,
            );
        }

        if (message.sessionResumptionUpdate?.resumable && message.sessionResumptionUpdate.newHandle) {
            h.onResumeHandle?.(message.sessionResumptionUpdate.newHandle);
        }

        if (message.goAway) {
            // Google va fermer cette connexion : l'écran en ouvre une autre sans attendre.
            h.onGoAway?.();
        }
    }

    send(payload) {
        if (this.isOpen) {
            this.ws.send(JSON.stringify(payload));
        }
    }

    /** Un paquet du micro (porte ouverte). */
    sendAudio(buffer) {
        this.send({ realtimeInput: { audio: { data: base64FromBuffer(buffer), mimeType: `audio/pcm;rate=${this.inputRate}` } } });
    }

    /** Micro fermé : « audioStreamEnd », comme le demande Google. */
    endAudio() {
        this.send({ realtimeInput: { audioStreamEnd: true } });
    }

    sendText(text) {
        if (text) {
            // Derniers échanges d'une séance reprise ici après un autre fournisseur : envoyés avec l'indication.
            const context = this.context;
            this.context = null;
            this.send({ realtimeInput: { text: context ? `${context}\n\n${text}` : text } });
        }
    }

    /**
     * Le contexte d'une conversation reprise chez Google est gardé (sessionResumption). Seule une séance commencée
     * chez un autre fournisseur en a besoin : il part avec la première indication (reprise).
     */
    sendContext(text) {
        this.context = text || null;
    }

    sendFunctionResult(call, response) {
        this.send({ toolResponse: { functionResponses: [{ id: call.id, name: call.name, response }] } });
    }

    /** Arrêt de la voix : la fin de la réplique est ignorée par l'écran (Gemini n'a pas d'annulation). */
    cancel() {}

    /** Fermeture voulue : aucun onClose. */
    close() {
        if (this.ws) {
            this.ws.onclose = null;
            this.ws.close();
        }
    }
}
