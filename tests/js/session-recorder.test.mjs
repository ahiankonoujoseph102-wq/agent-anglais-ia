// « Enregistrer ma séance » (public/js/session-recorder.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { adaptQuality, easeInOut, extensionFor, fileName, formatElapsed, formatSize, layoutBoard, mixCamera, pickMimeType, supportInfo, wrapLines } from '../../public/js/session-recorder.js';

test('le MP4 est préféré, sinon le WebM, sinon rien', () => {
    assert.match(pickMimeType(() => true), /^video\/mp4/);
    assert.match(pickMimeType((type) => type.startsWith('video/webm')), /^video\/webm;codecs=vp9/);
    assert.equal(pickMimeType((type) => type === 'video/webm'), 'video/webm');
    assert.equal(pickMimeType(() => false), null);
    assert.equal(pickMimeType(() => { throw new Error('refusé'); }), null);
    assert.equal(extensionFor('video/mp4;codecs=avc1'), 'mp4');
    assert.equal(extensionFor('video/webm;codecs=vp8,opus'), 'webm');
});

test('le nom du fichier est clair : plateforme, séance, date et heure', () => {
    assert.equal(fileName('talkami-seance', new Date(2026, 9, 22, 14, 5), 'mp4'), 'talkami-seance-22-10-2026-14h05.mp4');
    assert.equal(fileName('talkami-evaluation', new Date(2026, 0, 3, 9, 0), 'webm'), 'talkami-evaluation-03-01-2026-09h00.webm');
});

test('durée et taille sont écrites sans abréviation', () => {
    assert.equal(formatElapsed(0), '0:00');
    assert.equal(formatElapsed(125.7), '2:05');
    assert.equal(formatSize(300 * 1024), 'moins d\'un mégaoctet');
    assert.equal(formatSize(1024 * 1024), '1 mégaoctet');
    assert.equal(formatSize(12.4 * 1024 * 1024), '12 mégaoctets');
});

// Largeur simulée d'un texte : 0,55 × taille par caractère (proche de Sora).
const measure = (text, size = 10) => text.length * size * 0.55;
const lesson = {
    title: 'Bonjour ! Se saluer et se présenter',
    phase: 'Leçon',
    sections: [
        { heading: 'Phrases clés', items: [
            { en: 'Good morning, madam!', fr: 'Saluer selon le moment de la journée', lit: false },
            { en: 'I am Kofi', fr: 'Le verbe to be au présent', lit: true },
        ] },
    ],
    pitfall: 'En français on dit « j\'ai 20 ans », en anglais « I am 20 ».',
};
const box = { width: 512, height: 404, sizes: { max: 34, min: 24, window: 28 } };

test('un texte est coupé en lignes, un mot trop long aussi, et la dernière ligne finit par « … »', () => {
    const m = (t) => measure(t, 10);
    assert.deepEqual(wrapLines('one two three four', 60, m), ['one two', 'three four']);
    assert.ok(wrapLines('supercalifragilistic', 40, m).every((line) => m(line) <= 40));
    const cut = wrapLines('a b c d e f g h i j k l m n o p', 20, m, 2);
    assert.equal(cut.length, 2);
    assert.match(cut[1], /…$/);
    assert.deepEqual(wrapLines('', 100, m), []);
});

test('un tableau court tient en entier, en grand, piège compris', () => {
    const layout = layoutBoard(lesson, box, measure);
    assert.equal(layout.windowed, false);
    assert.ok(layout.size >= 28 && layout.size <= 34);
    assert.deepEqual(layout.blocks.map((b) => b.type), ['title', 'heading', 'item', 'item', 'heading', 'pitfall']);
    assert.ok(layout.blocks.find((b) => b.type === 'item' && b.lit).en.includes('I am Kofi'));
    assert.ok(layout.bottom <= box.height);
    // Sans traduction (B2 et plus) : seulement l'anglais.
    assert.ok(layoutBoard(lesson, { ...box, showFr: false }, measure).blocks.filter((b) => b.type === 'item').every((b) => b.fr.length === 0));
});

test('un tableau long : la taille baisse, puis seuls les éléments autour de celui qui est expliqué restent', () => {
    const items = Array.from({ length: 14 }, (_, i) => ({ en: `Phrase number ${i} that is rather long to read`, fr: `Traduction ${i}`, lit: i === 9 }));
    const long = { ...lesson, sections: [{ heading: 'Phrases clés', items: items.slice(0, 6) }, { heading: 'Vocabulaire', items: items.slice(6) }] };
    const layout = layoutBoard(long, box, measure);
    assert.equal(layout.windowed, true);
    assert.equal(layout.size, 28);
    const shown = layout.blocks.filter((b) => b.type === 'item').map((b) => b.en.join(' '));
    assert.ok(shown.some((text) => text.includes('number 9')));
    assert.ok(!shown.some((text) => text.includes('number 0 ')));
    // Le titre de la partie reste au-dessus des éléments montrés.
    assert.equal(layout.blocks.find((b) => b.type === 'heading').text, 'Vocabulaire');
    assert.ok(layout.bottom <= box.height);
    // Rien d'allumé : le début du tableau.
    const start = layoutBoard({ ...long, sections: long.sections.map((s) => ({ ...s, items: s.items.map((i) => ({ ...i, lit: false })) })) }, box, measure);
    assert.ok(start.blocks.filter((b) => b.type === 'item')[0].en.join(' ').includes('number 0'));
});

test('jamais plus de trois lignes d\'anglais ni deux de traduction par élément', () => {
    const huge = { ...lesson, sections: [{ heading: '', items: [{ en: 'word '.repeat(80), fr: 'mot '.repeat(80), lit: true }] }], pitfall: '' };
    const item = layoutBoard(huge, box, measure).blocks.find((b) => b.type === 'item');
    assert.ok(item.en.length <= 3 && item.fr.length <= 2);
});

test('transition douce, cadrage intermédiaire et protection de la séance', () => {
    assert.equal(easeInOut(0), 0);
    assert.equal(easeInOut(1), 1);
    assert.equal(easeInOut(2), 1);
    assert.ok(easeInOut(0.25) < 0.25 && easeInOut(0.75) > 0.75);
    const camera = mixCamera({ target: [0, 0.5, 0], half_height: 0.4, half_width: 0.26 }, { target: [0, 0.6, 0], half_height: 0.3, half_width: 0.32 }, 0.5);
    assert.deepEqual(camera.target.map((v) => +v.toFixed(3)), [0, 0.55, 0]);
    assert.equal(+camera.half_width.toFixed(3), 0.29);
    // Coût sous le budget : rien ne change ; au-dessus : moins de pixels, puis une image sur deux, jamais au-delà de 4.
    assert.deepEqual(adaptQuality({ scale: 1, every: 1 }, 8, 14), { scale: 1, every: 1 });
    assert.deepEqual(adaptQuality({ scale: 1, every: 1 }, 30, 14), { scale: 0.75, every: 1 });
    assert.deepEqual(adaptQuality({ scale: 0.75, every: 1 }, 30, 14), { scale: 0.5, every: 1 });
    assert.deepEqual(adaptQuality({ scale: 0.5, every: 1 }, 30, 14), { scale: 0.5, every: 2 });
    assert.deepEqual(adaptQuality({ scale: 0.5, every: 4 }, 30, 14), { scale: 0.5, every: 4 });
});

test('sans MediaRecorder, capture du canevas ou sortie audio en flux : pas d\'enregistrement', () => {
    const ok = {
        MediaRecorder: { isTypeSupported: (type) => type === 'video/webm' },
        HTMLCanvasElement: { prototype: { captureStream() {} } },
        AudioContext: { prototype: { createMediaStreamDestination() {} } },
    };
    assert.equal(supportInfo(ok), 'video/webm');
    assert.equal(supportInfo({ ...ok, MediaRecorder: undefined }), null);
    assert.equal(supportInfo({ ...ok, HTMLCanvasElement: { prototype: {} } }), null);
    assert.equal(supportInfo({ ...ok, AudioContext: { prototype: {} } }), null);
    assert.equal(supportInfo({ ...ok, MediaRecorder: { isTypeSupported: () => false } }), null);
});

test('la vidéo ne part jamais au serveur et le son n\'est jamais rejoué', () => {
    const code = readFileSync(new URL('../../public/js/session-recorder.js', import.meta.url), 'utf8');
    assert.ok(!/fetch\(|XMLHttpRequest|sendBeacon|WebSocket/.test(code));
    assert.ok(!code.includes('context.destination'));
    assert.ok(!code.includes('Teacher Joe') && !code.includes('Talkami'));
    // Le tableau est dessiné à partir de ses données, jamais une copie de l'écran.
    assert.ok(!/html2canvas|getDisplayMedia|foreignObject/.test(code));
});
