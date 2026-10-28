/*
 * Mesure des tours de parole et filet de sécurité de la fin de tour, sans dépendance au navigateur : testé par
 * tests/js/turn-watch.test.mjs. Aucune donnée audio : seulement le NIVEAU du micro, déjà calculé pour le témoin.
 *
 *   - VoiceActivity : détecte, dans le navigateur, le début et la fin de la voix de l'apprenant. Le seuil suit le
 *     bruit de fond (le plus petit niveau des dernières secondes, multiplié par « ratio ») : un ventilateur ou une
 *     rue bruyante ne passe pas pour de la voix, et les petites pauses entre les mots gardent le seuil bas.
 *   - TurnWatch : un tour = la parole de l'apprenant (selon le micro et selon le fournisseur), la fin du tour
 *     signalée par le fournisseur, puis le premier son de Teacher Joe ; il calcule le délai de réponse, dit quand
 *     signaler soi-même la fin du tour (le fournisseur ne l'a pas vue) et quand prévenir l'apprenant qu'aucune
 *     réponse n'arrive.
 *
 * Toutes les heures sont en millisecondes (Date.now()), comme celles du texte de la conversation.
 */

export class VoiceActivity {
    /**
     * @param {number} minLevel      niveau minimal d'une voix (valeur efficace entre 0 et 1)
     * @param {number} ratio         la voix doit dépasser le bruit de fond de ce facteur
     * @param {number} windowMs      durée sur laquelle on cherche le bruit de fond (plus petit niveau)
     * @param {number} bucketMs      durée d'une case de la moyenne du niveau
     * @param {number} startMs       voix au-dessus du seuil pendant ce temps : début de parole
     * @param {number} endMs         voix sous le seuil pendant ce temps : fin de parole (pause entre deux mots tolérée)
     */
    constructor({ minLevel = 0.015, ratio = 3, windowMs = 6000, bucketMs = 100, startMs = 120, endMs = 600 } = {}) {
        Object.assign(this, { minLevel, ratio, windowMs, bucketMs, startMs, endMs });
        this.buckets = [];
        this.bucket = null;
        this.speaking = false;
        this.aboveSince = null;
        this.lastAbove = null;
    }

    /** Bruit de fond : le plus petit niveau moyen des dernières secondes. */
    get floor() {
        return this.buckets.length === 0 ? 0 : Math.min(...this.buckets);
    }

    get threshold() {
        return Math.max(this.minLevel, this.floor * this.ratio);
    }

    /** Micro fermé (voix du professeur, pause, micro coupé) : plus aucune parole de l'apprenant. */
    reset(now) {
        const event = this.speaking ? { type: 'end', at: this.lastAbove ?? now } : null;
        this.speaking = false;
        this.aboveSince = null;

        return event;
    }

    /**
     * @return {?{type: 'start'|'end', at: number}}
     */
    update(now, level) {
        this.track(now, level);
        const above = level >= this.threshold;

        if (above) {
            this.lastAbove = now;
            this.aboveSince ??= now;

            if (!this.speaking && now - this.aboveSince >= this.startMs) {
                this.speaking = true;

                return { type: 'start', at: this.aboveSince };
            }

            return null;
        }

        this.aboveSince = null;

        if (this.speaking && now - this.lastAbove >= this.endMs) {
            this.speaking = false;

            return { type: 'end', at: this.lastAbove };
        }

        return null;
    }

    track(now, level) {
        if (this.bucket && now - this.bucket.start >= this.bucketMs) {
            this.buckets.push(this.bucket.sum / this.bucket.count);

            while (this.buckets.length > this.windowMs / this.bucketMs) {
                this.buckets.shift();
            }

            this.bucket = null;
        }

        this.bucket ??= { start: now, sum: 0, count: 0 };
        this.bucket.sum += level;
        this.bucket.count++;
    }
}

export class TurnWatch {
    /**
     * @param {number} endOfTurnMs   voix de l'apprenant finie depuis ce temps sans fin de tour du fournisseur :
     *                               le navigateur la signale lui-même
     * @param {number} minSpeechMs   parole trop courte (toux, bruit) : jamais de fin de tour signalée
     * @param {number} noAnswerMs    aucune voix du professeur ce temps après la fin de parole : connexion lente
     * @param {number} stuckMs       toujours rien : solution proposée (« Relancer »)
     * @param {number} lateMs        une réplique de l'apprenant transcrite jusqu'à ce temps après son tour lui appartient
     */
    constructor({ endOfTurnMs = 3000, minSpeechMs = 600, noAnswerMs = 8000, stuckMs = 20000, lateMs = 15000 } = {}) {
        Object.assign(this, { endOfTurnMs, minSpeechMs, noAnswerMs, stuckMs, lateMs });
        this.turn = null;
        this.last = null;
        this.ids = 0;
    }

    open(at) {
        if (!this.turn) {
            this.turn = { id: ++this.ids, micStart: null, micEnd: null, micActive: false, providerStart: null, providerEnd: null, turnEnd: null, forcedAt: null, warned: null };
        }

        this.turn.openedAt ??= at;

        return this.turn;
    }

    /** Début ou fin de la voix de l'apprenant, mesurés dans le navigateur. */
    micVoice(started, at) {
        if (started) {
            const turn = this.open(at);
            turn.micStart ??= at;
            turn.micActive = true;
            // L'apprenant reprend la parole : la fin mesurée avant ne compte plus.
            turn.micEnd = null;
            turn.forcedAt = null;
            turn.turnEnd = null;
            turn.warned = null;

            return;
        }

        if (this.turn?.micActive) {
            this.turn.micActive = false;
            this.turn.micEnd = at;
        }
    }

    /** Le fournisseur a détecté le début de la parole (OpenAI : speech_started ; Gemini : activité vocale). */
    providerStart(at) {
        this.open(at).providerStart ??= at;
    }

    /** Le fournisseur a détecté la fin de la parole : c'est aussi la fin du tour. */
    providerEnd(at) {
        if (this.turn) {
            this.turn.providerEnd = at;
            this.turn.turnEnd ??= at;
        }
    }

    /** Le fournisseur prépare sa réponse (Gemini : premier morceau de réponse) : fin du tour vue par lui. */
    providerResponding(at) {
        if (this.turn && (this.turn.micEnd !== null || this.turn.providerEnd !== null)) {
            this.turn.turnEnd ??= at;
        }
    }

    /** Fin de parole retenue : mesurée par le micro, sinon par le fournisseur. */
    learnerEnd(turn = this.turn) {
        return turn ? (turn.micEnd ?? turn.providerEnd) : null;
    }

    /**
     * Faut-il signaler soi-même la fin du tour ? L'apprenant a vraiment parlé, s'est tu depuis endOfTurnMs, et
     * le fournisseur n'a ni clos le tour ni commencé à répondre.
     */
    shouldEndTurn(now) {
        const turn = this.turn;

        return Boolean(turn && !turn.micActive && turn.micEnd !== null && turn.forcedAt === null && turn.turnEnd === null
            && turn.micEnd - turn.micStart >= this.minSpeechMs && now - turn.micEnd >= this.endOfTurnMs);
    }

    markEndOfTurn(now) {
        if (this.turn) {
            this.turn.forcedAt = now;
        }
    }

    /** Fin du tour déjà signalée par le navigateur (le micro reste fermé tant que l'apprenant ne reparle pas). */
    get endingTurn() {
        return Boolean(this.turn?.forcedAt && !this.turn.micActive);
    }

    /**
     * Attente d'une réponse : null, « thinking » (après noAnswerMs) ou « stuck » (après stuckMs).
     */
    waiting(now) {
        const end = this.learnerEnd();

        if (!this.turn || this.turn.micActive || end === null) {
            return null;
        }

        if (now - end >= this.stuckMs) {
            return 'stuck';
        }

        return now - end >= this.noAnswerMs ? 'thinking' : null;
    }

    /** Une seule fois par palier et par tour : vrai quand l'attente vient d'atteindre ce palier. */
    warnOnce(level) {
        if (!this.turn || this.turn.warned === level) {
            return false;
        }

        this.turn.warned = level;

        return true;
    }

    /**
     * Premier son de Teacher Joe : le tour se ferme et ses mesures sont rendues (null s'il répondait à une
     * indication, sans parole de l'apprenant).
     */
    teacherSound(at) {
        const turn = this.turn;
        this.turn = null;

        if (!turn || (turn.micStart === null && turn.providerStart === null)) {
            return null;
        }

        const end = this.learnerEnd(turn);
        const record = {
            id: turn.id,
            mic_start: turn.micStart,
            mic_end: turn.micEnd,
            provider_start: turn.providerStart,
            provider_end: turn.providerEnd,
            turn_end: turn.turnEnd,
            end_of_turn_sent: turn.forcedAt,
            teacher_start: at,
            delay_ms: end === null ? null : Math.max(0, Math.round(at - end)),
        };
        this.last = { ...record, closedAt: at };

        return record;
    }

    /**
     * Heure de la fin de parole quand on attend vraiment une réponse : l'apprenant a parlé au moins minMs (un
     * « Yes » suffit, pas un claquement) et s'est tu ; null sinon.
     */
    waitingSince(minMs = 250) {
        const turn = this.turn;
        const end = this.learnerEnd();

        if (!turn || turn.micActive || end === null) {
            return null;
        }

        const start = turn.micStart ?? turn.providerStart;

        return start === null || end - start >= minMs ? end : null;
    }

    /** Pause, interruption, reconnexion : le tour en cours n'est plus mesuré. */
    reset() {
        this.turn = null;
    }

    /**
     * Tour auquel appartient une réplique de l'apprenant qui commence maintenant : le tour en cours, sinon le
     * dernier tour fermé s'il l'a été il y a peu (la transcription peut arriver après la réponse).
     */
    turnFor(now) {
        if (this.turn) {
            return { id: this.turn.id, start: this.turn.micStart ?? this.turn.providerStart, record: null };
        }

        if (this.last && now - this.last.closedAt <= this.lateMs) {
            return { id: this.last.id, start: this.last.mic_start ?? this.last.provider_start, record: this.last };
        }

        return null;
    }
}

/**
 * Teacher Joe ne répond pas (blocage silencieux du fournisseur, réponse coupée en plein milieu) : étapes de
 * reprise AUTOMATIQUES, invisibles pour l'apprenant, avant le bouton « Relancer » :
 *   1. « nudge »     nudgeMs après la fin de parole : il reçoit l'indication de répondre ;
 *   2. « reconnect » reconnectMs plus tard, toujours rien : nouvelle connexion chez le même fournisseur, la
 *                    conversation continue (reprise) ;
 *   3. « switch »    switchMs plus tard, toujours rien : la séance continue chez l'autre fournisseur.
 * Une seule fois chaque étape par attente ; tout repart de zéro dès que sa voix arrive (waitedSince null).
 */
export class AnswerRecovery {
    constructor({ nudgeMs = 6000, reconnectMs = 8000, switchMs = 10000 } = {}) {
        this.delays = [nudgeMs, reconnectMs, switchMs];
        this.reset();
    }

    reset() {
        this.step = 0;
        this.at = 0;
    }

    /** Réponse coupée en plein milieu : on passe directement à la reconnexion. */
    stalled(now) {
        if (this.step === 0) {
            this.step = 1;
            this.at = now - this.delays[1];
        }
    }

    /**
     * @param {number} now
     * @param {?number} waitedSince  début de l'attente (fin de parole de l'apprenant, ou réponse coupée), null sinon
     * @return {?string} « nudge », « reconnect », « switch » ou null
     */
    next(now, waitedSince) {
        if (waitedSince === null || waitedSince === undefined) {
            this.reset();

            return null;
        }

        const delay = this.delays[this.step];
        const since = this.step === 0 ? waitedSince : this.at;

        if (delay === undefined || now - since < delay) {
            return null;
        }

        this.step++;
        this.at = now;

        return ['nudge', 'reconnect', 'switch'][this.step - 1];
    }
}

/**
 * Moment calme pour changer de connexion (renouvellement prévu) : ni la voix du professeur, ni une réponse en
 * cours, ni l'apprenant qui parle ou attend une réponse.
 *
 * @param {{teacherSpeaking: boolean, responding: boolean, learnerSpeaking: boolean, awaitingAnswer: boolean}} s
 */
export function quietMoment(s) {
    return !s.teacherSpeaking && !s.responding && !s.learnerSpeaking && !s.awaitingAnswer;
}

/**
 * Mesures d'un tour gardées avec la réplique de l'apprenant (heures en millisecondes).
 */
export function learnerMetrics(record) {
    return compact({
        mic_start: record.mic_start,
        mic_end: record.mic_end,
        provider_start: record.provider_start,
        provider_end: record.provider_end,
        turn_end: record.turn_end,
        end_of_turn_sent: record.end_of_turn_sent,
    });
}

/**
 * Mesures gardées avec la réplique de Teacher Joe : délai depuis la fin de la parole de l'apprenant.
 */
export function teacherMetrics(record, audioAt) {
    return compact({
        delay_ms: record?.delay_ms,
        learner_end: record ? (record.mic_end ?? record.provider_end) : null,
        turn_end: record?.turn_end,
        audio_at: audioAt,
    });
}

function compact(object) {
    return Object.fromEntries(Object.entries(object).filter(([, value]) => value !== null && value !== undefined).map(([key, value]) => [key, Math.round(value)]));
}
