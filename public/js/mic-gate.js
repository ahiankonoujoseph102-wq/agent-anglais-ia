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

/**
 * Fin de la voix chez Gemini, où le son passe par nos tampons. Le son reçu peut s'épuiser au milieu d'une réponse
 * (Gemini produit la voix à peu près au rythme de la lecture : un trou du réseau d'une demi-seconde suffit) : la
 * porte du micro se rouvrait alors, l'écho de la suite de la réponse partait vers Gemini, qui coupait sa propre
 * phrase ou se répondait à lui-même. Tant que la réponse n'est pas finie (turnComplete), la voix est tenue pour
 * en cours, au plus « hold » secondes sans nouveau son (sécurité si la fin de réponse se perd). Juste après la
 * demande d'accueil, la porte reste aussi fermée jusqu'au premier son (au plus jusqu'à « expectingUntil ») : un
 * bruit pendant ce temps interrompait l'accueil.
 *
 * @param {number} scheduledEnd   fin programmée du dernier tampon (voiceEndsAt)
 * @param {boolean} pending       réponse commencée et pas encore finie
 * @param {number} lastChunkAt    heure du dernier morceau de voix reçu
 */
export function heldVoiceEnd({ scheduledEnd, now, pending = false, lastChunkAt = 0, hold = 3, expectingUntil = 0 }) {
    let end = scheduledEnd;

    if ((pending && now - lastChunkAt < hold) || now < expectingUntil) {
        end = Math.max(end, now + 0.05);
    }

    return end;
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

/**
 * Sécurité de la porte du micro quand la fin de la voix est ANNONCÉE par le fournisseur (OpenAI :
 * output_audio_buffer.stopped) : si cet événement se perd, la porte resterait fermée et l'apprenant parlerait
 * dans le vide. La voix est donc tenue pour finie quand plus rien n'est audible depuis « after » secondes
 * (heures de l'horloge audio), même sans l'événement.
 *
 * @param {number} now            heure actuelle
 * @param {number} speakingSince  début annoncé de la voix
 * @param {number} lastAudibleAt  dernier instant où la voix reçue était audible
 */
export function voiceLooksOver({ now, speakingSince, lastAudibleAt, after = 4 }) {
    return now - Math.max(speakingSince || 0, lastAudibleAt || 0) >= after;
}
