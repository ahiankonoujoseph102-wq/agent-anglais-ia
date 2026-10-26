// Catalogue des écrans pour un montage (tools/presentation/catalogue-index.mjs, catalogue-scenes.mjs) : passages bout
// à bout séparés d'une seconde de noir, index en minutes et secondes, rien d'ajouté à l'écran.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { GAP, clock, indexText, timeline } from '../../tools/presentation/catalogue-index.mjs';
import { CALM, passages } from '../../tools/presentation/catalogue-scenes.mjs';

const list = passages({ attestationCode: 'A1-CODE', courseId: 7 });

test('chaque passage dure de 10 à 20 secondes et commence par 2 secondes calmes', () => {
    assert.equal(CALM, 2000);

    for (const passage of list) {
        assert.ok(passage.seconds >= 10 && passage.seconds <= 20, passage.key);
        assert.ok(passage.title.length > 10, passage.key);
    }

    assert.equal(new Set(list.map((p) => p.key)).size, list.length);
});

test('le catalogue couvre tout le site demandé, dans l\'ordre', () => {
    const keys = list.map((p) => p.key);
    const order = ['accueil-haut', 'accueil-defilement-1', 'teacher-joe-parle', 'inscription', 'evaluation-commencer', 'evaluation-a-vous', 'evaluation-niveau', 'mon-espace', 'programme', 'seance-lecon', 'seance-pratique', 'seance-conversation', 'seance-bilan', 'mes-cours-ajouter', 'mes-cours-explication', 'attestations', 'formules', 'abonnement', 'paiement', 'comment-ca-marche', 'installer', 'contact', 'a-propos', 'administration-videos'];
    let previous = -1;

    for (const key of order) {
        const at = keys.indexOf(key);
        assert.ok(at > previous, `${key} manquant ou hors de l'ordre`);
        previous = at;
    }

    assert.equal(list.filter((p) => p.teacher).length, 1);
});

test('passages bout à bout, une seconde de noir entre deux, moments forts dans la vidéo unique', () => {
    const entries = timeline([{ key: 'a', title: 'A', seconds: 12, moments: [[5, 'doigt']] }, { key: 'b', title: 'B', seconds: 20 }]);
    assert.equal(GAP, 1);
    assert.deepEqual(entries.map((e) => [e.start, e.end]), [[0, 12], [13, 33]]);
    assert.deepEqual(entries[0].moments, [[5, 'doigt']]);
    assert.equal(clock(65.4), '1:05');
    const text = indexText(entries, 13);
    assert.match(text, /01\. 0:00 à 0:12 : A/);
    assert.match(text, /02\. 0:13 à 0:33 : B/);
    assert.match(text, /à placer à 0:13/);
});

test('aucune marque ajoutée à l\'écran : ni cercle, ni flèche, ni zoom, ni sous-titre', () => {
    const source = readFileSync(new URL('../../tools/presentation/catalogue-scenes.mjs', import.meta.url), 'utf8');
    assert.doesNotMatch(source, /s\.(circle|circleAny|circleText|arrow|arrowText|zoom|scrollText|questionnaireScreen|planCard)\(/);
});

test('aucun paiement : la capture n\'est jamais choisie ni envoyée', () => {
    const source = readFileSync(new URL('../../tools/presentation/catalogue-scenes.mjs', import.meta.url), 'utf8');
    assert.doesNotMatch(source, /chooseDemoProof|data-proof-submit/);
});

test('pendant un tournage, Teacher Joe reste animé même sur une machine lente', () => {
    const record = readFileSync(new URL('../../tools/presentation/record.mjs', import.meta.url), 'utf8');
    const teacher = readFileSync(new URL('../../public/js/teacher-3d.js', import.meta.url), 'utf8');
    assert.match(record, /window\.__filming = true/);
    assert.match(teacher, /s\.quality >= 2 && !window\.__filming\) \{\s*(\/\/[^\n]*\n\s*)*this\.fail\('too slow'\)/);
});

test('avant la séance « Mes cours », les séances du jour restées ouvertes sont closes', () => {
    const source = readFileSync(new URL('../../tools/presentation/catalogue.mjs', import.meta.url), 'utf8');
    assert.match(source, /passage\.live === 'course'\) \{\s*closeOpenLessons\(env\);/);
    assert.match(source, /LessonStatus::InProgress\)->get\(\)->each\(fn \(\$lesson\) => \$lesson->finish\(now\(\)\)\)/);
});

test('pendant un tournage, Teacher Joe est dessiné plus légèrement (15 images par seconde, définition 1)', () => {
    const teacher = readFileSync(new URL('../../public/js/teacher-3d.js', import.meta.url), 'utf8');
    assert.match(teacher, /elapsed < 1000 \/ \(window\.__filming \? 15\.5 : 31\)/);
    assert.match(teacher, /this\.video \|\| window\.__filming \? 1 :/);
});

test('jamais le visage simple : attente plus longue au tournage, et rien n\'est déposé s\'il apparaît', () => {
    const view = readFileSync(new URL('../../public/js/teacher-view.js', import.meta.url), 'utf8');
    const catalogue = readFileSync(new URL('../../tools/presentation/catalogue.mjs', import.meta.url), 'utf8');
    assert.match(view, /withTimeout\(attempt, window\.__filming \? 180000 : LOAD_TIMEOUT_MS\)/);
    assert.match(catalogue, /\[data-teacher="svg"\]/);
    assert.match(catalogue, /if \(simpleFace\.length > 0\) \{\s*throw new Error/);
});
