// EN LOCAL : vraies captures d'écran du site de DÉMONSTRATION (comptes fictifs « Élève », faux fournisseur des
// séances, base SQLite neuve) et rendus de Teacher Joe en haute définition (notre modèle 3D), pour les images de la
// fiche Google de Talkami (tools/brand/google-profile.py les met en page). Jamais le site en ligne, aucune clé.
// Usage : node tools/presentation/google-profile.mjs dossier-de-sortie
import { chromium } from 'playwright-core';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { launch, ROOT } from '../video/common.mjs';
import { demoEnv, FAKE_PORT, resetDemo, SITE, startDemoSite } from './demo-site.mjs';
import { startFakeGemini } from './fake-gemini.mjs';

const out = process.argv[2];
mkdirSync(out, { recursive: true });
const sleep = (ms) => new Promise((ok) => setTimeout(ok, ms));
const say = (step) => fetch(`http://127.0.0.1:${FAKE_PORT}/__step`, { method: 'POST', body: JSON.stringify(step) }).catch(() => {});

const env = demoEnv(join(out, 'demo.sqlite'));
writeFileSync(env.DB_DATABASE, '');
resetDemo(env);
const fake = await startFakeGemini(FAKE_PORT);
const server = await startDemoSite(env);
const browser = await launch(chromium, ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream']);

try {
    // 1. Teacher Joe en haute définition : salle de classe (portrait carré) et fond transparent (couverture).
    const html = readFileSync(join(ROOT, 'tools/brand/teacher-expressions.html'), 'utf8')
        .replaceAll('576px', '1080px')
        .replace("scene: { mode: 'classroom' }", "scene: { mode: window.__mode || 'classroom' }")
        .replace("out.toDataURL('image/webp', quality)", "out.toDataURL('image/png')")
        .replace('out.width = size;', 'out.width = window.__w || size;')
        .replace('out.height = size;', 'out.height = window.__h || size;')
        .replace('context.drawImage(source, 0, 0, size, size);', 'context.drawImage(source, 0, 0, out.width, out.height);');
    const render = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
    await render.route(`${SITE}/__teacher`, (route) => route.fulfill({ contentType: 'text/html', body: html }));

    // Au repos (« neutral » : sourire, mains sur le bureau) ; la couverture en 16:9, Teacher Joe à droite.
    const shots = {
        'teacher-classe': ['classroom', 'neutral', [1080, 1080], { target: [0, 0.5, 0], half_height: 0.43, half_width: 0.43, eye_height: 0.68 }],
        'teacher-couverture': ['classroom', 'neutral', [1920, 1080], { target: [-0.3, 0.52, 0], half_height: 0.42, half_width: 0.746, eye_height: 0.66 }],
    };

    for (const [file, [mode, name, [w, h], camera]] of Object.entries(shots)) {
        await render.goto(`${SITE}/__teacher`);
        await render.evaluate(([m, w, h]) => {
            window.__mode = m;
            window.__w = w;
            window.__h = h;
            Object.assign(document.getElementById('stage').style, { width: `${w}px`, height: `${h}px` });
        }, [mode, w, h]);
        await render.waitForFunction(() => window.ready === true);
        const images = await render.evaluate((options) => window.renderExpressions(options), { names: [name], size: 1080, settle: 1.2, camera });
        writeFileSync(join(out, `${file}.png`), Buffer.from(images[name].split(',')[1], 'base64'));
        console.log(file);
    }

    await render.close();

    // 2. Captures du site : téléphone de 390 × 844 points, écran 2x.
    const options = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-FR', timezoneId: 'Africa/Lome', permissions: ['microphone'] };
    const clean = '#mic-choice, #install, .toast, .install-hint { display: none !important; } .reveal, .reveal-side { opacity: 1 !important; transform: none !important; }';

    const open = async (phone) => {
        const context = await browser.newContext(options);
        await context.addInitScript(() => {
            window.__filming = true;
            try {
                localStorage.setItem('install-reminder', JSON.stringify({ count: 9, at: Date.now() }));
                localStorage.setItem('teacher-view', '3d');
            } catch {}
        });
        const page = await context.newPage();

        if (phone) {
            await page.goto(`${SITE}/connexion`);
            await page.fill('input[name="phone"]', phone);
            await page.fill('input[name="password"]', 'demo-presentation');
            await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/connexion')), page.click('main form button[type="submit"]')]);
        }

        return page;
    };

    const shoot = async (page, file) => {
        await page.addStyleTag({ content: clean });
        await page.evaluate(() => document.fonts.ready);
        await sleep(600);
        await page.screenshot({ path: join(out, `${file}.png`) });
        console.log(file);
    };

    const teacherReady = (page) => page.waitForFunction(() => document.querySelector('[data-teacher]')?.dataset.teacher === '3d', null, { timeout: 60000 });

    let page = await open(null);
    await page.goto(SITE + '/', { waitUntil: 'networkidle' });
    await shoot(page, 'ecran-accueil');
    await page.context().close();

    page = await open('+22899000005');
    await page.goto(SITE + '/espace', { waitUntil: 'networkidle' });
    await shoot(page, 'ecran-mon-espace');
    await page.context().close();

    page = await open('+22899000002');
    await page.goto(SITE + '/abonnement', { waitUntil: 'networkidle' });
    await shoot(page, 'ecran-formules');
    await page.goto(SITE + '/evaluation', { waitUntil: 'networkidle' });
    await shoot(page, 'ecran-resultat');
    await page.context().close();

    // Évaluation gratuite en cours (faux fournisseur : écran de démonstration).
    page = await open('+22899000001');
    await page.goto(SITE + '/evaluation', { waitUntil: 'networkidle' });
    await teacherReady(page);
    await page.click('#start');
    await page.waitForFunction(() => document.querySelector('.live[data-phase]')?.dataset.phase !== 'intro', null, { timeout: 15000 }).catch(() => {});
    await sleep(1500);
    await say({ teacher: 'Hello! Welcome. What is your name?', seconds: 2 });
    await sleep(3200);
    await say({ learner: 'My name is Afi. I live in Lomé.' });
    await sleep(800);
    await say({ teacher: 'Nice to meet you, Afi! What do you do?', seconds: 2.5 });
    await sleep(5000);
    await page.evaluate(() => window.scrollTo(0, 0));
    await shoot(page, 'ecran-evaluation');
    await page.context().close();

    // Séance du jour : tableau de la leçon « Au Grand Marché de Lomé », phrase dite allumée.
    page = await open('+22899000007');
    await page.goto(SITE + '/seance', { waitUntil: 'networkidle' });
    await teacherReady(page);
    await page.click('#start');
    await page.waitForFunction(() => document.querySelector('.live[data-phase]')?.dataset.phase !== 'intro', null, { timeout: 15000 }).catch(() => {});
    await sleep(1500);
    await say({ teacher: 'Hello! Today, we are at the market.', seconds: 2 });
    await sleep(3500);
    await page.evaluate(() => document.getElementById('board')?.setAttribute('open', ''));
    await say({ teacher: "Repeat after me: It's two thousand francs.", seconds: 2.5 });
    await sleep(5000);
    await page.evaluate(() => window.scrollTo(0, 0));
    await shoot(page, 'ecran-seance');
    // Le tableau en haut de l'écran : page entière, découpée sous l'en-tête à la hauteur du tableau.
    const top = await page.evaluate(() => document.getElementById('board').getBoundingClientRect().top + window.scrollY);
    await page.addStyleTag({ content: '.site-header, header { position: static !important; }' });
    await page.screenshot({ path: join(out, 'ecran-tableau.png'), fullPage: true, clip: { x: 0, y: Math.max(0, top - 16), width: 390, height: 844 } });
    console.log('ecran-tableau');
    await page.context().close();
} finally {
    await browser.close();
    server.kill();
    await fake.close();
}
