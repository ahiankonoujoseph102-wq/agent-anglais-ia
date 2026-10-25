// Vidéos de présentation : découpage en prises, intervalles du plein écran et du médaillon, sous-titres et chapitres
// (tools/presentation/compose.mjs).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildAss, enableExpr, takes, windows } from '../../tools/presentation/compose.mjs';

const job = {
    site: 'talkami.laravel.cloud',
    render: { width: 1080, height: 1920, subtitles: { top: 1408, size: 48, chars: 56, side_margin: 40, right_margin: 140 }, chapter: { x: 625, y: 330, width: 310, size: 44 } },
    segments: [
        { shot: 'full', scene: 'prepare.pay', subtitle: 'Payer prend [en]trois étapes[/en].', chapter: null, medallion: true, closing: false, start: 0, end: 2 },
        { shot: 'screen', scene: 'pay.method', subtitle: 'Touchez votre moyen de paiement.', chapter: 'Payer', medallion: true, closing: false, start: 2.3, end: 5 },
        { shot: 'screen', scene: 'pay.step1', subtitle: 'Un : copiez le numéro.', chapter: null, medallion: false, closing: false, start: 5.3, end: 7 },
        { shot: 'full', scene: 'wait', subtitle: 'Rendez-vous sur talkami.laravel.cloud', chapter: null, medallion: true, closing: true, start: 7.3, end: 9 },
    ],
};

test('les phrases à l\'écran qui se suivent forment une seule prise, à la durée de leurs phrases', () => {
    const list = takes(job, 10.2);
    assert.equal(list.length, 1);
    assert.deepEqual(list[0].scenes, [{ name: 'pay.method', ms: 3000 }, { name: 'pay.step1', ms: 2000 }]);
    assert.equal(list[0].from, 2.3);
    assert.equal(list[0].to, 7.3);
    assert.equal(windows(job, 10.2).at(-1).to, 10.2);
});

test('les intervalles voisins sont réunis', () => {
    assert.equal(enableExpr([[0, 2.3], [2.3, 5]]), 'between(t,0.000,4.999)');
    assert.equal(enableExpr([]), '0');
});

test('sous-titres en bas, mots importants en orange, chapitre à droite du téléphone, adresse en gros à la fin', () => {
    const ass = buildAss(job, 10.2);
    assert.match(ass, /Style: Sub,Sora,48,.*,8,40,140,1408,1/);
    assert.match(ass, /Chapter,,0,0,0,,Payer/);
    // Le chapitre « Payer » reste affiché pendant la phrase suivante (sans chapitre), jusqu'au plein écran.
    assert.match(ass, /0:00:02\.30,0:00:07\.30,Chapter/);
    assert.match(ass, /\{\\c&H001A7AFF&\}trois/);
    assert.match(ass, /\\N\{\\fs67\\c&H001A7AFF&\}talkami\.laravel\.cloud/);
});

test('l\'écran a une image exacte à chaque trentième de seconde, du noir hors des prises', async () => {
    const { screenFrames } = await import('../../tools/presentation/compose.mjs');
    const list = screenFrames([{ from: 1, to: 2, frames: [{ t: 0, file: 'a' }, { t: 0.5, file: 'b' }] }], 3, 'noir');
    assert.equal(list.length, 90);
    assert.equal(list[0], 'noir');
    assert.equal(list[30], 'a');
    assert.equal(list[45], 'b');
    assert.equal(list[59], 'b');
    assert.equal(list[60], 'noir');
});

test('la ponctuation reste collée à un mot en orange', () => {
    const ass = buildAss({ ...job, segments: [{ ...job.segments[1], subtitle: 'Sur [en]talkami.laravel.cloud[/en], touchez.' }] }, 5);
    assert.match(ass, /talkami\.laravel\.cloud\{\\c&H00FFFFFF&\},/);
});

test('le bandeau en gros reste affiché sans clignoter tant que les phrases le gardent', () => {
    const withCard = { ...job, segments: [
        { ...job.segments[0], card: ['CE SOIR · 19 HEURES'] },
        { ...job.segments[0], start: 2.3, end: 5, card: ['CE SOIR · 19 HEURES', 'Groupe WhatsApp Talkami'] },
        { ...job.segments[0], start: 5.3, end: 7, card: ['CE SOIR · 19 HEURES', 'Groupe WhatsApp Talkami'] },
    ] };
    const cards = buildAss(withCard, 8).split('\n').filter((line) => line.includes(',Card,'));
    assert.equal(cards.length, 2);
    assert.match(cards[0], /0:00:00\.00,0:00:02\.30,Card,,0,0,0,,CE SOIR · 19 HEURES$/);
    assert.match(cards[1], /0:00:02\.30,0:00:08\.00,Card.*\\N\{\\fs60\\c&H001A7AFF&\}Groupe WhatsApp Talkami/);
});

test('publicité : l\'anglais du bandeau en orange, sous-titres remontés, bruitages seulement avec le réglage « sfx »', async () => {
    const { sfxEvents, SFX } = await import('../../tools/presentation/compose.mjs');
    const ad = {
        ...job,
        render: { ...job.render, subtitles: { top: 1040, size: 54, chars: 34, side_margin: 120, right_margin: 320 }, card: { top: 300, margin_left: 120, margin_right: 120 }, sfx: { volume_db: -14, lead: 0.05 } },
        segments: [
            { ...job.segments[0], card: ['[en]How much is it?[/en]'], sfx: 'pop' },
            { ...job.segments[0], start: 2.3, end: 5, card: ['[en]How much is it?[/en]', 'Vous répondez quoi ?'], sfx: null },
            { ...job.segments[1], start: 5.3, end: 7, sfx: 'ding' },
        ],
    };
    const ass = buildAss(ad, 8);
    assert.match(ass, /Style: Sub,Sora,54,.*,8,120,320,1040,1/);
    assert.match(ass, /Style: Card,Sora,92,.*,8,120,120,300,1/);
    assert.match(ass, /Card,,0,0,0,,\{\\c&H001A7AFF&\}How much is it\?\{\\c&H00FFFFFF&\}$/m);
    assert.deepEqual(sfxEvents(ad, ad.render.sfx), [{ kind: 'pop', at: 0 }, { kind: 'ding', at: 5.25 }]);
    // Les autres vidéos : aucun bruitage.
    assert.deepEqual(sfxEvents(job, undefined), []);
    assert.ok(SFX.pop.startsWith('aevalsrc=') && SFX.ding.startsWith('aevalsrc='));
});
