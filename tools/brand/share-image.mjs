// EN LOCAL : produit les images de partage Open Graph (1200 × 630) à partir de share-image.html, avec Chromium
// (playwright-core) : public/images/partage-talkami.jpg (accueil), une par page pays et une par niveau d'attestation
// (public/images/partage/). Textes : uniquement des faits déjà écrits sur le site.
// Usage : node tools/brand/share-image.mjs [chemin-de-chromium]
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const here = fileURLToPath(new URL('.', import.meta.url));
const out = here + '../../public/images/';
mkdirSync(out + 'partage', { recursive: true });

// Deux professeurs (Teacher Joe et Teacher Rose) sur l'image de l'accueil et des pages pays.
const duo = { duo: '1', name: 'Teacher Joe et Teacher Rose', note: 'Vos professeurs d\'anglais à l\'oral' };

const images = [
    ['partage-talkami.jpg', {}],
    // Versions avec les deux professeurs (choisies par le site quand le choix du professeur est proposé).
    ['partage-talkami-duo.jpg', { ...duo }],
    ...[['togo', 'au Togo'], ['benin', 'au Bénin'], ['burkina-faso', 'au Burkina Faso'], ['cote-d-ivoire', 'en Côte d\'Ivoire']]
        .map(([slug, accent]) => [`partage/pays-${slug}-duo.jpg`, { title: 'Apprendre l\'anglais', accent, ...duo }]),
    // Pages « Apprendre l'anglais » par pays (App\Support\CountryPage::shareImage).
    ['partage/pays-togo.jpg', { title: 'Apprendre l\'anglais', accent: 'au Togo' }],
    ['partage/pays-benin.jpg', { title: 'Apprendre l\'anglais', accent: 'au Bénin' }],
    ['partage/pays-burkina-faso.jpg', { title: 'Apprendre l\'anglais', accent: 'au Burkina Faso' }],
    ['partage/pays-cote-d-ivoire.jpg', { title: 'Apprendre l\'anglais', accent: 'en Côte d\'Ivoire' }],
    // Vérification d'une attestation (App\Support\AttestationShare::image).
    ...['A1', 'A2', 'B1', 'B2', 'C1', 'C2'].map((level) => [
        `partage/attestation-${level.toLowerCase()}.jpg`,
        { title: 'Niveau d\'anglais', accent: `${level} validé`, pill: 'Attestation de niveau', note: 'Attestation vérifiable par son code' },
    ]),
];

const browser = await chromium.launch({ executablePath: process.argv[2] || undefined, args: ['--allow-file-access-from-files'] });
const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
for (const [file, params] of images) {
    await page.goto('file://' + here + 'share-image.html?' + new URLSearchParams(params));
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: out + file, type: 'jpeg', quality: 86 });
}
await browser.close();
