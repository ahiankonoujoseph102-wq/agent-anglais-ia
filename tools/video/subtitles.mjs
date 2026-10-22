// Sous-titres incrustés des vidéos de leçon (format ASS, dessinés par ffmpeg avec la police Sora) : titre en haut,
// sous-titres en gros dans le tiers bas, mots anglais en orange, phrase de fin avec l'adresse du site en orange.
// Testé par tests/js/video-subtitles.test.mjs.

const WHITE = '&H00FFFFFF&';

/** Couleur CSS « #RRGGBB » → couleur ASS « &H00BBGGRR& ». */
export function assColor(hex) {
    const value = hex.replace('#', '');

    return `&H00${value.slice(4, 6)}${value.slice(2, 4)}${value.slice(0, 2)}&`.toUpperCase();
}

/** Heure ASS « h:mm:ss.cc ». */
export function assTime(seconds) {
    const cs = Math.max(0, Math.round(seconds * 100));
    const h = Math.floor(cs / 360000);
    const m = Math.floor((cs % 360000) / 6000);
    const s = Math.floor((cs % 6000) / 100);

    return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}.${String(cs % 100).padStart(2, '0')}`;
}

/** Texte sûr pour ASS : ni accolades, ni barre oblique inverse, ni retour à la ligne. */
function clean(text) {
    return text.replace(/[{}\\]/g, '').replace(/\s+/g, ' ');
}

/**
 * Mots d'un sous-titre, chacun marqué anglais ou non ([en]…[/en]). « glue » : collé au mot précédent, sans espace
 * (virgule juste après une balise : « [en]Hello[/en], »).
 * @returns {{word: string, en: boolean, glue?: boolean}[]}
 */
export function words(subtitle) {
    const result = [];
    let en = false;
    let previous = '';

    for (const piece of subtitle.split(/(\[\/?en\])/)) {
        if (piece === '[en]' || piece === '[/en]') {
            en = piece === '[en]';
            continue;
        }

        // Collé seulement si rien ne sépare ce morceau du précédent (ni espace avant la balise, ni après).
        const touching = result.length > 0 && !/\s$/.test(previous) && !/^\s/.test(piece);

        piece.split(/\s+/).filter((word) => clean(word) !== '').forEach((word, index) => {
            result.push(index === 0 && touching ? { word: clean(word), en, glue: true } : { word: clean(word), en });
        });

        if (piece !== '') {
            previous = piece;
        }
    }

    return result;
}

/**
 * Découpe un sous-titre en morceaux de « max » caractères au plus, de préférence après une fin de phrase ou une virgule.
 * @returns {{word: string, en: boolean}[][]}
 */
export function chunk(subtitle, max = 70) {
    const list = words(subtitle);
    const chunks = [];
    let current = [];
    let length = 0;

    list.forEach(({ word, en }, index) => {
        if (current.length > 0 && length + 1 + word.length > max) {
            chunks.push(current);
            current = [];
            length = 0;
        }

        current.push({ word, en });
        length += (length > 0 ? 1 : 0) + word.length;

        // Fin de phrase au-delà de la moitié : on coupe là, plus naturel à lire.
        const next = list[index + 1];

        if (next && /[.!?:]$/.test(word) && length > max * 0.45) {
            chunks.push(current);
            current = [];
            length = 0;
        }
    });

    if (current.length > 0) {
        chunks.push(current);
    }

    return chunks;
}

/** Morceau → texte ASS, mots anglais en couleur d'accent. */
export function render(chunkWords, accent) {
    let out = '';
    let en = false;

    chunkWords.forEach(({ word, en: isEn, glue }, index) => {
        out += index > 0 && !glue ? separator(chunkWords[index - 1].word, word) : '';

        if (isEn !== en) {
            out += isEn ? `{\\c${accent}}` : `{\\c${WHITE}}`;
            en = isEn;
        }

        out += word;
    });

    return out;
}

/**
 * Espace entre deux mots : insécable (\h de libass) devant « : ; ! ? » » et après « « », comme en typographie
 * française, pour qu'aucune ligne ne commence par une ponctuation.
 */
export function separator(previous, word) {
    return /^[:;!?»]/.test(word) || /«$/.test(previous) ? '\\h' : ' ';
}

/** Adresse du site mise en valeur dans la phrase de fin : seule sur sa ligne, en gros et en orange. */
export function highlightSite(text, site, accent, size) {
    if (!site || !text.includes(site)) {
        return text;
    }

    const [before, after] = [text.slice(0, text.lastIndexOf(site)), text.slice(text.lastIndexOf(site) + site.length)];

    return `${before.replace(/(\\h|\s)+$/, '')}\\N{\\fs${size}\\c${accent}}${site}{\\r}${after}`;
}

/** Couleur ASS avec transparence (0 = opaque, 255 = invisible). */
function withAlpha(color, alpha) {
    return color.replace('&H00', `&H${alpha.toString(16).padStart(2, '0').toUpperCase()}`);
}

/**
 * Fichier ASS complet d'une vidéo : titre discret en haut (sous les onglets de TikTok), nom du professeur en
 * filigrane, sous-titres en gros sous le menton (marges qui évitent les icônes de droite de TikTok, rien dans le bas
 * de l'image), adresse du site en gros à la fin.
 * @param {object} job travail donné par Talkami (title, segments [{subtitle, start, end, closing}], site, render)
 * @param {number} total durée totale de la vidéo, en secondes
 */
export function buildAss(job, total) {
    const r = job.render;
    const t = r.text ?? {};
    const accent = assColor(r.accent);
    const side = t.side_margin ?? 120;
    const subtitleTop = t.subtitle_top ?? 1180;
    const box = '&H70000000&';
    const lines = [
        '[Script Info]',
        'ScriptType: v4.00+',
        `PlayResX: ${r.width}`,
        `PlayResY: ${r.height}`,
        'WrapStyle: 0',
        'ScaledBorderAndShadow: yes',
        '',
        '[V4+ Styles]',
        'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
        `Style: Title,Sora,${t.title_size ?? 46},${WHITE},${WHITE},${box},${box},-1,0,0,0,100,100,0,0,3,10,0,8,${side},${side},${t.title_top ?? 175},1`,
        `Style: Mark,Sora,${t.watermark_size ?? 32},${withAlpha(WHITE, 0x40)},${WHITE},&H00000000&,&H00000000&,-1,0,0,0,100,100,1,0,1,0,0,9,0,${t.watermark_right ?? 140},${t.watermark_top ?? 700},1`,
        `Style: Sub,Sora,${t.subtitle_size ?? 66},${WHITE},${WHITE},${box},${box},-1,0,0,0,100,100,0,0,3,14,0,8,${side},${side},${subtitleTop},1`,
        `Style: End,Sora,${t.closing_size ?? 60},${WHITE},${WHITE},${box},${box},-1,0,0,0,100,100,0,0,3,14,0,8,${side},${side},${subtitleTop},1`,
        '',
        '[Events]',
        'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text',
        `Dialogue: 0,${assTime(0)},${assTime(total)},Title,,0,0,0,,${clean(job.title)}`,
    ];

    if (r.watermark) {
        lines.push(`Dialogue: 0,${assTime(0)},${assTime(total)},Mark,,0,0,0,,${clean(r.watermark)}`);
    }

    job.segments.forEach((segment, index) => {
        const next = job.segments[index + 1];
        // Le sous-titre reste affiché pendant le silence qui suit (jusqu'à la partie suivante, ou la fin).
        const end = next ? next.start : total;

        if (segment.closing) {
            const plain = words(segment.subtitle.replace(/\[\/?en\]/g, '')).map(({ word }) => ({ word, en: false }));
            const text = highlightSite(render(plain, accent), job.site, accent, t.site_size ?? 84);
            lines.push(`Dialogue: 0,${assTime(segment.start)},${assTime(end)},End,,0,0,0,,${text}`);

            return;
        }

        const pieces = chunk(segment.subtitle);
        const weights = pieces.map((piece) => piece.reduce((sum, { word }) => sum + word.length + 1, 0));
        const totalWeight = weights.reduce((sum, w) => sum + w, 0) || 1;
        const span = Math.max(0.5, segment.end - segment.start);
        let at = segment.start;

        pieces.forEach((piece, i) => {
            const until = i === pieces.length - 1 ? end : at + span * (weights[i] / totalWeight);
            lines.push(`Dialogue: 0,${assTime(at)},${assTime(until)},Sub,,0,0,0,,${render(piece, accent)}`);
            at = until;
        });
    });

    return `${lines.join('\n')}\n`;
}
