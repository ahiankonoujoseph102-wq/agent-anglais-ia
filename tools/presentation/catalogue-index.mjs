// Index du catalogue des écrans (catalogue.mjs) : format de la vidéo unique, début et fin de chaque passage (une
// seconde de noir entre deux passages), texte à copier pour le monteur. Sans dépendance (testé par
// tests/js/catalogue.test.mjs).

export const WIDTH = 1080;
export const HEIGHT = 1920;
export const FPS = 30;
export const GAP = 1;

/** Index : début et fin de chaque passage dans la vidéo unique (une seconde de noir entre deux passages). */
export function timeline(list) {
    let at = 0;

    return list.map((passage, index) => {
        const start = at;
        at += passage.seconds;
        const entry = { number: index + 1, key: passage.key, title: passage.title, start, end: at, moments: (passage.moments ?? []).map(([t, text]) => [Math.round((start + t) * 10) / 10, text]) };
        at += GAP;

        return entry;
    });
}

export function clock(seconds) {
    const total = Math.round(seconds);

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

/** Texte de l'index, à copier pour le monteur. */
export function indexText(entries, voiceStart) {
    const lines = ['Catalogue des écrans de Talkami : une seule vidéo, passages séparés par une seconde de noir.', ''];

    for (const entry of entries) {
        lines.push(`${String(entry.number).padStart(2, '0')}. ${clock(entry.start)} à ${clock(entry.end)} : ${entry.title}`);

        for (const [t, text] of entry.moments) {
            lines.push(`      ${clock(t)} ${text}`);
        }
    }

    if (voiceStart !== null) {
        lines.push('', `Voix de Teacher Joe (fichier à part) : à placer à ${clock(voiceStart)}, début du passage où il parle.`);
    }

    return `${lines.join('\n')}\n`;
}

