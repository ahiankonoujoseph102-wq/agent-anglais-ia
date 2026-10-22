// Fabrication des vidéos de leçon de Teacher Joe (GitHub Actions, .github/workflows/videos.yml).
//
// 1. Demande à Talkami les vidéos dont la voix est prête (jeton TALKAMI_VIDEO_TOKEN).
// 2. Pour chacune : Chromium sans écran (WebGL par le processeur) filme Teacher Joe image par image (render.html),
//    ffmpeg ajoute le fond, les sous-titres (subtitles.mjs, police Sora) et la voix, et écrit un MP4 vertical
//    1080 × 1920 (H.264 + AAC, compatible TikTok et WhatsApp).
// 3. Dépose la vidéo dans le bucket privé (adresse signée donnée par Talkami) et prévient Talkami.
//
// Variables : TALKAMI_URL (adresse du site), TALKAMI_VIDEO_TOKEN (secret), VIDEO_COUNT (1 à 3), CHROME_PATH
// (navigateur), VIDEO_PEEK=1 (dire seulement combien de vidéos attendent, sans rien prendre).
// Essai en local, sans Talkami : node render.mjs --job=job.json --audio=voix.wav --out=video.mp4

import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { mkdtemp, readFile, writeFile, rm, stat } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, extname, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { buildAss } from './subtitles.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const PUBLIC = join(ROOT, 'public');
const FONTS = join(ROOT, 'resources/pdf');
const TYPES = { '.html': 'text/html', '.js': 'text/javascript', '.mjs': 'text/javascript', '.glb': 'model/gltf-binary', '.bin': 'application/octet-stream', '.wav': 'audio/wav', '.json': 'application/json', '.woff2': 'font/woff2' };

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

/** Petit serveur local : public/ du dépôt, la page de fabrication et la voix de la vidéo en cours. */
function serve(extra) {
    const server = createServer(async (request, response) => {
        const path = decodeURIComponent(new URL(request.url, 'http://local').pathname);
        const file = path === '/__video/render.html' ? join(HERE, 'render.html')
            : extra[path] ?? join(PUBLIC, path);

        if (!file.startsWith(PUBLIC) && !Object.values(extra).includes(file) && file !== join(HERE, 'render.html')) {
            response.writeHead(403).end();

            return;
        }

        try {
            const body = await readFile(file);
            response.writeHead(200, { 'Content-Type': TYPES[extname(file)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
            response.end(body);
        } catch {
            response.writeHead(404).end();
        }
    });

    return new Promise((ok) => server.listen(0, '127.0.0.1', () => ok(server)));
}

function ffmpeg(job, workdir, audio, out, total) {
    const r = job.render;
    const scaledHeight = Math.round(r.avatar_height * (r.width / r.avatar_width));
    const filter = [
        `color=c=${r.background.replace('#', '0x')}:s=${r.width}x${r.height}:r=${r.fps}[bg]`,
        `[0:v]scale=${r.width}:${scaledHeight}:flags=lanczos[av]`,
        `[bg][av]overlay=0:${r.avatar_top}:shortest=1[base]`,
        `[base]ass=${join(workdir, 'subtitles.ass')}:fontsdir=${FONTS}[v]`,
    ].join(';');

    const child = spawn('ffmpeg', [
        '-hide_banner', '-loglevel', 'error', '-y',
        '-f', 'image2pipe', '-framerate', String(r.fps), '-c:v', 'mjpeg', '-i', '-',
        '-i', audio,
        '-filter_complex', filter,
        '-map', '[v]', '-map', '1:a',
        '-af', 'apad', '-t', total.toFixed(2),
        '-c:v', 'libx264', '-preset', 'medium', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-r', String(r.fps), '-g', String(r.fps * 2),
        '-b:v', r.video_bitrate, '-maxrate', '1500k', '-bufsize', '3000k',
        '-c:a', 'aac', '-b:a', r.audio_bitrate, '-ar', '44100', '-ac', '1',
        '-movflags', '+faststart',
        out,
    ], { stdio: ['pipe', 'inherit', 'inherit'] });

    const done = new Promise((ok, fail) => child.on('close', (code) => (code === 0 ? ok() : fail(new Error(`ffmpeg a échoué (code ${code})`)))));

    return { child, done };
}

/** Fabrique une vidéo : renvoie sa durée. */
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
        encoder = ffmpeg(job, workdir, audioFile, out, total);

        const result = await page.evaluate(([j, url]) => window.renderVideo(j, url), [job, '/__video/voice.wav']);
        encoder.child.stdin.end();
        await encoder.done;
        await page.close();
        log(`vidéo ${job.id} : ${result.frames} images, lèvres ${result.lipSync ? 'HeadAudio' : 'selon le volume'}, ${((Date.now() - started) / 1000).toFixed(0)} secondes.`);

        return result.total;
    } finally {
        server.close();
        await rm(workdir, { recursive: true, force: true });
    }
}

/** Durée d'un WAV PCM 16 bits, lue dans son en-tête. */
async function audioDuration(file) {
    const bytes = await readFile(file);
    let position = 12;
    let rate = 24000;
    let channels = 1;

    while (position + 8 <= bytes.length) {
        const id = bytes.toString('ascii', position, position + 4);
        const size = bytes.readUInt32LE(position + 4);

        if (id === 'fmt ') {
            channels = bytes.readUInt16LE(position + 10);
            rate = bytes.readUInt32LE(position + 12);
        } else if (id === 'data') {
            return Math.min(size, bytes.length - position - 8) / (rate * channels * 2);
        }

        position += 8 + size + (size % 2);
    }

    return 0;
}

function launch() {
    const executablePath = process.env.CHROME_PATH || ['/usr/bin/google-chrome', '/opt/pw-browsers/chromium'].find((path) => existsSync(path));

    return chromium.launch({
        executablePath,
        args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist', '--autoplay-policy=no-user-gesture-required'],
    });
}

async function local() {
    const job = JSON.parse(await readFile(args.job, 'utf8'));
    const browser = await launch();

    try {
        const total = await renderOne(browser, job, resolve(args.audio), resolve(args.out ?? 'video.mp4'));
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
        const { waiting } = await (await api('/api/videos/travaux?apercu=1')).json();
        log(`Vidéos dont la voix est prête : ${waiting}.`);

        if (process.env.GITHUB_OUTPUT) {
            await writeFile(process.env.GITHUB_OUTPUT, `waiting=${waiting}\n`, { flag: 'a' });
        }

        return;
    }

    const count = Math.max(1, Math.min(3, Number(process.env.VIDEO_COUNT || 3)));
    const { jobs } = await (await api(`/api/videos/travaux?limit=${count}`)).json();
    log(`Vidéos à fabriquer : ${jobs.length}.`);

    if (jobs.length === 0) {
        return;
    }

    const browser = await launch();
    let failures = 0;

    try {
        for (const job of jobs) {
            const workdir = await mkdtemp(join(tmpdir(), 'job-'));

            try {
                log(`vidéo ${job.id} : « ${job.title} »`);
                const audio = join(workdir, 'voice.wav');
                await writeFile(audio, Buffer.from(await (await api(job.audio_url)).arrayBuffer()));
                const out = join(workdir, 'video.mp4');
                const total = await renderOne(browser, job, audio, out);
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

                await api(job.done_url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ duration: total }) });
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
