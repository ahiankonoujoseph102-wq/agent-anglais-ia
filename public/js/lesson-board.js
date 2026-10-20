/*
 * Tableau de la séance du jour : quel élément Teacher Joe est en train d'expliquer, et à quelle étape en est la
 * séance (leçon, pratique, conversation, bilan). Tout est déduit de la transcription de sa voix, sans appel de
 * fonction (les appels de fonction de l'API Live faisaient répéter le modèle), comme la phrase de fin de
 * l'évaluation ; l'étape suit aussi le temps écoulé quand la phrase de passage n'a pas été entendue.
 * Logique pure (BoardTracker), testée par tests/js/lesson-board.test.mjs ; affichage : BoardView.
 */

export const PHASES = ['lesson', 'practice', 'conversation', 'summary'];

export const PHASE_LABELS = {
    lesson: 'Leçon',
    practice: 'Pratique',
    conversation: 'Conversation',
    summary: 'Bilan',
};

/**
 * Texte comparable : minuscules, sans accents, sans apostrophes (« let's » = « lets ») ni ponctuation.
 */
export function normalizeSpeech(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/['’]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/**
 * Ce qu'il faut entendre pour allumer un élément du tableau : une phrase (ses 6 premiers mots au plus), ou
 * chaque forme d'un mot (« mother / mom », « went (go) » → « went »).
 */
export function keysFor(text, kind = 'phrase') {
    const raw = String(text ?? '');

    if (kind === 'phrase') {
        const words = normalizeSpeech(raw).split(' ').filter(Boolean);

        return words.length ? [words.slice(0, 6).join(' ')] : [];
    }

    if (kind === 'verb') {
        const forms = raw.split(/[\/,]/).map((form) => normalizeSpeech(form)).filter(Boolean);
        const keys = forms.length > 1 ? [forms.join(' ')] : [];

        // Formes assez longues pour ne pas s'allumer à tort (« went », « gone », « bought »).
        forms.slice(1).forEach((form) => { if (form.length >= 4) keys.push(form); });

        return [...new Set(keys)];
    }

    return raw
        .replace(/\([^)]*\)/g, ' ')
        .split('/')
        .map((part) => normalizeSpeech(part))
        .filter((part) => part.length >= 2);
}

export class BoardTracker {
    /**
     * @param {{id: string, kind?: string, keys: string[], span?: number}[]} items   éléments du tableau, dans l'ordre
     * @param {{phrases?: Object, starts?: Object, merged?: boolean}} phases  envoyées par le serveur (/connexion)
     * @param {number} grace  retard toléré (secondes) avant de suivre le temps quand la phrase de passage manque
     */
    constructor(items = [], phases = {}, grace = 90) {
        this.items = items.map((item) => ({ ...item, from: 0 }));
        this.grace = grace;
        this.raw = '';
        this.detected = 0;
        this.current = null;
        this.configure(phases);
    }

    configure({ phrases = {}, starts = {}, merged = false } = {}) {
        this.phrases = Object.fromEntries(Object.entries(phrases).map(([phase, phrase]) => [phase, normalizeSpeech(phrase)]));
        this.phraseFrom = Object.fromEntries(Object.keys(this.phrases).map((phase) => [phase, 0]));
        this.starts = starts;
        this.merged = merged;
    }

    /**
     * Nouveau morceau de la transcription du professeur. Renvoie l'élément qui s'allume (ou null).
     */
    feed(text) {
        this.raw += String(text ?? '');
        // Le dernier mot peut être incomplet (« sis… ») : on ne cherche que jusqu'au dernier espace.
        const normalized = ` ${normalizeSpeech(this.raw)} `;
        const complete = /[\s.,!?;:]$/.test(this.raw) ? normalized : normalized.slice(0, normalized.trimEnd().lastIndexOf(' ') + 1);

        for (const [phase, phrase] of Object.entries(this.phrases)) {
            const at = phrase ? complete.indexOf(` ${phrase} `, this.phraseFrom[phase]) : -1;

            if (at >= 0) {
                this.phraseFrom[phase] = at + 1;
                this.detected = Math.max(this.detected, PHASES.indexOf(phase));
            }
        }

        // Les phrases d'abord : un mot dit à l'intérieur d'une phrase du tableau ne s'allume pas seul.
        const found = [];
        const spans = [];

        for (const item of [...this.items].sort((a, b) => (a.kind === 'phrase' ? 0 : 1) - (b.kind === 'phrase' ? 0 : 1))) {
            for (const key of item.keys) {
                // Dernière fois que le professeur l'a dit (après la précédente déjà comptée).
                const at = complete.lastIndexOf(` ${key} `);

                if (at < item.from) {
                    continue;
                }

                const end = at + (item.kind === 'phrase' ? Math.max(key.length, item.span ?? 0) : key.length);

                if (item.kind !== 'phrase' && spans.some(([from, to]) => at >= from && at < to)) {
                    item.from = at + key.length + 1;
                    continue;
                }

                if (item.kind === 'phrase') {
                    spans.push([at, end + 1]);
                }

                item.from = at + key.length + 1;
                found.push({ id: item.id, end, length: key.length });
            }
        }

        // Le dernier élément nommé s'allume ; à fin égale, le plus long (« go, went, gone » plutôt que « went »).
        let lit = null;

        for (const match of found) {
            if (lit === null || match.end > lit.end || (match.end === lit.end && match.length > lit.length)) {
                lit = match;
            }
        }

        if (lit !== null) {
            this.current = lit.id;
        }

        return lit?.id ?? null;
    }

    /**
     * Étape en cours : la plus avancée entre celle annoncée par le professeur et celle du temps écoulé (avec un
     * retard toléré, sauf pour le bilan, déclenché à l'heure par l'indication de fin).
     */
    phase(elapsed) {
        let byTime = 0;

        PHASES.forEach((phase, index) => {
            const start = this.starts?.[phase];

            if (index > 0 && typeof start === 'number' && elapsed >= start + (phase === 'summary' ? 0 : this.grace)) {
                byTime = Math.max(byTime, index);
            }
        });

        let index = Math.max(this.detected, byTime);

        // Séance courte : leçon et pratique ne font qu'un.
        if (this.merged && index === 1) {
            index = 0;
        }

        return PHASES[index];
    }

    /**
     * Une seule relance douce : la conversation aurait dû commencer depuis une minute et le professeur ne l'a pas annoncée.
     */
    needsConversationNudge(elapsed) {
        const start = this.starts?.conversation;

        return typeof start === 'number' && start > 0 && this.detected < 2 && elapsed >= start + 60
            && elapsed < (this.starts?.summary ?? Infinity);
    }
}

/**
 * Affichage du tableau (#board) : étape en cours, élément allumé, éléments déjà vus.
 */
export class BoardView {
    constructor(root, labels = PHASE_LABELS) {
        this.root = root;
        this.labels = labels;
        this.phaseEl = root.querySelector('[data-board-phase]');
        this.nowEl = root.querySelector('[data-board-now]');
        this.elements = new Map([...root.querySelectorAll('[data-board-item]')].map((el) => [el.dataset.boardItem, el]));
        this.lastPhase = null;
    }

    items() {
        return [...this.elements.values()].map((el) => ({
            id: el.dataset.boardItem,
            kind: el.dataset.kind,
            keys: keysFor(el.dataset.say, el.dataset.kind),
            // Phrase : toute sa longueur (les mots qu'elle contient ne s'allument pas seuls).
            span: normalizeSpeech(el.dataset.say).length,
            label: el.dataset.say,
        }));
    }

    light(id) {
        const el = this.elements.get(id);

        if (!el) {
            return;
        }

        this.root.querySelectorAll('.is-lit').forEach((other) => {
            other.classList.remove('is-lit');
            other.classList.add('is-seen');
        });
        el.classList.add('is-lit');
        el.classList.remove('is-seen');

        if (this.nowEl) {
            this.nowEl.textContent = el.dataset.say;
        }
    }

    setPhase(phase, merged = false) {
        if (phase === this.lastPhase) {
            return;
        }

        this.lastPhase = phase;
        this.root.dataset.phase = phase;

        if (this.phaseEl) {
            this.phaseEl.textContent = phase === 'lesson' && merged ? 'Leçon et pratique' : this.labels[phase];
        }
    }

    label(id) {
        return this.elements.get(id)?.dataset.say ?? null;
    }
}
