// Tableau noir du décor 3D (public/js/teacher-3d.js). Lancé par tests/Feature/JavaScriptTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { boardLayout, BOARD_LINES } from '../../public/js/teacher-3d.js';

// Même cadrage que config/avatar.php et resize().
const camera = { target: [0, 0.57, 0], halfHeight: 0.33, halfWidth: 0.30 };

function distanceFor(aspect) {
    return Math.max(camera.halfHeight, camera.halfWidth / aspect) / Math.tan((30 * Math.PI / 180) / 2);
}

test('le tableau tient entier dans l\'image, du téléphone en portrait à l\'ordinateur', () => {
    // 360 × 360 (carré), portrait étroit, portrait, paysage, très large.
    for (const aspect of [1, 0.7, 0.75, 4 / 3, 16 / 9, 2.4]) {
        const layout = boardLayout({ aspect, distance: distanceFor(aspect), target: camera.target });
        assert.ok(layout, `tableau absent pour ${aspect}`);
        assert.ok(layout.left > layout.visible.left, `bord gauche coupé pour ${aspect}`);
        assert.ok(layout.right < layout.visible.right, `bord droit coupé pour ${aspect}`);
        assert.ok(layout.top < layout.visible.top, `haut coupé pour ${aspect}`);
        // Toujours à gauche de la tête du professeur, assez large pour « Hello! » (le texte est réduit à la largeur).
        assert.ok(layout.right <= -0.2);
        assert.ok(layout.right - layout.left >= 0.12);
        assert.ok(layout.lines.length >= 1 && layout.lines[0] === 'Hello!');
    }
});

test('le texte du tableau est court', () => {
    assert.deepEqual(BOARD_LINES, ['Hello!', "Let's talk!"]);
    assert.ok(BOARD_LINES.every((line) => line.length <= 12));
});

test('image très étroite : pas de tableau plutôt qu\'un tableau coupé', () => {
    for (const aspect of [0.4, 0.5, 0.6]) {
        const layout = boardLayout({ aspect, distance: distanceFor(aspect), target: camera.target });
        assert.ok(layout === null || layout.left > layout.visible.left);
    }
});

test('tableau étroit : seulement « Hello! », large : les deux lignes', () => {
    assert.deepEqual(boardLayout({ aspect: 0.7, distance: distanceFor(0.7), target: camera.target }).lines, ['Hello!']);
    assert.deepEqual(boardLayout({ aspect: 16 / 9, distance: distanceFor(16 / 9), target: camera.target }).lines, BOARD_LINES);
});

test('sur un grand écran, le tableau garde sa taille d\'origine au plus', () => {
    const layout = boardLayout({ aspect: 3, distance: distanceFor(3), target: camera.target });
    assert.ok(layout.left >= -1.12);
    assert.ok(layout.top <= 1.065);
});
