// Sous-titres des vidéos de leçon (tools/video/subtitles.mjs). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { assColor, assTime, buildAss, chunk, render, separator, words } from '../../tools/video/subtitles.mjs';

const job = {
    title: 'Se présenter en anglais',
    platform: 'Talkami',
    site: 'talkami.laravel.cloud',
    segments: [
        { subtitle: 'Pour dire votre nom : [en]My name is Afi.[/en]', start: 0, end: 3, closing: false },
        { subtitle: 'Pour aller plus loin, évaluez gratuitement votre niveau d\'anglais sur talkami.laravel.cloud.', start: 3.5, end: 7, closing: true },
    ],
    render: { width: 1080, height: 1920, avatar_width: 720, avatar_height: 640, avatar_top: 340, accent: '#FF7A1A' },
};

test('les couleurs et les heures sont au format ASS', () => {
    assert.equal(assColor('#FF7A1A'), '&H001A7AFF&');
    assert.equal(assTime(0), '0:00:00.00');
    assert.equal(assTime(83.456), '0:01:23.46');
});

test('les mots anglais sont reconnus et mis en orange', () => {
    const list = words('On dit [en]I\'m twenty-five[/en], pas « I have ».');
    assert.deepEqual(list.filter((w) => w.en).map((w) => w.word), ['I\'m', 'twenty-five']);
    assert.match(render(list, '&H001A7AFF&'), /On dit \{\\c&H001A7AFF&\}I'm twenty-five\{\\c&H00FFFFFF&\}, pas/);
    assert.match(render(words('Votre nom :\u0020[en]My name[/en] ici.'), '&H001A7AFF&'), /nom\\h: \{\\c&H001A7AFF&\}My name \{\\c&H00FFFFFF&\}ici\./);
});

test('aucune ligne ne commence par une ponctuation française', () => {
    assert.equal(separator('nom', ':'), '\\h');
    assert.equal(separator('«', 'bonjour'), '\\h');
    assert.equal(separator('votre', 'nom'), ' ');
});

test('un long sous-titre est coupé en morceaux courts, de préférence après une phrase', () => {
    const long = 'Première phrase assez longue pour remplir une ligne entière. Deuxième phrase qui suit avec encore des mots, et puis une troisième partie.';
    const pieces = chunk(long, 70);
    assert.ok(pieces.length >= 2);
    pieces.forEach((piece) => assert.ok(piece.map((w) => w.word).join(' ').length <= 70));
    assert.match(pieces[0].at(-1).word, /\.$/);
});

test('le fichier ASS a le titre, les sous-titres et la phrase de fin avec l\'adresse en orange', () => {
    const ass = buildAss(job, 8.2);
    assert.match(ass, /PlayResX: 1080/);
    assert.match(ass, /,Title,,0,0,0,,Se présenter en anglais/);
    assert.match(ass, /,Brand,,0,0,0,,TALKAMI/);
    assert.match(ass, /,End,,0,0,0,,Pour aller plus loin.*\{\\c&H001A7AFF&\}talkami\.laravel\.cloud/);
    // Le dernier sous-titre ordinaire reste affiché jusqu'à la phrase suivante.
    assert.match(ass, /Dialogue: 0,0:00:00\.00,0:00:03\.50,Sub/);
    assert.ok(!ass.includes('[en]'));
});
