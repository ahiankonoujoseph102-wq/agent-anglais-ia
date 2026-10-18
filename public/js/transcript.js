/*
 * Texte de la conversation (jamais l'audio) : chaque réplique de l'apprenant ou du professeur reçoit un numéro,
 * grandit au fil des sous-titres, et part au serveur par petits lots (live-session.js : à chaque signal, puis à la
 * fin). Une réplique déjà envoyée qui a grandi repart avec le même numéro : le serveur garde la plus longue.
 * Un envoi raté est simplement repris au lot suivant ; la conversation n'attend jamais.
 * Testé par tests/js/transcript.test.mjs.
 */

export class TranscriptRecorder {
    /**
     * @param {object} options  maxBatch (répliques par envoi), maxChars (caractères par réplique), now (horloge)
     */
    constructor({ maxBatch = 50, maxChars = 4000, now = () => Date.now() } = {}) {
        this.maxBatch = maxBatch;
        this.maxChars = maxChars;
        this.now = now;
        this.seq = 0;
        // Répliques encore à envoyer (nouvelles ou qui ont grandi), dans l'ordre.
        this.pending = new Set();
    }

    /** Nouvelle réplique de « learner » ou « teacher », à l'heure donnée (par défaut : maintenant). */
    start(speaker, at = this.now()) {
        return { seq: ++this.seq, speaker, text: '', at };
    }

    /** Un morceau de sous-titre ajouté à la réplique. */
    append(line, text) {
        if (!line || !text || line.text.length >= this.maxChars) {
            return;
        }

        line.text = (line.text + text).slice(0, this.maxChars);
        this.pending.add(line);
    }

    get hasPending() {
        return this.pending.size > 0;
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

    /** Lot refusé ou réseau coupé : ses répliques seront renvoyées au prochain envoi. */
    restore(batch) {
        for (const line of batch) {
            this.pending.add(line);
        }
    }

    /** Ce qui part au serveur. */
    static payload(batch) {
        return batch.map(({ seq, speaker, text, at }) => ({ seq, speaker, text: text.trim(), at: Math.round(at) }));
    }
}
