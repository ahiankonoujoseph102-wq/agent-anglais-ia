// EN LOCAL : fabrique la pastille « Teacher Joe pensif » de l'accueil (public/images/teacher-joe-pensif.webp) à
// partir de NOTRE modèle 3D (CC0), dans la même salle de classe que l'image fixe de l'accueil, cadrée sur le visage.
// Le site doit tourner (php artisan serve) : la page charge le vrai module des séances (public/js/teacher-3d.js).
// Usage : node tools/brand/teacher-expressions.mjs [adresse-du-site] [chemin-de-chromium] [--rose]
// --rose : fabrique aussi le portrait fixe de Teacher Rose (public/images/teacher-rose.webp).
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

// Expression du module des séances (EXPRESSIONS de teacher-3d.js) ; nom du fichier ⇒ expression et cadrage
// (petit rond : visage seul).
const face = { target: [0, 0.70, 0], half_height: 0.2, half_width: 0.2, eye_height: 0.72 };
const files = { pensif: ['thinking', face] };

for (const [file, [name, camera]] of Object.entries(files)) {
    const images = await page.evaluate((options) => window.renderExpressions(options), { names: [name], size: 192, quality: 0.82, settle: 1.2, camera });
    const data = Buffer.from(images[name].split(',')[1], 'base64');
    writeFileSync(here + `../../public/images/teacher-joe-${file}.webp`, data);
    console.log(`teacher-joe-${file}.webp`, data.length, 'octets');
}

// Portrait fixe de Teacher Rose (pages publiques, comme public/images/teacher-joe.webp) : même salle de classe,
// même cadrage que les séances (config/avatar.php → rose.camera), au repos.
if (process.argv.includes('--rose')) {
    const camera = { target: [0, 0.47, 0], half_height: 0.42, half_width: 0.42, eye_height: 0.68 };
    const images = await page.evaluate((options) => window.renderExpressions(options), { names: ['neutral'], size: 560, quality: 0.8, settle: 1.2, camera, model: 'teacher-rose', smile: 0.45 });
    const data = Buffer.from(images.neutral.split(',')[1], 'base64');
    writeFileSync(here + '../../public/images/teacher-rose.webp', data);
    console.log('teacher-rose.webp', data.length, 'octets');
}

await browser.close();
