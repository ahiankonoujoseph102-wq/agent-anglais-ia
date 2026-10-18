/*
 * Texte de la conversation (jamais l'audio) : chaque réplique de l'apprenant ou du professeur reçoit un numéro,
 * grandit au fil des sous-titres, et part au serveur par petits lots (live-session.js : à chaque signal, puis à la
 * fin). Une réplique déjà envoyée qui a grandi (ou dont l'heure réelle et les mesures sont connues) repart avec le
 * même numéro : le serveur garde le texte le plus long et met à jour l'heure et les mesures.
 * Les événements de la séance (reconnexion, erreur, micro fermé, pause…) partent avec les répliques.
 * Un envoi raté est simplement repris au lot suivant ; la conversation n'attend jamais.
 * Testé par tests/js/transcript.test.mjs.
 */

export class TranscriptRecorder {
    /**
     * @param {object} options  maxBatch (répliques par envoi), maxEvents (événements par envoi et en attente),
     *                          maxChars (caractères par réplique), now (horloge)
     */
    constructor({ maxBatch = 50, maxEvents = 50, maxChars = 4000, now = () => Date.now() } = {}) {
        this.maxBatch = maxBatch;
        this.maxEvents = maxEvents;
        this.maxChars = maxChars;
        this.now = now;
        this.seq = 0;
        // Répliques encore à envoyer (nouvelles, qui ont grandi ou mises à jour), dans l'ordre.
        this.pending = new Set();
        // Événements pas encore envoyés (les plus anciens sont abandonnés au-delà de maxEvents × 6).
        this.events = [];
    }

    /** Nouvelle réplique de « learner » ou « teacher », à l'heure donnée (par défaut : maintenant). */
    start(speaker, at = this.now()) {
        return { seq: ++this.seq, speaker, text: '', at, end: null, metrics: null };
    }

    /** Un morceau de sous-titre ajouté à la réplique. */
    append(line, text) {
        if (!line || !text || line.text.length >= this.maxChars) {
            return;
        }

        line.text = (line.text + text).slice(0, this.maxChars);
        this.pending.add(line);
    }

    /**
     * Heure réelle du début de la parole, fin de la parole, mesures du tour : connues parfois après le texte.
     */
    update(line, { at = null, end = null, metrics = null } = {}) {
        if (!line) {
            return;
        }

        if (at !== null && at !== undefined) {
            line.at = at;
        }

        if (end !== null && end !== undefined) {
            line.end = end;
        }

        if (metrics) {
            line.metrics = { ...(line.metrics ?? {}), ...metrics };
        }

        this.pending.add(line);
    }

    /** Un événement de la séance (reconnexion, erreur, micro fermé…), avec un court détail. */
    event(kind, detail = '', at = this.now()) {
        this.events.push({ kind, detail: String(detail ?? '').slice(0, 200), at });

        if (this.events.length > this.maxEvents * 6) {
            this.events.shift();
        }
    }

    get hasPending() {
        return this.pending.size > 0 || this.events.length > 0;
    }

    /**
     * Le prochain lot à envoyer (au plus maxBatch répliques, sans les répliques vides).
     */
    takeBatch() {
        const batch = [];

        for (const line of this.pending) {
            this.pending.delete(line);

            if (line.text.trim() !== '') {
                batch.push(line);
            }

            if (batch.length >= this.maxBatch) {
                break;
            }
        }

        return batch;
    }

    /** Les prochains événements à envoyer. */
    takeEvents() {
        return this.events.splice(0, this.maxEvents);
    }

    /** Lot refusé ou réseau coupé : ses répliques et ses événements seront renvoyés au prochain envoi. */
    restore(batch, events = []) {
        for (const line of batch) {
            this.pending.add(line);
        }

        this.events.unshift(...events);
    }

    /** Ce qui part au serveur. */
    static payload(batch) {
        return batch.map(({ seq, speaker, text, at, end, metrics }) => {
            const line = { seq, speaker, text: text.trim(), at: Math.round(at) };

            if (end) {
                line.end = Math.round(end);
            }

            if (metrics && Object.keys(metrics).length > 0) {
                line.metrics = metrics;
            }

            return line;
        });
    }

    static eventsPayload(events) {
        return events.map(({ kind, detail, at }) => ({ kind, detail, at: Math.round(at) }));
    }

    /**
     * Même contenu en champs de formulaire (dernier envoi de la page fermée, navigator.sendBeacon).
     */
    static formFields(lines, events) {
        const fields = [];

        lines.forEach((line, index) => {
            for (const [key, value] of Object.entries(line)) {
                if (key === 'metrics') {
                    for (const [name, number] of Object.entries(value)) {
                        fields.push([`lines[${index}][metrics][${name}]`, String(number)]);
                    }
                } else {
                    fields.push([`lines[${index}][${key}]`, String(value)]);
                }
            }
        });

        events.forEach((event, index) => {
            for (const [key, value] of Object.entries(event)) {
                fields.push([`events[${index}][${key}]`, String(value)]);
            }
        });

        return fields;
    }
}
