// Version « site » des vidéos de présentation (page publique « Comment ça marche ») : GitHub Actions lit la version
// WhatsApp déjà fabriquée (sous-titres déjà dans l'image), la réduit à 480 × 854 (environ 3,5 mégaoctets par minute),
// tire une image d'aperçu, puis dépose les deux fichiers dans le bucket privé. Aucune image refaite, aucun rendu 3D.
// Variables : TALKAMI_URL, TALKAMI_VIDEO_TOKEN, PRESENTATION_KEYS (facultative : evaluation,talkami,questions), PRESENTATION_PEEK.
import { spawnSync } from 'node:child_process';
import { mkdtemp, readFile, rm, stat, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { call } from './talkami-api.mjs';

const base = (process.env.TALKAMI_URL ?? '').replace(/\/$/, '');
const token = process.env.TALKAMI_VIDEO_TOKEN ?? '';
const log = (message) => console.log(message);

/** Commande ffmpeg de la version « site » : image réduite, son mono léger, lecture qui commence tout de suite. */
export function siteArgs(job, source, out) {
    return [
        '-y', '-hide_banner', '-loglevel', 'error', '-i', source,
        '-vf', `scale=${job.width}:${job.height}:flags=lanczos`, '-r', '24',
        '-c:v', 'libx264', '-preset', 'slow', '-profile:v', 'main', '-pix_fmt', 'yuv420p',
        '-b:v', `${job.video_kbps}k`, '-maxrate', `${Math.round(job.video_kbps * 1.3)}k`, '-bufsize', `${job.video_kbps * 2}k`,
        '-c:a', 'aac', '-ac', '1', '-b:a', `${job.audio_kbps}k`, '-movflags', '+faststart', out,
    ];
}

/** Commande ffmpeg de l'image d'aperçu (JPEG). */
export function posterArgs(job, source, out) {
    return ['-y', '-hide_banner', '-loglevel', 'error', '-ss', String(job.poster_second), '-i', source, '-frames:v', '1', '-vf', `scale=${job.width}:${job.height}:flags=lanczos`, '-q:v', '5', out];
}

function ffmpeg(args, what) {
    const run = spawnSync('ffmpeg', args, { encoding: 'utf8' });

    if (run.status !== 0) {
        throw new Error(`ffmpeg (${what}) : ${(run.stderr || '').trim().slice(-200)}`);
    }
}

function api(path, options = {}, extra = {}) {
    const url = path.startsWith('http') ? path : base + path;

    return call(url, { ...options, headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(options.headers ?? {}) } }, { log, ...extra });
}

async function upload(target, file) {
    const size = (await stat(file)).size;
    const headers = { ...target.headers, 'Content-Length': String(size) };

    if (!target.direct) {
        headers.Authorization = `Bearer ${token}`;
    }

    await call(target.url, { method: target.method, headers, body: await readFile(file) }, { log });
}

async function main() {
    if (!base || !token) {
        throw new Error('TALKAMI_URL ou TALKAMI_VIDEO_TOKEN manquant (variables du dépôt GitHub).');
    }

    const keys = (process.env.PRESENTATION_KEYS ?? '').trim();
    const query = keys ? `?cles=${encodeURIComponent(keys)}` : '';

    if (process.env.PRESENTATION_PEEK === '1') {
        const { waiting } = await (await api(`/api/presentations/site/travaux${query}${query ? '&' : '?'}apercu=1`)).json();
        log(`Vidéos à préparer pour le site : ${waiting}.`);

        if (process.env.GITHUB_OUTPUT) {
            await writeFile(process.env.GITHUB_OUTPUT, `waiting=${waiting}\n`, { flag: 'a' });
        }

        return;
    }

    const { jobs } = await (await api(`/api/presentations/site/travaux${query}`)).json();
    log(`Vidéos à préparer pour le site : ${jobs.length}.`);
    let failures = 0;

    for (const job of jobs) {
        const dir = await mkdtemp(join(tmpdir(), `site-${job.key}-`));

        try {
            const source = join(dir, 'source.mp4');
            const video = join(dir, 'site.mp4');
            const poster = join(dir, 'apercu.jpg');
            const response = job.source_url.startsWith(base) ? await api(job.source_url) : await call(job.source_url, {}, { log });
            await writeFile(source, Buffer.from(await response.arrayBuffer()));
            ffmpeg(siteArgs(job, source, video), 'vidéo');
            ffmpeg(posterArgs(job, source, poster), 'aperçu');
            const sizes = { video: (await stat(video)).size, poster: (await stat(poster)).size };
            log(`${job.key} : vidéo ${(sizes.video / 1048576).toFixed(1)} mégaoctets, aperçu ${Math.round(sizes.poster / 1024)} kilooctets.`);
            await upload(job.upload.video, video);
            await upload(job.upload.poster, poster);
            await api(job.done_url, { method: 'POST' });
            log(`${job.key} : prête sur la page « Comment ça marche ».`);
        } catch (error) {
            failures++;
            log(`${job.key} : échec : ${error.message}`);
        } finally {
            await rm(dir, { recursive: true, force: true });
        }
    }

    if (failures > 0) {
        process.exitCode = 1;
    }
}

if (import.meta.url === `file://${process.argv[1]}`) {
    await main();
}
