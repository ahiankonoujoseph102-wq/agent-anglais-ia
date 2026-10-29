// Fabrication des vidéos de présentation de Talkami (GitHub Actions, .github/workflows/presentations.yml, lancé à la
// main ; ou en local pour une répétition).
//
// 1. Demande à Talkami les vidéos dont la voix est prête (jeton TALKAMI_VIDEO_TOKEN) : phrases, heures, plans.
// 2. Lance un site LOCAL de DÉMONSTRATION (cette copie du dépôt, base SQLite neuve, comptes fictifs « Élève » de
//    database/seeders/PresentationDemoSeeder.php, faux fournisseur des séances : fake-gemini.mjs). Jamais le site en
//    ligne, aucune donnée réelle, aucun paiement envoyé.
// 3. Filme l'écran du téléphone (record.mjs, scenes.mjs) pendant chaque phrase, puis Teacher Joe image par image
//    (avatar.mjs : bouche qui suit la voix), assemble (compose.mjs) et fabrique la version légère pour WhatsApp.
// 4. Vérifie : lèvres (sinon rien n'est déposé), durée, taille, sonie ; garde quatre images de contrôle.
// 5. Dépose les deux fichiers dans le bucket privé et prévient Talkami (avec les mesures).
//
// Variables : TALKAMI_URL, TALKAMI_VIDEO_TOKEN, PRESENTATION_KEYS (clés séparées par des virgules ; vide : toutes),
// CHROME_PATH, PRESENTATION_OUT (dossier des images de contrôle), PRESENTATION_PEEK=1 (compter seulement).
// Répétition locale, sans Talkami : node make.mjs --job=job.json --audio=voix.wav --out=dossier [--avatar=avatar.mp4]

import { spawnSync } from 'node:child_process';
import { mkdir, mkdtemp, readFile, rm, stat, writeFile, copyFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { audioDuration, launch } from '../video/common.mjs';
import { startFakeGemini } from './fake-gemini.mjs';
import { openDemo, recordTake } from './record.mjs';
import { renderAvatar } from './avatar.mjs';
import { buildScreen, compose, lightVersion, renderLayers, takes } from './compose.mjs';
import { SCENES } from './scenes.mjs';
import { call } from './talkami-api.mjs';
import { FAKE_PORT, SITE, demoEnv, resetDemo, startDemoSite } from './demo-site.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const args = Object.fromEntries(process.argv.slice(2).map((arg) => arg.replace(/^--/, '').split('=')));
const base = (process.env.TALKAMI_URL ?? '').replace(/\/+$/, '');
const token = process.env.TALKAMI_VIDEO_TOKEN ?? '';

function log(message) {
    console.log(`[${new Date().toISOString().slice(11, 19)}] ${message}`);
}

async function api(path, options = {}, extra = {}) {
    const url = path.startsWith('http') ? path : `${base}${path}`;

    return call(url, { ...options, headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(options.headers ?? {}) } }, { log, ...extra });
}

/** Image neutre choisie comme « capture » dans la scène du paiement : jamais une fausse confirmation, jamais envoyée. */
async function demoProof(browser) {
    const page = await browser.newPage({ viewport: { width: 360, height: 640 } });
    await page.setContent('<body style="margin:0;display:grid;place-items:center;height:100vh;background:#F4F6FB;font:600 28px system-ui;color:#0E2A5C;text-align:center">Capture<br>de démonstration<br><span style="font-size:18px;font-weight:400">aucun paiement</span></body>');
    const image = await page.screenshot();
    await page.close();

    return image;
}

/** Quatre images de contrôle (début, tiers, deux tiers, fin), regardées avant toute publication. */
function stills(file, total, dir, key) {
    const result = [];

    for (const [index, at] of [1, total / 3, (2 * total) / 3, Math.max(0, total - 1.5)].entries()) {
        const out = join(dir, `${key}-${index + 1}-${at.toFixed(0)}s.jpg`);
        spawnSync('ffmpeg', ['-v', 'error', '-y', '-ss', at.toFixed(2), '-i', file, '-frames:v', '1', '-q:v', '3', out]);
        result.push(out);
    }

    return result;
}

function probe(file) {
    const run = spawnSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration:stream=codec_name,width,height,r_frame_rate', '-of', 'json', file], { encoding: 'utf8' });

    return JSON.parse(run.stdout || '{}');
}

/** Fabrique les deux fichiers d'une vidéo ; renvoie la durée et les mesures. */
async function makeOne(context, job, audioFile, workdir, outDir) {
    for (const segment of job.segments) {
        if (!SCENES[segment.scene]) {
            throw new Error(`scène inconnue : ${segment.scene}`);
        }
    }

    const audioSeconds = await audioDuration(audioFile);
    const total = Math.max(job.duration, audioSeconds) + 1.2;
    // Comptes de démonstration avec le professeur de la vidéo (Teacher Rose : séance du jour et « Mes cours » avec elle).
    resetDemo({ ...context.env, PRESENTATION_TEACHER: job.teacher ?? '' });
    const demo = await openDemo(context.browser, { site: SITE, fakePort: FAKE_PORT, viewport: job.render.viewport, scale: 2, effects: context.effects, proofImage: context.proof, teacher: job.teacher && job.teacher !== 'joe' ? job.teacher : null });

    // 1. L'écran, prise par prise.
    const recorded = [];

    for (const [index, take] of takes(job, total).entries()) {
        log(`${job.key} : écran, prise ${index + 1} (${take.scenes.map((s) => s.name).join(', ')})`);
        const result = await recordTake(demo, take.scenes, join(workdir, `take-${index}`));
        recorded.push({ ...take, frames: result.frames });
    }

    await demo.context.close();

    // 2. Le professeur (Teacher Joe, ou Teacher Rose pour ses versions).
    log(`${job.key} : ${job.teacher ?? 'joe'} image par image`);
    let avatarFile = join(workdir, 'avatar.mp4');
    // Répétition locale : Teacher Joe déjà filmé pour cette voix (--avatar=…), lèvres vérifiées à ce moment-là.
    const avatar = context.avatar
        ? { lips: { ok: true, correlation: null, open_speech: null } }
        : await renderAvatar(context.browser, job, audioFile, avatarFile, log);
    avatarFile = context.avatar ?? avatarFile;

    if (!avatar.lips.ok) {
        throw new Error(`Lèvres : ${avatar.lips.reason}. Vidéo non déposée.`);
    }

    // 3. Assemblage.
    log(`${job.key} : assemblage`);
    const layers = await renderLayers(context.browser, job, workdir);
    const black = join(workdir, 'black.jpg');
    const size = { width: job.render.viewport.width * 2, height: job.render.viewport.height * 2 };
    spawnSync('ffmpeg', ['-v', 'error', '-y', '-f', 'lavfi', '-i', `color=c=black:s=${size.width}x${size.height}`, '-frames:v', '1', black]);
    const screen = await buildScreen(recorded, total, workdir, black, size);
    const full = join(workdir, 'tiktok.mp4');
    const { sound } = await compose(job, total, { screen, avatar: avatarFile, audio: audioFile, layers }, workdir, full);
    const light = join(workdir, 'whatsapp.mp4');
    await lightVersion(full, total, job.light_limit_bytes, light);

    const sizes = { full: (await stat(full)).size, light: (await stat(light)).size };
    const info = probe(full);
    const stream = info.streams?.find((s) => s.width);
    log(`${job.key} : ${Number(info.format?.duration ?? 0).toFixed(1)} secondes, ${stream?.width} × ${stream?.height}, ${stream?.r_frame_rate} images par seconde ; TikTok ${(sizes.full / 1048576).toFixed(1)} mégaoctets, WhatsApp ${(sizes.light / 1048576).toFixed(1)} mégaoctets ; son ${sound.lufs} LUFS, crête ${sound.peak} dB.`);

    if (sizes.light > job.light_limit_bytes) {
        throw new Error('version légère trop lourde');
    }

    if (outDir) {
        await mkdir(outDir, { recursive: true });
        stills(full, total, outDir, job.key);
    }

    return { total, full, light, lips: avatar.lips, sound, sizes };
}

async function withContext(work) {
    const workdir = await mkdtemp(join(tmpdir(), 'presentation-'));
    const env = demoEnv(join(workdir, 'demo.sqlite'));
    await writeFile(env.DB_DATABASE, '');
    const fake = await startFakeGemini(FAKE_PORT);
    resetDemo(env);
    const site = await startDemoSite(env);
    const browser = await launch(chromium, ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream']);

    try {
        const context = { env, browser, effects: await readFile(join(HERE, 'effects.js'), 'utf8'), proof: await demoProof(browser), workdir };

        return await work(context);
    } finally {
        await browser.close();
        site.kill();
        await fake.close();
        await rm(workdir, { recursive: true, force: true });
    }
}

async function local() {
    const job = JSON.parse(await readFile(args.job, 'utf8'));
    const outDir = resolve(args.out ?? 'presentation');

    await withContext(async (context) => {
        context.avatar = args.avatar ? resolve(args.avatar) : null;
        await mkdir(join(context.workdir, job.key), { recursive: true });
        const result = await makeOne(context, job, resolve(args.audio), join(context.workdir, job.key), outDir);
        await copyFile(result.full, join(outDir, `${job.key}-tiktok.mp4`));
        await copyFile(result.light, join(outDir, `${job.key}-whatsapp.mp4`));
        // Teacher Joe gardé pour une autre répétition avec la même voix (--avatar=…).
        await copyFile(context.avatar ?? join(context.workdir, job.key, 'avatar.mp4'), join(outDir, `${job.key}-avatar.mp4`)).catch(() => {});
        await writeFile(join(outDir, `${job.key}.json`), JSON.stringify({ duration: result.total, lips: result.lips, sound: result.sound, sizes: result.sizes }, null, 2));
        log(`Vidéos écrites dans ${outDir}.`);
    });
}

async function upload(target, file) {
    const size = (await stat(file)).size;
    const headers = { ...target.headers, 'Content-Length': String(size) };

    if (!target.direct) {
        headers.Authorization = `Bearer ${token}`;
    }

    // Même fichier, même adresse : un nouvel essai remplace simplement le dépôt interrompu.
    await call(target.url, { method: target.method, headers, body: await readFile(file) }, { log });
}

async function remote() {
    if (!base || !token) {
        throw new Error('TALKAMI_URL ou TALKAMI_VIDEO_TOKEN manquant (variables du dépôt GitHub).');
    }

    const keys = (process.env.PRESENTATION_KEYS ?? '').trim();
    const query = keys ? `?cles=${encodeURIComponent(keys)}` : '';

    if (process.env.PRESENTATION_PEEK === '1') {
        const { waiting } = await (await api(`/api/presentations/travaux${query}${query ? '&' : '?'}apercu=1`)).json();
        log(`Vidéos de présentation dont la voix est prête : ${waiting}.`);

        if (process.env.GITHUB_OUTPUT) {
            await writeFile(process.env.GITHUB_OUTPUT, `waiting=${waiting}\n`, { flag: 'a' });
        }

        return;
    }

    const { jobs } = await (await api(`/api/presentations/travaux${query}`)).json();
    log(`Vidéos de présentation à fabriquer : ${jobs.length}.`);

    if (jobs.length === 0) {
        return;
    }

    let failures = 0;

    await withContext(async (context) => {
        for (const job of jobs) {
            const workdir = join(context.workdir, job.key);
            await mkdir(workdir, { recursive: true });

            try {
                log(`${job.key} : « ${job.title} »`);
                const audio = join(workdir, 'voice.wav');
                await writeFile(audio, Buffer.from(await (await api(job.audio_url)).arrayBuffer()));
                const result = await makeOne(context, job, audio, workdir, process.env.PRESENTATION_OUT || null);
                log(`${job.key} : envoi…`);
                await upload(job.upload.video, result.full);
                await upload(job.upload.light, result.light);
                // Les deux fichiers sont déposés : une réponse passagère de Talkami ne doit plus faire perdre la vidéo.
                await api(job.done_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ duration: result.total, lips_correlation: result.lips.correlation, lips_open: result.lips.open_speech, loudness_lufs: result.sound.lufs, true_peak_db: result.sound.peak }),
                }, { acceptOnRetry: [409] });
                log(`${job.key} : prête.`);
            } catch (error) {
                failures++;
                log(`${job.key} : échec : ${error.message}`);
                await api(job.failed_url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ error: String(error.message).slice(0, 280) }) }).catch(() => {});
            } finally {
                await rm(workdir, { recursive: true, force: true });
            }
        }
    });

    if (failures > 0) {
        process.exitCode = 1;
    }
}

await (args.job ? local() : remote());
