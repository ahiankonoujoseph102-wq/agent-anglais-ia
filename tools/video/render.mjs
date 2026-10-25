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
// Variables : TALKAMI_URL (adresse du site), TALKAMI_VIDEO_TOKEN (secret), VIDEO_COUNT (1 à 3), CHROME_PATH
// (navigateur), VIDEO_PEEK=1 (dire seulement combien de vidéos attendent, sans rien prendre).
// Essai en local, sans Talkami : node render.mjs --job=job.json --audio=voix.wav --out=video.mp4

import { spawn } from 'node:child_process';
import { mkdtemp, readFile, writeFile, rm, stat } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { chromium } from 'playwright-core';
import { join, resolve } from 'node:path';
import { buildAss } from './subtitles.mjs';
import { lipCheck } from './lip-check.mjs';
import { AUDIO_PRE, FONTS, audioDuration, gainFor, launch, measureLoudness, serve } from './common.mjs';

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
        '-af', `${AUDIO_PRE},volume=${gainDb}dB,alimiter=limit=${r.audio.limit}:attack=3:release=60:level=false,apad`, '-t', total.toFixed(2),
        '-c:v', 'libx264', '-preset', 'medium', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-r', String(r.fps), '-g', String(r.fps * 2),
        '-b:v', r.video_bitrate, '-maxrate', '1500k', '-bufsize', '3000k',
        '-c:a', 'aac', '-b:a', r.audio_bitrate, '-ar', '44100', '-ac', '1',
        '-movflags', '+faststart',
        out,
    ], { stdio: ['pipe', 'inherit', 'inherit'] });

    const done = new Promise((ok, fail) => child.on('close', (code) => (code === 0 ? ok() : fail(new Error(`ffmpeg a échoué (code ${code})`)))));

    return { child, done };
}

/** Fabrique une vidéo : renvoie sa durée et ses mesures (lèvres, son). Lèvres hors des seuils : erreur. */
async function renderOne(browser, job, audioFile, out) {
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
        const started = Date.now();
        await page.exposeFunction('sendFrame', async (base64) => {
            const chunk = Buffer.from(base64, 'base64');

            if (!encoder.child.stdin.write(chunk)) {
                await new Promise((ok) => encoder.child.stdin.once('drain', ok));
            }

            if (++count % 250 === 0) {
                log(`vidéo ${job.id} : ${count} images (${((Date.now() - started) / count).toFixed(0)} millisecondes par image)`);
            }
        });

        await page.goto(`http://127.0.0.1:${port}/__video/render.html`);

        // Durée totale connue d'avance : voix + 1,2 seconde (même calcul que la page).
        const audioSeconds = await audioDuration(audioFile);
        const total = Math.max(job.duration, audioSeconds) + 1.2;
        await writeFile(join(workdir, 'subtitles.ass'), buildAss(job, total));
        const gainDb = gainFor(measureLoudness(audioFile, AUDIO_PRE).lufs, job.render.audio);
        encoder = ffmpeg(job, workdir, audioFile, out, total, gainDb);

        const result = await page.evaluate(([j, url]) => window.renderVideo(j, url), [job, '/__video/voice.wav']);
        encoder.child.stdin.end();
        await encoder.done;
        await page.close();

        const lips = lipCheck(result.rms, result.open, job.render.lips);
        const sound = measureLoudness(out);
        log(`vidéo ${job.id} : ${result.frames} images en ${((Date.now() - started) / 1000).toFixed(0)} secondes ; lèvres ${result.lipSync ? 'HeadAudio' : 'selon le volume'}, accord ${lips.correlation}, ouverture ${lips.open_speech} ; son ${sound.lufs} LUFS, crête ${sound.peak} dBFS (gain ${gainDb} dB).`);

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
        const { total } = await renderOne(browser, job, resolve(args.audio), resolve(args.out ?? 'video.mp4'));
        log(`Vidéo écrite : ${args.out ?? 'video.mp4'} (${total.toFixed(1)} secondes).`);
    } finally {
        await browser.close();
    }
}

async function remote() {
    if (!base || !token) {
        throw new Error('TALKAMI_URL ou TALKAMI_VIDEO_TOKEN manquant (variables du dépôt GitHub).');
    }

    if (process.env.VIDEO_PEEK === '1') {
        const { waiting, blocked } = await (await api('/api/videos/travaux?apercu=1')).json();
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

    const count = Math.max(1, Math.min(3, Number(process.env.VIDEO_COUNT || 3)));
    const { jobs, blocked } = await (await api(`/api/videos/travaux?limit=${count}`)).json();
    log(`Vidéos à fabriquer : ${jobs.length}.`);

    if (blocked) {
        log(`Fabrication bloquée : ${blocked}`);
        process.exitCode = 1;

        return;
    }

    if (jobs.length === 0) {
        return;
    }

    const browser = await launch(chromium);
    let failures = 0;

    try {
        for (const job of jobs) {
            const workdir = await mkdtemp(join(tmpdir(), 'job-'));

            try {
                log(`vidéo ${job.id} : « ${job.title} »`);
                const audio = join(workdir, 'voice.wav');
                await writeFile(audio, Buffer.from(await (await api(job.audio_url)).arrayBuffer()));
                const out = join(workdir, 'video.mp4');
                const { total, lips, sound } = await renderOne(browser, job, audio, out);
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
                failures++;
                log(`vidéo ${job.id} : échec : ${error.message}`);
                await api(job.failed_url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ error: String(error.message).slice(0, 280) }) }).catch(() => {});
            } finally {
                await rm(workdir, { recursive: true, force: true });
            }
        }
    } finally {
        await browser.close();
    }

    if (failures > 0) {
        process.exitCode = 1;
    }
}

await (args.job ? local() : remote());
