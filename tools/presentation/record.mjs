// Enregistrement de l'écran du site de DÉMONSTRATION (téléphone de 390 × 844 points) : une « prise » = des phrases
// à l'écran qui se suivent ; Chromium envoie ses images (Page.startScreencast) avec leur heure, gardées en JPEG.
// Les scènes (scenes.mjs) agissent sur la page avec l'aide « s » : doigt, cercle, flèche, zoom, défilement, saisie.

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { scene as sceneOf } from './scenes.mjs';

const DEMO_PASSWORD = 'demo-presentation';
const ACCOUNTS = {
    fresh: '+22899000001',
    assessed: '+22899000002',
    pending: '+22899000003',
    welcome: '+22899000004',
    active: '+22899000005',
    abroad: '+33600000006',
    market: '+22899000007',
};

const sleep = (ms) => new Promise((ok) => setTimeout(ok, Math.max(0, ms)));

/** Navigateur prêt : un onglet de téléphone, les effets, les cookies de chaque compte de démonstration. */
export async function openDemo(browser, { site, fakePort, viewport, scale, effects, proofImage, coursePhoto = null, accounts = {} }) {
    const options = { viewport, deviceScaleFactor: scale, isMobile: true, hasTouch: true, locale: 'fr-FR', timezoneId: 'Africa/Lome', permissions: ['microphone'], reducedMotion: 'no-preference' };
    const cookies = { visitor: [] };

    for (const [key, phone] of Object.entries({ ...ACCOUNTS, ...accounts })) {
        const context = await browser.newContext(options);
        const page = await context.newPage();
        await page.goto(`${site}/connexion`);
        await page.fill('input[name="phone"]', phone);
        await page.fill('input[name="password"]', DEMO_PASSWORD);
        await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/connexion')), page.click('main form button[type="submit"]')]);
        cookies[key] = await context.cookies();
        await context.close();
    }

    const context = await browser.newContext(options);
    await context.addInitScript({ content: effects });
    // Proposition d'installation jamais montrée : déjà « fermée » aujourd'hui ; Teacher Joe jamais remplacé
    // par le visage simple à cause de la lenteur de la machine de tournage (window.__filming, teacher-3d.js).
    await context.addInitScript(() => {
        window.__filming = true;
        try {
            localStorage.setItem('install-reminder', JSON.stringify({ count: 9, at: Date.now() }));
            localStorage.setItem('teacher-view', '3d');
        } catch {}
    });
    const page = await context.newPage();
    page.on('pageerror', (error) => console.log(`[page] ${error.message}`));
    const cdp = await context.newCDPSession(page);

    return { context, page, cdp, cookies, site, fakePort, proofImage, coursePhoto, viewport, scale, account: null };
}

/** L'aide « s » des scènes. */
function helper(demo, deadline) {
    const { page } = demo;
    const fx = (name, ...args) => page.evaluate(([n, a]) => window.__fx?.[n]?.(...a), [name, args]);

    const center = async (locator) => {
        const box = await locator.boundingBox({ timeout: 3000 }).catch(() => null);

        return box ? { x: box.x + box.width / 2, y: box.y + box.height / 2 } : null;
    };

    const s = {
        page,
        left: () => deadline - Date.now(),
        wait: (ms) => sleep(Math.min(ms, Math.max(0, deadline - Date.now()))),
        clear: () => fx('clearMarks'),
        circle: (selector, pad) => fx('circle', selector, pad ?? 8),
        arrow: (selector) => fx('arrow', selector),
        zoom: (selector, value) => fx('zoom', selector, value),
        unzoom: async () => { await fx('unzoom'); await sleep(650); },

        async circleAny(selectors) {
            for (const selector of selectors) {
                if (await page.locator(selector).first().isVisible().catch(() => false)) {
                    return s.circle(selector);
                }
            }

            return false;
        },

        async scroll(selector, ms = 900, offset = 90) {
            await page.evaluate(([sel, m, o]) => window.__fx.scrollTo(sel, m, o), [selector, ms, offset]);
        },

        /** Défile jusqu'au premier texte trouvé et l'entoure. */
        async scrollText(texts, ms = 900) {
            for (const text of texts) {
                const locator = page.getByText(text, { exact: false }).first();

                if (await locator.count() && await locator.isVisible().catch(() => false)) {
                    const handle = await locator.elementHandle();
                    await page.evaluate(([el, m]) => window.__fx.scrollTo(el, m, 200), [handle, ms]);
                    await page.evaluate((el) => window.__fx.circle(el, 6), handle);

                    return true;
                }
            }

            return false;
        },

        async circleText(container, text) {
            const locator = page.locator(container).getByText(new RegExp(text, 'i')).filter({ visible: true }).first();

            if (await locator.count()) {
                const handle = await locator.elementHandle();
                await page.evaluate((el) => window.__fx.circle(el, 6), handle);
            }
        },

        async arrowText(text) {
            const handle = await page.getByText(text, { exact: false }).first().elementHandle();

            if (handle) {
                await page.evaluate((el) => window.__fx.arrow(el), handle);
            }
        },

        async tap(selector, { click = true, navigate = false } = {}) {
            const locator = typeof selector === 'string' ? page.locator(selector).first() : selector;
            const point = await center(locator);

            if (point) {
                await fx('tap', point.x, point.y);
            }

            if (!click) {
                return;
            }

            if (!point) {
                console.log(`[toucher] élément introuvable : ${typeof selector === 'string' ? selector : 'texte'}`);

                return;
            }

            if (navigate) {
                await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }).catch(() => {}), locator.click({ timeout: 5000 })]);
                await settle(page);
            } else {
                await locator.click({ timeout: 5000 });
            }
        },

        tapText: (text, options) => s.tap(page.getByText(text, { exact: false }).first(), options),

        async type(selector, text, delay = 80) {
            const locator = page.locator(selector).first();
            await locator.fill('');
            // La saisie tient dans le temps de la phrase (jamais plus lente que « delay »).
            const fits = Math.floor((deadline - Date.now() - 600) / Math.max(1, text.length));
            await locator.pressSequentially(text, { delay: Math.max(15, Math.min(delay, fits)) });
        },

        select: (selector, value) => page.selectOption(selector, value),

        newPhone: () => `9910${String(Date.now() % 10000).padStart(4, '0')}`.slice(0, 8),

        /** Le compte tout juste créé devient « registered » (scènes suivantes). */
        async registered() {
            demo.cookies.registered = await demo.context.cookies();
            demo.account = 'registered';
        },

        /** Teacher Joe en 3D affiché (jamais filmé pendant le chargement). */
        async teacherReady() {
            const timeout = Math.max(1000, Math.min(40000, deadline - Date.now() - 1500));
            await page.waitForFunction(() => ['3d', 'svg'].includes(document.querySelector('[data-teacher]')?.dataset.teacher), null, { timeout }).catch(() => {});
        },

        async openBoard() {
            await page.evaluate(() => document.getElementById('board')?.setAttribute('open', ''));
            await sleep(300);
        },

        /** Carte d'une formule : visible, puis entourée (index dans l'ordre affiché). */
        async planCard(index, mark = true) {
            const card = page.locator('article.plan').nth(index);
            const handle = await card.elementHandle();

            if (!handle) {
                return;
            }

            await page.evaluate((el) => window.__fx.scrollTo(el, 800, 110), handle);

            if (mark) {
                await page.evaluate((el) => window.__fx.circle(el, 6), handle);
            }
        },

        /** Image neutre « capture de démonstration » (jamais une fausse confirmation), jamais envoyée. */
        async chooseDemoProof() {
            await page.setInputFiles('#proof', { name: 'capture-de-demonstration.png', mimeType: 'image/png', buffer: demo.proofImage });
        },

        /** Photo de cahier DESSINÉE (aucune vraie photo), jamais envoyée : seulement son aperçu. */
        async chooseCoursePhoto() {
            await page.setInputFiles('#course-gallery', { name: 'lecon-de-demonstration.png', mimeType: 'image/png', buffer: demo.coursePhoto ?? demo.proofImage });
        },

        async questionnaireScreen(screen) {
            const choose = async (selector) => {
                const label = page.locator(selector).first().locator('xpath=ancestor::label[1]');
                await s.tap((await label.count()) ? label : page.locator(selector).first());
            };

            if (screen === 1) {
                await choose('input[name="goal"][value="work"]');
            } else if (screen === 2) {
                await choose('input[name="profile"]');
                await page.fill('input[name="occupation"]', 'Commerçante');
            } else if (screen === 3) {
                // Jours déjà proposés : on garde, on continue.
                await s.circle('.days');
                await s.wait(500);
            } else {
                await choose('input[name="difficulties[]"]');
                await s.scroll('main form button[type="submit"]', 600, 420);
                await s.circle('main form button[type="submit"]');

                return;
            }

            await s.tap('main form button[type="submit"]', { navigate: true });
        },

        /** Ce que dit le faux professeur, maintenant. */
        say: (step) => fetch(`http://127.0.0.1:${demo.fakePort}/__step`, { method: 'POST', body: JSON.stringify(step) }).catch(() => {}),
    };

    return s;
}

async function settle(page) {
    await page.waitForLoadState('load').catch(() => {});
    await page.evaluate(() => document.fonts?.ready).catch(() => {});
    await sleep(250);
}

/** Compte et page de la scène (sans rien filmer si c'est la première de la prise). */
async function arrive(demo, sceneDef) {
    const { page } = demo;
    const account = sceneDef.account;
    let moved = false;

    if (account && account !== demo.account) {
        await demo.context.clearCookies();

        if (demo.cookies[account]?.length) {
            await demo.context.addCookies(demo.cookies[account]);
        }

        demo.account = account;
        moved = true;
    }

    if (sceneDef.path) {
        const current = new URL(page.url() === 'about:blank' ? `${demo.site}/__vide` : page.url());
        const wanted = new URL(demo.site + sceneDef.path);

        if (moved || current.pathname + current.search !== wanted.pathname + wanted.search) {
            // Aucun « retour depuis l'application Mobile Money » simulé par un changement de page.
            await page.evaluate(() => { try { sessionStorage.clear(); } catch {} }).catch(() => {});
            await page.goto(wanted.href, { waitUntil: 'load' });
            await settle(page);
        }
    }

    if (sceneDef.before) {
        await sceneDef.before(helper(demo, Date.now() + 60000));
    }
}

/**
 * Filme une prise : les scènes dans l'ordre, chacune pendant sa durée (millisecondes). Renvoie les images (heure
 * depuis le début de la prise, en secondes, et fichier JPEG).
 */
export async function recordTake(demo, scenes, dir) {
    await mkdir(dir, { recursive: true });
    const { page, cdp } = demo;
    const first = scenes[0].def ?? sceneOf(scenes[0].name);
    await arrive(demo, first);

    if (first.live) {
        await helper(demo, Date.now() + 40000).teacherReady();
        await sleep(1500);
    } else {
        // Les blocs de la page finissent d'apparaître avant la première image.
        await sleep(1200);
    }

    const frames = [];
    let t0 = null;
    let lastBefore = null;
    let count = 0;

    const writes = [];
    const onFrame = ({ data, metadata, sessionId }) => {
        cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
        const file = join(dir, `f${String(count++).padStart(6, '0')}.jpg`);
        writes.push(writeFile(file, Buffer.from(data, 'base64')));
        const at = metadata.timestamp ?? Date.now() / 1000;

        if (t0 === null || at < t0) {
            lastBefore = { file };
        } else {
            frames.push({ t: at - t0, file });
        }
    };

    cdp.on('Page.screencastFrame', onFrame);
    await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 90, maxWidth: demo.viewport.width * demo.scale, maxHeight: demo.viewport.height * demo.scale, everyNthFrame: 1 });
    await sleep(400);
    // Une image tout de suite, même si rien ne bouge.
    await page.evaluate(() => window.__fx?.ready());
    t0 = Date.now() / 1000;
    let planned = Date.now();

    for (const [index, entry] of scenes.entries()) {
        const sceneDef = entry.def ?? sceneOf(entry.name);
        const start = Math.max(Date.now(), planned);
        const deadline = start + entry.ms;
        const s = helper(demo, deadline);

        if (index > 0) {
            await s.clear();
            // Un zoom laissé par la scène précédente est retiré (sans attendre la fin de l'animation).
            await page.evaluate(() => window.__fx?.unzoom()).catch(() => {});
            await arrive(demo, sceneDef);
        }

        for (const step of sceneDef.plan ?? []) {
            setTimeout(() => s.say(step), Math.max(0, start + step.at - Date.now()));
        }

        try {
            // La scène suivante démarre à l'heure de SA phrase : le retard ne s'accumule jamais d'une scène à l'autre.
            const acting = Promise.resolve(sceneDef.act?.(s, Math.max(500, deadline - Date.now()))).catch((error) => console.log(`[scène ${entry.name}] ${error.message.split('\n')[0]}`));
            await Promise.race([acting, sleep(deadline - Date.now() + 150)]);
        } catch (error) {
            console.log(`[scène ${entry.name}] ${error.message.split('\n')[0]}`);
        }

        if (Date.now() > deadline + 300) {
            console.log(`[scène ${entry.name}] ${Date.now() - deadline} millisecondes de retard`);
        }

        await sleep(deadline - Date.now());
        planned = deadline;
    }

    await cdp.send('Page.stopScreencast').catch(() => {});
    cdp.off('Page.screencastFrame', onFrame);
    await sleep(200);
    await Promise.all(writes);
    frames.sort((a, b) => a.t - b.t);

    if (lastBefore && (frames.length === 0 || frames[0].t > 0)) {
        frames.unshift({ t: 0, file: lastBefore.file });
    }

    const duration = Date.now() / 1000 - t0;
    console.log(`[prise] ${frames.length} images, de ${frames[0]?.t.toFixed(2)} à ${frames.at(-1)?.t.toFixed(2)} secondes, sur ${duration.toFixed(2)} secondes`);

    return { frames, duration };
}

export { readFile };
