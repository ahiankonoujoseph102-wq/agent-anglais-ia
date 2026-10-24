// EN LOCAL : fabrique les petites images d'expressions de Teacher Joe (public/images/teacher-joe-{nom}.webp) à
// partir de NOTRE modèle 3D (CC0), dans la même salle de classe que l'image fixe de l'accueil, cadrées sur le visage.
// Le site doit tourner (php artisan serve) : la page charge le vrai module des séances (public/js/teacher-3d.js).
// Usage : node tools/brand/teacher-expressions.mjs [adresse-du-site] [chemin-de-chromium]
import { chromium } from 'playwright-core';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const base = (process.argv[2] || 'http://localhost:8000').replace(/\/$/, '');
const browser = await chromium.launch({ executablePath: process.argv[3] || undefined, args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
const page = await browser.newPage({ viewport: { width: 576, height: 576 } });
await page.route(`${base}/__teacher-expressions`, (route) => route.fulfill({ contentType: 'text/html', body: readFileSync(here + 'teacher-expressions.html', 'utf8') }));
await page.goto(`${base}/__teacher-expressions`);
await page.waitForFunction(() => window.ready === true);

// Expressions du module des séances (EXPRESSIONS de teacher-3d.js) ; nom du fichier ⇒ expression et cadrage.
// « pensif » s'affiche en petit rond : visage seul ; les autres en carré arrondi : tête, épaules et gestes des mains.
const face = { target: [0, 0.70, 0], half_height: 0.2, half_width: 0.2, eye_height: 0.72 };
const bust = { target: [0, 0.6, 0], half_height: 0.3, half_width: 0.3, eye_height: 0.72 };
const files = { pensif: ['thinking', face], accueil: ['welcome', bust], attentif: ['attentive', bust], encourageant: ['encouraging', bust], souriant: ['happy', bust] };

for (const [file, [name, camera]] of Object.entries(files)) {
    const images = await page.evaluate((options) => window.renderExpressions(options), { names: [name], size: 192, quality: 0.82, settle: 1.2, camera });
    const data = Buffer.from(images[name].split(',')[1], 'base64');
    writeFileSync(here + `../../public/images/teacher-joe-${file}.webp`, data);
    console.log(`teacher-joe-${file}.webp`, data.length, 'octets');
}

await browser.close();
