// Garde-fou des lèvres des vidéos (tools/video/lip-check.mjs) et voix réellement jouée dans la page de rendu.
// Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { correlation, lipCheck, openness } from '../../tools/video/lip-check.mjs';

// Une voix de 10 secondes à 25 images par seconde : des syllabes (énergie qui monte et descend) et des silences.
const rms = Array.from({ length: 250 }, (_, i) => (i % 50 < 35 ? 0.08 + 0.07 * Math.sin(i * 1.3) ** 2 : 0));

test('la corrélation de Pearson est juste', () => {
    assert.equal(correlation([1, 2, 3], [2, 4, 6]), 1);
    assert.equal(correlation([1, 2, 3], [3, 2, 1]), -1);
    assert.equal(correlation([1, 1, 1], [1, 2, 3]), 0);
});

test('l\'ouverture de la bouche vient de la mâchoire et des formes de bouche', () => {
    assert.equal(openness({}), 0);
    assert.equal(openness({ jawOpen: 0.4, viseme_aa: 0.2 }), 0.6000000000000001);
    assert.ok(openness({ viseme_O: 1 }) > openness({ viseme_SS: 1 }));
});

test('une bouche qui suit la voix passe', () => {
    const open = rms.map((v) => v * 3);
    const result = lipCheck(rms, open);
    assert.equal(result.ok, true);
    assert.equal(result.correlation, 1);
    assert.equal(result.lag, 0);
    assert.ok(result.open_speech > 0.2);
    assert.equal(result.open_silence, 0);
});

test('une bouche fermée pendant la parole échoue (le défaut de la première vidéo)', () => {
    const result = lipCheck(rms, rms.map(() => 0));
    assert.equal(result.ok, false);
    assert.match(result.reason, /bouche fermée pendant la parole/);
});

test('une bouche qui bouge au hasard, sans suivre la voix, échoue', () => {
    let seed = 7;
    const random = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
    const result = lipCheck(rms, rms.map(() => random() * 0.5));
    assert.equal(result.ok, false);
    assert.match(result.reason, /mal synchronisées/);
});

test('un décalage de plus d\'une image n\'est pas toléré', () => {
    const late = [0, 0, 0, ...rms.slice(0, -3)].map((v) => v * 3);
    assert.equal(lipCheck(rms, [0, ...rms.slice(0, -1)].map((v) => v * 3)).ok, true);
    assert.ok(lipCheck(rms, late).correlation < lipCheck(rms, rms.map((v) => v * 3)).correlation);
});

test('sans parole, rien n\'est déposé', () => {
    const silent = rms.map(() => 0);
    assert.equal(lipCheck(silent, silent).ok, false);
});

test('la page de rendu JOUE la voix (source.start) avant le calcul, et vérifie les lèvres', () => {
    const html = readFileSync(new URL('../../tools/video/render.html', import.meta.url), 'utf8');
    const start = html.indexOf('source.start(0)');
    assert.ok(start > 0, 'source.start(0) a disparu de render.html : la bouche ne bougerait plus');
    assert.ok(start < html.indexOf('context.startRendering()'));
    assert.ok(html.indexOf('teacher.attachAudio(context, source)') < start);
    assert.match(html, /rms\.push\(/);
    assert.match(html, /open\.push\(/);

    const runner = readFileSync(new URL('../../tools/video/render.mjs', import.meta.url), 'utf8');
    assert.match(runner, /lipCheck\(result\.rms, result\.open/);
    assert.match(runner, /if \(!lips\.ok\) \{\s*throw/);
});
