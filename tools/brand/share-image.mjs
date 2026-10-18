// EN LOCAL : produit public/images/partage-talkami.jpg (1200 × 630, image de partage Open Graph)
// à partir de share-image.html, avec Chromium (playwright-core). Usage : node tools/brand/share-image.mjs [chemin-de-chromium]
import { chromium } from 'playwright-core';
import { fileURLToPath } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const browser = await chromium.launch({ executablePath: process.argv[2] || undefined, args: ['--allow-file-access-from-files'] });
const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
await page.goto('file://' + here + 'share-image.html');
await page.evaluate(() => document.fonts.ready);
await page.screenshot({ path: here + '../../public/images/partage-talkami.jpg', type: 'jpeg', quality: 86 });
await browser.close();
