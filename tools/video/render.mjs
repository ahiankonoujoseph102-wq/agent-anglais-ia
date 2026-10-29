// Fabrication des vidéos de leçon de Teacher Joe (GitHub Actions, .github/workflows/videos.yml).
//
// 1. Demande à Talkami les vidéos dont la voix est prête (jeton TALKAMI_VIDEO_TOKEN).
// 2. Pour chacune : Chromium sans écran (WebGL par le processeur) filme Teacher Joe image par image (render.html),
//    ffmpeg ajoute les sous-titres (subtitles.mjs, police Sora) et la voix (compresseur léger, gain calculé d'après la
//    sonie, limiteur : environ −14 LUFS), et écrit un MP4 vertical 1080 × 1920 (H.264 + AAC 128 kilobits par
//    seconde, compatible TikTok et WhatsApp).
// 3. Vérifie que la bouche a suivi la voix (lip-check.mjs) : sinon, la vidéo n'est PAS déposée (échec).
// 4. Dépose la vidéo dans le bucket privé (adresse signée donnée par Talkami) et prévient Talkami (avec les mesures).
//
// Variables : TALKAMI_URL (adresse du site), TALKAMI_VIDEO_TOKEN (secret), VIDEO_COUNT (1 à 4), VIDEO_BUDGET_MINUTES (temps accordé), CHROME_PATH
// (navigateur), VIDEO_PEEK=1 (dire seulement combien de vidéos attendent, sans rien prendre).
// Essai en local, sans Talkami : node render.mjs --job=job.json --audio=voix.wav --out=video.mp4

import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp, readFile, rename, writeFile, rm, stat } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { chromium } from 'playwright-core';
import { join, resolve } from 'node:path';
import { buildAss } from './subtitles.mjs';
import { lipCheck } from './lip-check.mjs';
import { AUDIO_PRE, FONTS, audioDuration, audioFilter, gainFor, launch, measureLoudness, serve, soundFix, willFinish } from './common.mjs';

export { gainFor, measureLoudness };

const args = Object.fromEntries(process.argv.slice(2).map((arg) => arg.replace(/^--/, '').split('=')));
const base = (process.env.TALKAMI_URL ?? '').replace(/\/+$/, '');
const token = process.env.TALKAMI_VIDEO_TOKEN ?? '';

function log(message) {
    console.log(`[${new Date().toISOString().slice(11, 19)}] ${message}`);
}

async function api(path, options = {}) {
    const response = await fetch(path.startsWith('http') ? path : `${base}${path}`, {
        ...options,
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(options.headers ?? {}) },
    });

    if (!response.ok) {
        throw new Error(`Talkami a répondu ${response.status} pour ${new URL(response.url).pathname}`);
    }

    return response;
}

function ffmpeg(job, workdir, audio, out, total, gainDb) {
    const r = job.render;
    const scaledHeight = Math.round(r.avatar_height * (r.width / r.avatar_width));
    const filter = [
        `color=c=${r.background.replace('#', '0x')}:s=${r.width}x${r.height}:r=${r.fps}[bg]`,
        `[0:v]scale=${r.width}:${scaledHeight}:flags=lanczos[av]`,
        `[bg][av]overlay=0:${r.avatar_top ?? 0}:shortest=1[base]`,
        `[base]ass=${join(workdir, 'subtitles.ass')}:fontsdir=${FONTS}[v]`,
    ].join(';');

    const child = spawn('ffmpeg', [
        '-hide_banner', '-loglevel', 'error', '-y',
        '-f', 'image2pipe', '-framerate', String(r.fps), '-c:v', 'mjpeg', '-i', '-',
        '-i', audio,
        '-filter_complex', filter,
        '-map', '[v]', '-map', '1:a',
        '-af', audioFilter(r.audio, gainDb), '-t', total.toFixed(2),
        '-c:v', 'libx264', '-preset', 'medium', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-r', String(r.fps), '-g', String(r.fps * 2),
        '-b:v', r.video_bitrate, '-maxrate', '1500k', '-bufsize', '3000k',
        '-c:a', 'aac', '-b:a', r.audio_bitrate, '-ar', String(r.audio.rate ?? 48000), '-ac', '1',
        '-movflags', '+faststart',
        out,
    ], { stdio: ['pipe', 'inherit', 'inherit'] });

    const done = new Promise((ok, fail) => child.on('close', (code) => (code === 0 ? ok() : fail(new Error(`ffmpeg a échoué (code ${code})`)))));

    return { child, done };
}

/** Refait le son seul d'une vidéo déjà encodée (image copiée telle quelle) : quelques secondes. */
function remixAudio(job, video, audio, out, total, gainDb, limit) {
    const r = job.render;
    const run = spawnSync('ffmpeg', [
        '-hide_banner', '-loglevel', 'error', '-y', '-i', video, '-i', audio,
        '-map', '0:v', '-map', '1:a', '-c:v', 'copy',
        '-af', audioFilter(r.audio, gainDb, limit), '-t', total.toFixed(2),
        '-c:a', 'aac', '-b:a', r.audio_bitrate, '-ar', String(r.audio.rate ?? 48000), '-ac', '1',
        '-movflags', '+faststart', out,
    ], { encoding: 'utf8' });

    if (run.status !== 0) {
        throw new Error(`ffmpeg (son) a échoué : ${(run.stderr ?? '').slice(0, 200)}`);
    }
}

/**
 * Vérifie le son de la vidéo encodée et, au besoin, le refait (trois fois au plus) : sonie visée à un point près,
 * crête vraie sous « max_peak_db ». Renvoie la dernière mesure.
 */
export async function fixSound(job, out, audioFile, total, gainDb) {
    const audio = job.render.audio;
    let sound = measureLoudness(out);
    let gain = gainDb;
    let limit = audio.limit;

    for (let round = 0; round < 3; round++) {
        const fix = soundFix(sound, audio, gain, limit);

        if (fix === null) {
            break;
        }

        log(`vidéo ${job.id} : son ${sound.lufs} LUFS, crête ${sound.peak} dBFS : son refait (gain ${fix.gainDb} dB, plafond ${fix.limit}).`);
        const again = `${out}.son.mp4`;
        remixAudio(job, out, audioFile, again, total, fix.gainDb, fix.limit);
        await rename(again, out);
        ({ gainDb: gain, limit } = fix);
        sound = measureLoudness(out);
    }

    return { ...sound, gain };
}

/** Machine trop lente pour finir la vidéo dans le temps accordé : la vidéo est rendue à Talkami, sans échec compté. */
export class TooSlowError extends Error {}

/** Fabrique une vidéo : renvoie sa durée et ses mesures (lèvres, son). Lèvres hors des seuils : erreur. */
async function renderOne(browser, job, audioFile, out, deadline = 0) {
    const workdir = await mkdtemp(join(tmpdir(), 'video-'));
    const server = await serve({ '/__video/voice.wav': audioFile });
    const port = server.address().port;

    try {
        const page = await browser.newPage({ viewport: { width: job.render.avatar_width, height: job.render.avatar_height } });
        page.on('console', (message) => {
            if (['error', 'warning'].includes(message.type())) {
                log(`navigateur : ${message.text()}`);
            }
        });

        let encoder = null;
        let count = 0;
        let frames = 0;
        const started = Date.now();
        await page.exposeFunction('sendFrame', async (base64) => {
            const chunk = Buffer.from(base64, 'base64');

            if (!encoder.child.stdin.write(chunk)) {
                await new Promise((ok) => encoder.child.stdin.once('drain', ok));
            }

            if (++count % 250 === 0) {
                log(`vidéo ${job.id} : ${count} images (${((Date.now() - started) / count).toFixed(0)} millisecondes par image)`);

                if (!willFinish(started, Date.now(), count, frames, deadline)) {
                    throw new TooSlowError(`machine trop lente (${((Date.now() - started) / count).toFixed(0)} millisecondes par image) : la vidéo ne finirait pas dans le temps accordé`);
                }
            }
        });

        await page.goto(`http://127.0.0.1:${port}/__video/render.html`);

        // Durée totale connue d'avance : voix + 1,2 seconde (même calcul que la page).
        const audioSeconds = await audioDuration(audioFile);
        const total = Math.max(job.duration, audioSeconds) + 1.2;
        frames = Math.ceil(total * job.render.fps);
        await writeFile(join(workdir, 'subtitles.ass'), buildAss(job, total));
        const gainDb = gainFor(measureLoudness(audioFile, AUDIO_PRE).lufs, job.render.audio);
        encoder = ffmpeg(job, workdir, audioFile, out, total, gainDb);

        let result;

        try {
            result = await page.evaluate(([j, url]) => window.renderVideo(j, url), [job, '/__video/voice.wav']);
        } catch (error) {
            if (error.message?.includes('machine trop lente')) {
                encoder.done.catch(() => {});
                encoder.child.kill('SIGKILL');
                throw new TooSlowError(error.message.match(/machine trop lente[^\n]*accordé/)?.[0] ?? 'machine trop lente');
            }

            throw error;
        }
        encoder.child.stdin.end();
        await encoder.done;
        await page.close();

        const lips = lipCheck(result.rms, result.open, job.render.lips);
        const sound = await fixSound(job, out, audioFile, total, gainDb);
        log(`vidéo ${job.id} : ${result.frames} images en ${((Date.now() - started) / 1000).toFixed(0)} secondes ; lèvres ${result.lipSync ? 'HeadAudio' : 'selon le volume'}, accord ${lips.correlation}, ouverture ${lips.open_speech} ; son ${sound.lufs} LUFS, crête ${sound.peak} dBFS (gain ${sound.gain} dB).`);

        if (!lips.ok) {
            throw new Error(`Lèvres : ${lips.reason}. Vidéo non déposée.`);
        }

        return { total: result.total, lips, sound };
    } finally {
        server.close();
        await rm(workdir, { recursive: true, force: true });
    }
}

async function local() {
    const job = JSON.parse(await readFile(args.job, 'utf8'));
    const browser = await launch(chromium);

    try {
        // --budget=minutes : essai local de l'arrêt d'une machine trop lente.
        const deadline = args.budget ? Date.now() + Number(args.budget) * 60000 : 0;
        const { total } = await renderOne(browser, job, resolve(args.audio), resolve(args.out ?? 'video.mp4'), deadline);
        log(`Vidéo écrite : ${args.out ?? 'video.mp4'} (${total.toFixed(1)} secondes).`);
    } finally {
        await browser.close();
    }
}

async function remote() {
    if (!base || !token) {
        throw new Error('TALKAMI_URL ou TALKAMI_VIDEO_TOKEN manquant (variables du dépôt GitHub).');
    }

    // « club » ou « promo » : chaque travail de GitHub ne prend que ses vidéos (le Club a son propre passage).
    const kind = ['club', 'promo'].includes(process.env.VIDEO_KIND) ? process.env.VIDEO_KIND : '';
    const type = kind ? `&type=${kind}` : '';

    if (process.env.VIDEO_PEEK === '1') {
        const { waiting, blocked } = await (await api(`/api/videos/travaux?apercu=1${type}`)).json();
        log(`Vidéos dont la voix est prête : ${waiting}.`);

        if (blocked) {
            log(`Fabrication bloquée : ${blocked}`);
            process.exitCode = 1;
        }

        if (process.env.GITHUB_OUTPUT) {
            await writeFile(process.env.GITHUB_OUTPUT, `waiting=${waiting}\n`, { flag: 'a' });
        }

        return;
    }

    // Une vidéo à la fois : chaque vidéo est demandée juste avant sa fabrication (adresse de dépôt toute neuve), et
    // aucune nouvelle vidéo n'est commencée si le temps accordé (VIDEO_BUDGET_MINUTES) ne suffit plus pour la finir :
    // elle attend le passage suivant au lieu d'être coupée par la limite de GitHub (minutes perdues).
    const count = Math.max(1, Math.min(4, Number(process.env.VIDEO_COUNT || 3)));
    const budget = Math.max(0, Number(process.env.VIDEO_BUDGET_MINUTES || 0)) * 60000;
    const started = Date.now();
    let longest = 0;
    let browser = null;
    let failures = 0;

    try {
        for (let made = 0; made < count; made++) {
            if (budget > 0 && made > 0 && Date.now() - started + longest * 1.15 > budget) {
                log(`Temps accordé presque écoulé : les vidéos suivantes attendent le prochain passage.`);
                break;
            }

            const { jobs, blocked } = await (await api(`/api/videos/travaux?limit=1${type}`)).json();

            if (blocked) {
                log(`Fabrication bloquée : ${blocked}`);
                process.exitCode = 1;

                return;
            }

            if (jobs.length === 0) {
                log(made === 0 ? 'Aucune vidéo à fabriquer.' : `Vidéos fabriquées : ${made}.`);
                break;
            }

            browser ??= await launch(chromium);
            const job = jobs[0];
            const workdir = await mkdtemp(join(tmpdir(), 'job-'));
            const begun = Date.now();

            try {
                log(`vidéo ${job.id} : « ${job.title} »`);
                const audio = join(workdir, 'voice.wav');
                await writeFile(audio, Buffer.from(await (await api(job.audio_url)).arrayBuffer()));
                const out = join(workdir, 'video.mp4');
                const { total, lips, sound } = await renderOne(browser, job, audio, out, budget > 0 ? started + budget : 0);
                const size = (await stat(out)).size;
                log(`vidéo ${job.id} : ${(size / 1048576).toFixed(1)} mégaoctets, envoi…`);

                const headers = { ...job.upload.headers, 'Content-Length': String(size) };

                if (!job.upload.direct) {
                    headers.Authorization = `Bearer ${token}`;
                }

                const upload = await fetch(job.upload.url, { method: job.upload.method, headers, body: await readFile(out) });

                if (!upload.ok) {
                    throw new Error(`dépôt refusé (${upload.status})`);
                }

                await api(job.done_url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ duration: total, lips_correlation: lips.correlation, lips_open: lips.open_speech, loudness_lufs: sound.lufs, true_peak_db: sound.peak }) });
                log(`vidéo ${job.id} : prête.`);
            } catch (error) {
                if (error instanceof TooSlowError) {
                    // Rendue telle quelle à Talkami (aucun échec compté) : le passage suivant la reprendra.
                    log(`vidéo ${job.id} : arrêtée, ${error.message}. Elle sera reprise au prochain passage.`);
                    await api(job.release_url, { method: 'POST' }).catch(() => {});
                    break;
                }

                failures++;
                log(`vidéo ${job.id} : échec : ${error.message}`);
                await api(job.failed_url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ error: String(error.message).slice(0, 280) }) }).catch(() => {});
            } finally {
                longest = Math.max(longest, Date.now() - begun);
                await rm(workdir, { recursive: true, force: true });
            }
        }
    } finally {
        await browser?.close();
    }

    if (failures > 0) {
        process.exitCode = 1;
    }
}

await (args.job ? local() : remote());
