// EN LOCAL : rend l'affiche du « Club des membres fondateurs de Talkami » (club-fondateurs.html) en PNG, avec
// Chromium (playwright) : carré 1080 × 1080 et vertical 1080 × 1920, dans docs/affiches/club-fondateurs/.
// Usage : node tools/affiches/club-fondateurs.mjs
import { mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

// Playwright installé globalement (NODE_PATH) ou dans le projet.
const { chromium } = createRequire(import.meta.url)('playwright');

const here = fileURLToPath(new URL('.', import.meta.url));
const out = here + '../../docs/affiches/club-fondateurs/';
mkdirSync(out, { recursive: true });

const formats = [
    ['carre', 1080, 1080, 'club-fondateurs-talkami-carre-1080x1080.png'],
    ['vertical', 1080, 1920, 'club-fondateurs-talkami-vertical-1080x1920.png'],
];

const browser = await chromium.launch({ args: ['--allow-file-access-from-files'] });
for (const [format, width, height, file] of formats) {
    const page = await browser.newPage({ viewport: { width, height } });
    await page.goto('file://' + here + 'club-fondateurs.html?format=' + format);
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(300);
    await page.screenshot({ path: out + file, clip: { x: 0, y: 0, width, height } });
    await page.close();
}
await browser.close();
