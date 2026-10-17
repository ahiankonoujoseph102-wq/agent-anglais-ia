/*
 * Fin automatique d'une conversation après le verdict (niveau de l'évaluation,
 * bilan de séance, résultat du bilan de niveau) : le professeur termine sa phrase,
 * puis la conversation se coupe, sans attendre la fin du minuteur.
 * Testé par tests/js/conversation-end.test.mjs. Heures en secondes.
 */
export class ConversationEnd {
    /**
     * @param {number} settle    silence après la dernière phrase avant de couper
     * @param {number} maxWait   attente maximale d'une réplique après le verdict
     * @param {number} hardStop  au-delà, on coupe même s'il parle encore
     */
    constructor({ settle = 1, maxWait = 25, hardStop = 45 } = {}) {
        this.settle = settle;
        this.maxWait = maxWait;
        this.hardStop = hardStop;
        this.verdictAt = null;
        this.turnDoneAt = null;
    }

    get hasVerdict() {
        return this.verdictAt !== null;
    }

    /**
     * Après le verdict, plus aucune indication au professeur (« il reste N minutes »,
     * reprise…) : elle relançait une nouvelle annonce du niveau et une nouvelle question.
     */
    get acceptsCues() {
        return !this.hasVerdict;
    }

    /** Après le verdict, le micro reste fermé : plus de question ni de réponse. */
    get micOpen() {
        return !this.hasVerdict;
    }

    /** Le résultat est enregistré et la réponse envoyée à Gemini (une seule fois). */
    recordVerdict(now) {
        this.verdictAt ??= now;
    }

    /** Fin de tour du modèle : après le verdict, c'est la fin de son annonce. */
    turnComplete(now) {
        if (this.hasVerdict && this.turnDoneAt === null) {
            this.turnDoneAt = now;
        }
    }

    /**
     * @param {boolean} speaking  la voix du professeur sort encore du haut-parleur
     */
    shouldFinish(now, speaking) {
        if (!this.hasVerdict) {
            return false;
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
}
