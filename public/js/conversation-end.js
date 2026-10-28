/*
 * Fin automatique d'une conversation après le verdict (niveau de l'évaluation,
 * bilan de séance, résultat du bilan de niveau), sans attendre la fin du minuteur.
 * Testé par tests/js/conversation-end.test.mjs. Heures en secondes.
 *
 * Séance et bilan de niveau : le professeur termine sa phrase, puis on coupe.
 *
 * Évaluation (option closingPhrase) : après l'annonce du niveau, moment des
 * questions libres (micro ouvert, demi-duplex), puis UNE conclusion qui finit par
 * une phrase fixe (« On se retrouve de l'autre côté ! »). La fin est détectée en
 * repérant cette phrase dans la transcription du professeur, puis on coupe une fois
 * sa voix réellement terminée. Aucun appel de fonction : ils faisaient répéter le modèle.
 * UNE seule indication de temps après le verdict : « passez à la conclusion ».
 */

/**
 * Texte comparable : minuscules, sans accents ni ponctuation (apostrophes droites ou courbes).
 */
export function normalizeSpeech(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/**
 * Le professeur dit-il le niveau dans ce texte (sa transcription) ? « A2 », « A 2 », « A-2 », « A two ».
 */
export function mentionsLevel(text, level) {
    const match = /^([ABC])([12])$/.exec(String(level ?? '').toUpperCase());

    if (!match) {
        return false;
    }

    const word = match[2] === '1' ? 'one' : 'two';

    return new RegExp(`\\b${match[1]}\\s*-?\\s*(${match[2]}|${word})\\b`, 'i').test(String(text ?? ''));
}

/**
 * Évaluation : le niveau, reçu juste AVANT que le professeur l'annonce, n'est montré (écran et vidéo) que quand
 * il le dit, jamais avant. Heures en millisecondes.
 * - heardAt : moment où sa voix dit le niveau (repéré dans sa transcription, quand le son reçu jusque-là aura été
 *   joué) : on montre à ce moment.
 * - Niveau jamais repéré : après delayMs, seulement s'il a parlé depuis le résultat et s'est tu.
 * - Dans tous les cas, au plus tard maxMs après le résultat.
 */
export function levelRevealDue({ now, receivedAt, delayMs, maxMs, heardAt = null, spokeAfter = false, speaking = false }) {
    if (now - receivedAt >= maxMs) {
        return true;
    }

    if (heardAt !== null) {
        return now >= heardAt;
    }

    return now - receivedAt >= delayMs && spokeAfter && !speaking;
}

export class ConversationEnd {
    /**
     * @param {number} settle         silence après la dernière phrase avant de couper
     * @param {number} maxWait        attente maximale d'une réplique après le verdict (sans questions)
     * @param {number} hardStop       au-delà, on coupe même s'il parle encore (sans questions)
     * @param {?string} closingPhrase phrase fixe de la conclusion (évaluation) ; null : pas de questions
     * @param {number} closingWait    après la phrase fixe, coupe au plus tard (même s'il parle encore)
     * @param {number} cueSeconds     indication de conclusion ce nombre de secondes avant la fin
     * @param {number} grace          à la limite de temps, la phrase en cours peut finir ce temps au plus
     * @param {number} silentEnd      après l'indication de conclusion, fin si le professeur ne dit plus rien ce temps
     *                                (0 : jamais) ; jamais de séance en direct et muette jusqu'au bout du minuteur
     */
    constructor({ settle = 1, maxWait = 25, hardStop = 45, closingPhrase = null, closingWait = 20, cueSeconds = 30, grace = 6, silentEnd = 0 } = {}) {
        this.settle = settle;
        this.maxWait = maxWait;
        this.hardStop = hardStop;
        this.closingKey = closingPhrase ? normalizeSpeech(closingPhrase) : null;
        // Fin de la phrase fixe (quatre derniers mots) : reconnue même si le début est dit autrement.
        const words = this.closingKey ? this.closingKey.split(' ') : [];
        this.closingTail = words.length > 4 ? words.slice(-4).join(' ') : this.closingKey;
        this.silentEnd = silentEnd;
        this.conclusionCueAt = null;
        this.teacherAt = null;
        this.closingWait = closingWait;
        this.cueSeconds = cueSeconds;
        this.grace = grace;
        this.verdictAt = null;
        this.turnDoneAt = null;
        this.questions = false;
        this.heard = '';
        this.closingHeardAt = null;
        this.conclusionCueSent = false;
        this.limitAt = null;
    }

    get hasVerdict() {
        return this.verdictAt !== null;
    }

    /**
     * Après le verdict, plus aucune indication ordinaire (« il reste N minutes », reprise…) :
     * elle relançait une nouvelle annonce du niveau et une nouvelle question.
     * Seule exception : l'indication unique de conclusion (shouldConclude).
     */
    get acceptsCues() {
        return !this.hasVerdict;
    }

    /** La conclusion a commencé (indication envoyée) ou s'est terminée (phrase fixe entendue). */
    get concluding() {
        return this.conclusionCueSent || this.closingHeardAt !== null;
    }

    /** Moment des questions libres en cours (minuteur « Vos questions »). */
    get inQuestions() {
        return this.questions && this.closingHeardAt === null;
    }

    /**
     * Micro ouvert avant le verdict, pendant les questions et la conclusion (l'apprenant peut encore parler et être
     * entendu) ; fermé définitivement dès la phrase fixe (et, sans questions, dès le verdict).
     */
    get micOpen() {
        if (!this.hasVerdict) {
            return true;
        }

        return this.questions && this.closingHeardAt === null;
    }

    /**
     * Le résultat est enregistré et la réponse envoyée à Gemini (une seule fois).
     *
     * @param {boolean} questions  le serveur a ouvert le moment des questions (évaluation encore en cours)
     */
    recordVerdict(now, { questions = false } = {}) {
        if (this.verdictAt !== null) {
            return;
        }

        this.verdictAt = now;
        this.questions = questions && this.closingKey !== null;
    }

    /**
     * Sous-titre du professeur. Renvoie true quand la phrase fixe de la conclusion vient d'être dite.
     * Les morceaux de transcription sont recollés tels quels (un mot peut être coupé en deux).
     */
    teacherSaid(text, now) {
        this.teacherAt = now;

        if (!this.questions || this.closingHeardAt !== null) {
            return false;
        }

        this.heard = (this.heard + text).slice(-400);
        const heard = normalizeSpeech(this.heard);

        if (heard.includes(this.closingKey) || heard.includes(this.closingTail)) {
            this.closingHeardAt = now;

            return true;
        }

        return false;
    }

    /** Fin de tour du modèle. */
    turnComplete(now) {
        if (!this.hasVerdict || this.turnDoneAt !== null) {
            return;
        }

        // Avec questions, seule compte la fin du tour qui contient la phrase fixe.
        if (!this.questions || this.closingHeardAt !== null) {
            this.turnDoneAt = now;
        }
    }

    /**
     * Indication unique « passez à la conclusion », environ cueSeconds avant la fin du moment
     * des questions, quand le professeur ne parle pas (sinon on attend, jusqu'à la moitié du délai).
     * Renvoie true une seule fois : c'est alors qu'il faut l'envoyer.
     *
     * @param {number} remaining  secondes restantes (minuteur du serveur)
     * @param {boolean} busy      le professeur parle ou prépare sa réponse
     * @param {?number} now       heure de l'envoi (pour la fin sur silence)
     */
    shouldConclude(remaining, busy, now = null) {
        if (!this.questions || this.concluding || remaining > this.cueSeconds) {
            return false;
        }

        if (busy && remaining > this.cueSeconds / 2) {
            return false;
        }

        this.conclusionCueSent = true;
        this.conclusionCueAt = now;

        return true;
    }

    /**
     * @param {boolean} speaking  la voix du professeur sort encore du haut-parleur
     */
    shouldFinish(now, speaking) {
        if (!this.hasVerdict) {
            return false;
        }

        if (this.questions) {
            return this.finishAfterClosing(now, speaking);
        }

        if (now - this.verdictAt >= this.hardStop) {
            return true;
        }

        if (speaking) {
            return false;
        }

        if (this.turnDoneAt !== null) {
            return now - this.turnDoneAt >= this.settle;
        }

        return now - this.verdictAt >= this.maxWait;
    }

    /** Phrase fixe entendue : on coupe une fois la voix réellement terminée. */
    finishAfterClosing(now, speaking) {
        if (this.closingHeardAt === null) {
            // Indication de conclusion envoyée, puis plus un mot : on termine au lieu de rester muet jusqu'à la limite.
            if (this.silentEnd > 0 && this.conclusionCueAt !== null && !speaking) {
                return now - Math.max(this.conclusionCueAt, this.teacherAt ?? 0) >= this.silentEnd;
            }

            return false; // La limite de temps reste le filet de sécurité (timeUp).
        }

        if (now - this.closingHeardAt >= this.closingWait) {
            return true;
        }

        if (speaking) {
            return false;
        }

        if (this.turnDoneAt !== null) {
            return now - this.turnDoneAt >= this.settle;
        }

        // Voix finie sans fin de tour reçue : courte attente, puis on coupe.
        return now - this.closingHeardAt >= this.settle * 4;
    }

    /**
     * Minuteur à zéro. Pendant les questions ou la conclusion, la phrase en cours peut
     * finir (grace secondes au plus) ; ailleurs, on coupe tout de suite.
     */
    timeUp(now, speaking) {
        if (!this.questions) {
            return true;
        }

        this.limitAt ??= now;

        return !speaking || now - this.limitAt >= this.grace;
    }
}
