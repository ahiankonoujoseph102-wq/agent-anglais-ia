/*
 * Porte du micro (demi-duplex anti-écho), sans dépendance au navigateur : testée
 * par tests/js/mic-gate.test.mjs.
 *
 * Toutes les heures sont celles de l'horloge audio (AudioContext.currentTime, en
 * secondes). La fin de la voix du professeur est la fin PROGRAMMÉE du dernier
 * tampon joué, pas l'heure de réception : Gemini envoie l'audio plus vite qu'il
 * n'est joué.
 */

/**
 * Instant où le haut-parleur se tait vraiment : fin programmée du dernier tampon,
 * plus le retard ajouté (lecture labiale) et la latence de sortie de l'appareil.
 */
export function voiceEndsAt(playHead, outputDelay, outputLatency = 0) {
    return playHead + outputDelay + (outputLatency || 0);
}

export class MicGate {
    /**
     * @param {number} guard           silence gardé après la voix, en secondes (0,4)
     * @param {number} streamEndAfter  micro fermé depuis ce temps : audioStreamEnd (1 seconde, recommandation de Google)
     */
    constructor({ guard, streamEndAfter = 1 }) {
        this.guard = guard;
        this.streamEndAfter = streamEndAfter;
        this.reset();
    }

    /** Nouvelle connexion : rien n'a encore été envoyé. */
    reset() {
        this.streaming = false;
        this.closedSince = null;
    }

    isOpen(now, voiceEnd, blocked) {
        return !blocked && now >= voiceEnd + this.guard;
    }

    /**
     * À appeler pour chaque paquet du micro (toutes les 100 millisecondes environ).
     * « send » : envoyer le paquet ; « end » : envoyer audioStreamEnd ; « hold » : ne rien envoyer.
     *
     * @param {boolean} blocked  pause ou micro coupé (audioStreamEnd immédiat : la coupure est voulue)
     */
    decide(now, voiceEnd, blocked) {
        if (this.isOpen(now, voiceEnd, blocked)) {
            // Réouverture : Google reprend le flux dès le premier paquet audio.
            this.closedSince = null;
            this.streaming = true;

            return 'send';
        }

        if (this.closedSince === null) {
            this.closedSince = now;
        }

        if (this.streaming && (blocked || now - this.closedSince >= this.streamEndAfter)) {
            this.streaming = false;

            return 'end';
        }

        return 'hold';
    }
}
