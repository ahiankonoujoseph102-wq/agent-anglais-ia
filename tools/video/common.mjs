// Outils communs à la fabrication des vidéos (vidéos de leçon : render.mjs ; vidéos de présentation :
// tools/presentation/make.mjs) : petit serveur local, mesure de la sonie, gain, durée d'un WAV, Chromium sans écran.

import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, extname, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
export const ROOT = resolve(HERE, '../..');
export const PUBLIC = join(ROOT, 'public');
export const FONTS = join(ROOT, 'resources/pdf');
// Voix : léger nettoyage et compresseur avant la mesure de la sonie (le gain est calculé après eux).
export const AUDIO_PRE = 'highpass=f=70,acompressor=threshold=-21dB:ratio=3:attack=5:release=120';
const TYPES = { '.html': 'text/html', '.js': 'text/javascript', '.mjs': 'text/javascript', '.glb': 'model/gltf-binary', '.bin': 'application/octet-stream', '.wav': 'audio/wav', '.json': 'application/json', '.woff2': 'font/woff2' };

/** Petit serveur local : public/ du dépôt, la page de fabrication et la voix de la vidéo en cours. */
export function serve(extra) {
    const server = createServer(async (request, response) => {
        const path = decodeURIComponent(new URL(request.url, 'http://local').pathname);
        const own = { '/__video/render.html': join(HERE, 'render.html'), '/__video/lip-check.mjs': join(HERE, 'lip-check.mjs') };
        const file = own[path] ?? extra[path] ?? join(PUBLIC, path);

        if (!file.startsWith(PUBLIC) && !Object.values(extra).includes(file) && !Object.values(own).includes(file)) {
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

/**
 * Sonie intégrée (LUFS) et crête (dBFS) d'un fichier audio ou vidéo, mesurées par ffmpeg (ebur128), après un filtre
 * facultatif.
 */
export function measureLoudness(file, filter = '') {
    const run = spawnSync('ffmpeg', ['-hide_banner', '-nostats', '-i', file, '-af', `${filter ? `${filter},` : ''}ebur128=peak=true`, '-f', 'null', '-'], { encoding: 'utf8' });
    const text = run.stderr ?? '';
    const lufs = [...text.matchAll(/^\s+I:\s+(-?[\d.]+) LUFS/gm)].pop();
    const peak = [...text.matchAll(/Peak:\s+(-?[\d.]+) dBFS/g)].pop();

    return { lufs: lufs ? Number(lufs[1]) : null, peak: peak ? Number(peak[1]) : null };
}

/** Gain (dB) pour amener la voix à la sonie visée ; le limiteur en reprend un peu, compensé par « makeup ». */
export function gainFor(measured, audio) {
    if (measured === null || !Number.isFinite(measured)) {
        return 0;
    }

    return Math.round(Math.min(24, Math.max(-12, audio.target_lufs - measured + (audio.limiter_makeup_db ?? 0))) * 10) / 10;
}

/**
 * Chaîne du son d'une vidéo : passage à 48 kHz D'ABORD (le limiteur voit alors les crêtes telles qu'elles seront
 * jouées), compresseur léger, gain, limiteur, silence jusqu'à la fin de l'image.
 */
export function audioFilter(audio, gainDb, limit = audio.limit) {
    return `aresample=${audio.rate ?? 48000},${AUDIO_PRE},volume=${gainDb}dB,alimiter=limit=${limit}:attack=3:release=60:level=false,apad`;
}

/**
 * Son mesuré après l'encodage : null s'il est bon (sonie à « tolerance_lu » près, crête vraie sous « max_peak_db »),
 * sinon le nouveau gain et le nouveau plafond du limiteur pour refaire le son seul.
 * @returns {{gainDb: number, limit: number}|null}
 */
export function soundFix(sound, audio, gainDb, limit = audio.limit) {
    const maxPeak = audio.max_peak_db ?? -1;
    const tolerance = audio.tolerance_lu ?? 1;

    if (sound.lufs === null || sound.peak === null) {
        return null;
    }

    const peakOk = sound.peak <= maxPeak;
    const loudOk = Math.abs(sound.lufs - audio.target_lufs) <= tolerance;

    if (peakOk && loudOk) {
        return null;
    }

    // Crête trop haute : plafond baissé d'autant, plus une marge d'un demi-décibel ; sonie : gain corrigé.
    const nextLimit = peakOk ? limit : limit * 10 ** ((maxPeak - 0.5 - sound.peak) / 20);
    const nextGain = gainDb + (audio.target_lufs - sound.lufs);

    return { gainDb: Math.round(Math.min(30, Math.max(-12, nextGain)) * 10) / 10, limit: Math.round(Math.max(0.3, Math.min(1, nextLimit)) * 1000) / 1000 };
}

/**
 * Après « after » images, la vidéo finira-t-elle avant « deadline » (heure, en millisecondes) ? Faux si la
 * projection dépasse : inutile de brûler des minutes pour être coupé par la limite de GitHub.
 */
export function willFinish(started, now, count, frames, deadline, after = 250) {
    if (!deadline || count < after || count === 0) {
        return true;
    }

    return started + ((now - started) / count) * frames <= deadline;
}

/** Durée d'un WAV PCM 16 bits, lue dans son en-tête. */
export async function audioDuration(file) {
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

/** Chromium sans écran, WebGL par le processeur (« chromium » : celui de playwright-core de l'outil appelant). */
export function launch(chromium, extraArgs = []) {
    const executablePath = process.env.CHROME_PATH || ['/usr/bin/google-chrome', '/opt/pw-browsers/chromium'].find((path) => existsSync(path));

    return chromium.launch({
        executablePath,
        args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist', '--autoplay-policy=no-user-gesture-required', ...extraArgs],
    });
}
