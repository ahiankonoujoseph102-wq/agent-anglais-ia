// Arrêt d'une machine trop lente pendant la fabrication d'une vidéo (tools/video/render.html).
// Le 29 octobre 2026, la vidéo du Club a été refusée après 250 images (machine trop lente) : l'erreur, levée dans
// le rappel d'une pause du contexte audio hors ligne, n'était jamais suivie d'une reprise ; le rendu attendait pour
// toujours et GitHub a annulé le passage à sa limite, sans rendre la vidéo à Talkami.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const html = readFileSync(new URL('../../tools/video/render.html', import.meta.url), 'utf8');

// Faux contexte audio hors ligne : chaque pause attend sa reprise, comme dans Chromium.
class FakeOfflineContext {
    constructor() {
        this.pauses = [];
    }

    suspend(at) {
        return new Promise((resolve) => this.pauses.push({ at, resolve }));
    }

    async startRendering() {
        for (const pause of this.pauses.sort((a, b) => a.at - b.at)) {
            const resumed = new Promise((resolve) => (this.resume = resolve));
            pause.resolve();
            await resumed;
        }
    }
}

// Boucle des images pendant la voix, recopiée de render.html (de « let failure » jusqu'à la levée de l'erreur).
function loopFromPage() {
    const start = html.indexOf('let failure = null;');
    const end = html.indexOf('throw failure;', start);
    assert.ok(start > 0 && end > start, 'boucle des images introuvable dans render.html');

    return html.slice(start, html.indexOf('}', end) + 1);
}

test('une image refusée arrête le rendu au lieu de le bloquer', async () => {
    const run = new Function('context', 'audioFrames', 'fps', 'shoot', `return (async () => { ${loopFromPage()} })();`);
    const context = new FakeOfflineContext();
    let shots = 0;
    const shoot = async () => {
        shots++;

        if (shots === 250) {
            throw new Error('machine trop lente (1088 millisecondes par image)');
        }
    };

    const outcome = await Promise.race([
        run(context, 1000, 20, shoot).then(() => 'fini', (error) => error.message),
        new Promise((resolve) => setTimeout(() => resolve('bloqué'), 2000)),
    ]);

    assert.match(outcome, /machine trop lente/);
    // Après le refus, plus aucune image n'est fabriquée.
    assert.equal(shots, 250);
});
