// Version « e-mail » d'une vidéo de leçon ou du Club : la même vidéo, réduite (540 × 960, son mono léger) pour tenir
// sous la limite d'une pièce jointe (Brevo : 4 mégaoctets), envoyée au fondateur par Talkami (VideoMailer). Encodage en
// deux passes au débit calculé d'après la durée ; trop lourde malgré tout : nouvel essai à un débit plus bas.
import { spawnSync } from 'node:child_process';
import { rm, stat } from 'node:fs/promises';
import { join } from 'node:path';

/** Débit de l'image (kilobits par seconde) pour tenir dans « maxBytes » : marge de 6 % pour l'enveloppe MP4. */
export function mailVideoKbps(maxBytes, seconds, audioKbps, factor = 1) {
    const totalKbps = (maxBytes * 8 * 0.94 * factor) / Math.max(1, seconds) / 1000;

    return Math.floor(totalKbps - audioKbps);
}

/** Commande ffmpeg d'une passe (1 : analyse sans son ; 2 : fichier final). */
export function mailArgs(mail, source, out, videoKbps, pass, passlog) {
    const common = [
        '-y', '-hide_banner', '-loglevel', 'error', '-i', source,
        '-vf', `scale=${mail.width}:${mail.height}:flags=lanczos`,
        '-c:v', 'libx264', '-preset', 'medium', '-profile:v', 'main', '-pix_fmt', 'yuv420p',
        '-b:v', `${videoKbps}k`, '-maxrate', `${Math.round(videoKbps * 1.5)}k`, '-bufsize', `${videoKbps * 2}k`,
        '-pass', String(pass), '-passlogfile', passlog,
    ];

    return pass === 1
        ? [...common, '-an', '-f', 'mp4', '/dev/null']
        : [...common, '-c:a', 'aac', '-ac', '1', '-b:a', `${mail.audio_kbps}k`, '-movflags', '+faststart', out];
}

/**
 * Fabrique la version « e-mail » ; renvoie son chemin, ou null si elle ne peut pas tenir sous la limite (vidéo trop
 * longue : l'e-mail partira avec le lien seulement).
 */
export async function makeMailVersion(mail, source, seconds, workdir) {
    const out = join(workdir, 'email.mp4');
    const passlog = join(workdir, 'email-pass');

    for (const factor of [1, 0.85, 0.7]) {
        const kbps = mailVideoKbps(mail.max_bytes, seconds, mail.audio_kbps, factor);

        if (kbps < 60) {
            return null;
        }

        for (const pass of [1, 2]) {
            const run = spawnSync('ffmpeg', mailArgs(mail, source, out, kbps, pass, passlog), { cwd: workdir, encoding: 'utf8' });

            if (run.status !== 0) {
                throw new Error(`ffmpeg (version e-mail) : ${(run.stderr || '').trim().slice(-200)}`);
            }
        }

        if ((await stat(out)).size <= mail.max_bytes) {
            return out;
        }

        await rm(out, { force: true });
    }

    return null;
}
