// EN LOCAL : fabrique le prospectus Talkami (noir sur blanc) avec Chromium (playwright) :
//   prospectus-a6.pdf (un prospectus, 105 × 148 mm, pour l'imprimerie), prospectus-4-par-a4.pdf (quatre par feuille A4,
//   traits de coupe discrets, pour la photocopie) et prospectus.png (pour WhatsApp, 300 points par pouce).
// Valeurs lues dans la configuration du site (values.php) ; code QR fait par qr.py (segno).
// Usage : node tools/prospectus/build.mjs [--campagne=prenom] [--sortie=dossier]
//   --campagne : un prospectus par distributeur (utm_campaign), compté à part dans l'onglet « Provenance ».
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// Playwright installé globalement (ou dans le dossier) : require le trouve dans les deux cas.
const { chromium } = createRequire(import.meta.url)('playwright');
const here = fileURLToPath(new URL('.', import.meta.url));
const args = Object.fromEntries(process.argv.slice(2).map((a) => a.replace(/^--/, '').split('=')));
const campaign = (args.campagne || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9._-]+/g, '-').replace(/^-|-$/g, '');
const out = args.sortie || here + '../../docs/prospectus/' + (campaign ? 'distributeurs/' + campaign + '/' : '');
mkdirSync(out, { recursive: true });

const SITE = 'talkami.net';
const url = `https://${SITE}/?utm_source=prospectus` + (campaign ? `&utm_campaign=${campaign}` : '');
const values = JSON.parse(execFileSync('php', [here + 'values.php'], { encoding: 'utf8' }));
const qr = execFileSync('python3', [here + 'qr.py', url], { encoding: 'utf8' }).replace('<svg ', '<svg class="qr" role="img" aria-label="Code QR vers ' + SITE + '" ');

// Numéro local du Togo écrit comme sur le site (« 72 24 02 31 »), sans l'indicatif.
const phone = values.whatsapp.replace(/^\+228\s*/, '');
const period = values.period_days === 7 ? 'la semaine' : values.period_days === 30 ? 'le mois' : `pour ${values.period_days} jours`;
const price = new Intl.NumberFormat('fr-FR').format(values.price).replace(/\s/g, ' ');

const fill = (layout) => readFileSync(here + 'prospectus.html', 'utf8')
    .replaceAll('{{platform}}', values.platform)
    .replaceAll('{{teacher_nb}}', values.teacher.replace(/ /g, '\u00a0'))
    .replaceAll('{{teacher}}', values.teacher)
    .replaceAll('{{minutes}}', String(values.assessment_minutes))
    .replaceAll('{{price}}', price)
    .replaceAll('{{period}}', period)
    .replaceAll('{{phone}}', phone.replace(/ /g, ' '))
    .replaceAll('{{site}}', SITE)
    .replaceAll('{{qr}}', qr)
    .replaceAll('{{ref}}', campaign ? `<p class="ref">Réf. ${campaign}</p>` : '')
    .replaceAll('{{page_width}}', String(layout.width))
    .replaceAll('{{page_height}}', String(layout.height))
    .replaceAll('{{layout}}', JSON.stringify(layout));

const single = { width: 105, height: 148, cellHeight: 148, cells: [[0, 0]], sheet: false };
const sheet = { width: 210, height: 297, cellHeight: 148.5, cells: [[0, 0], [105, 0], [0, 148.5], [105, 148.5]], sheet: true };

const browser = await chromium.launch({ args: ['--allow-file-access-from-files'] });
const page = await browser.newPage();
const render = async (layout, name) => {
    const file = here + `.page-${name}.html`; // à côté du modèle : logo et polices en chemins relatifs
    writeFileSync(file, fill(layout));
    await page.goto('file://' + file);
    await page.evaluate(() => document.fonts.ready);
    return file;
};

let file = await render(single, 'a6');
await page.pdf({ path: out + 'prospectus-a6.pdf', width: '105mm', height: '148mm', printBackground: true, preferCSSPageSize: true });
// Image pour WhatsApp : 105 × 148 mm à 300 points par pouce (1240 × 1748 pixels).
const scale = 1240 / 396.85;
const hd = await browser.newPage({ deviceScaleFactor: scale, viewport: { width: 397, height: 560 } });
await hd.goto('file://' + file);
await hd.evaluate(() => document.fonts.ready);
await hd.screenshot({ path: out + 'prospectus.png', clip: { x: 0, y: 0, width: 396.85, height: 559.37 }, type: 'png' });
await hd.close();
rmSync(file);

file = await render(sheet, 'a4');
await page.pdf({ path: out + 'prospectus-4-par-a4.pdf', width: '210mm', height: '297mm', printBackground: true, preferCSSPageSize: true });
rmSync(file);

await browser.close();
console.log(`Prospectus prêts dans ${out} (code QR : ${url})`);
