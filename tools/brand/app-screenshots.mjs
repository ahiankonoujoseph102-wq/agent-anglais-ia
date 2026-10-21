// EN LOCAL : produit les captures d'écran du manifeste (fenêtre d'installation de Chrome sur Android) :
// public/images/app/ecran-mon-espace.png et ecran-seance.png (390 × 844 points, écran 2x), converties ensuite en WebP
// (python3 -c "from PIL import Image; …", voir la fin). OBLIGATOIRE : un compte de DÉMONSTRATION sur une base locale
// (nom « Élève », numéro fictif, abonnement et programme de démonstration), jamais un vrai compte ; aucune marque
// d'intelligence artificielle à l'écran (vérifié par tests/Feature/WebAppTest.php sur les pages, pas sur l'image).
// Usage : SITE=http://127.0.0.1:8123 DEMO_PHONE=… DEMO_PASSWORD=… node tools/brand/app-screenshots.mjs [chemin-de-chromium]
import { chromium } from 'playwright-core';
import { fileURLToPath } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const out = here + '../../public/images/app/';
const site = process.env.SITE || 'http://127.0.0.1:8000';
const browser = await chromium.launch({ executablePath: process.argv[2] || undefined });
const page = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, reducedMotion: 'reduce' });

await page.goto(site + '/connexion');
await page.fill('input[name="phone"]', process.env.DEMO_PHONE);
await page.fill('input[name="password"]', process.env.DEMO_PASSWORD);
await Promise.all([page.waitForNavigation(), page.click('form button[type="submit"]')]);

// Ni bandeau d'installation ni message passager sur les captures.
const clean = () => page.addStyleTag({ content: '#install, .toast { display: none !important; }' });

for (const [path, file] of [['/espace', 'ecran-mon-espace'], ['/seance', 'ecran-seance']]) {
    await page.goto(site + path, { waitUntil: 'networkidle' });
    await clean();
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(4000);
    await page.screenshot({ path: out + file + '.png' });
}

await browser.close();
// Puis : python3 -c "from PIL import Image; [Image.open(f'public/images/app/{n}.png').save(f'public/images/app/{n}.webp', quality=82, method=6) for n in ('ecran-mon-espace', 'ecran-seance')]" et suppression des PNG.
