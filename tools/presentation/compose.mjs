// Assemblage d'une vidéo de présentation (1080 × 1920, 30 images par seconde) : fond bleu nuit et téléphone (écran
// du site de démonstration filmé), Teacher Joe en plein écran ou dans un médaillon, chapitre à droite du téléphone,
// sous-titres en bas (mots importants en orange), voix à environ −14 LUFS. Puis une version légère pour WhatsApp.
//
// Zone sûre de TikTok : rien d'important dans les 10 % du haut, les 20 % du bas, les 12 % de droite.

import { spawn } from 'node:child_process';
import { readFile, writeFile, copyFile, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { assColor, assTime, chunk, highlightSite, render as renderWords, words } from '../video/subtitles.mjs';
import { AUDIO_PRE, FONTS, PUBLIC, gainFor, measureLoudness } from '../video/common.mjs';

const WHITE = '&H00FFFFFF&';

function run(args, label) {
    return new Promise((ok, fail) => {
        const child = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', ...args], { stdio: ['ignore', 'inherit', 'inherit'] });
        child.on('close', (code) => (code === 0 ? ok() : fail(new Error(`ffmpeg (${label}) a échoué (code ${code})`))));
    });
}

/** Intervalles [début, fin) de chaque phrase : jusqu'au début de la suivante, la dernière jusqu'à la fin. */
export function windows(job, total) {
    return job.segments.map((segment, index) => ({
        ...segment,
        from: index === 0 ? 0 : segment.start,
        to: job.segments[index + 1]?.start ?? total,
    }));
}

/** Prises : suites de phrases « à l'écran » qui se suivent, avec la durée de chaque scène (millisecondes). */
export function takes(job, total) {
    const result = [];
    let current = null;

    for (const w of windows(job, total)) {
        if (w.shot !== 'screen') {
            current = null;
            continue;
        }

        if (!current) {
            current = { from: w.from, scenes: [] };
            result.push(current);
        }

        current.scenes.push({ name: w.scene, ms: Math.round((w.to - w.from) * 1000) });
        current.to = w.to;
    }

    return result;
}

/** Expression ffmpeg « vrai pendant ces intervalles » (intervalles voisins réunis). */
export function enableExpr(intervals) {
    const merged = [];

    for (const [a, b] of intervals) {
        const last = merged[merged.length - 1];

        if (last && Math.abs(last[1] - a) < 0.001) {
            last[1] = b;
        } else {
            merged.push([a, b]);
        }
    }

    return merged.length === 0 ? '0' : merged.map(([a, b]) => `between(t,${a.toFixed(3)},${(b - 0.001).toFixed(3)})`).join('+');
}

/** Sous-titres et chapitres (ASS, police Sora). */
export function buildAss(job, total) {
    const r = job.render;
    const sub = r.subtitles;
    const ch = r.chapter;
    const card = { top: 250, size: 92, second: 60, box: '&H285C2A0E&', ...(r.card ?? {}) };
    const accent = assColor('#FF7A1A');
    const box = '&H70000000&';
    const lines = [
        '[Script Info]', 'ScriptType: v4.00+', `PlayResX: ${r.width}`, `PlayResY: ${r.height}`, 'WrapStyle: 0', 'ScaledBorderAndShadow: yes', '',
        '[V4+ Styles]',
        'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
        `Style: Sub,Sora,${sub.size},${WHITE},${WHITE},${box},${box},-1,0,0,0,100,100,0,0,3,12,0,8,${sub.side_margin},${sub.right_margin},${sub.top},1`,
        // Bandeau en gros (« CE SOIR · 19 HEURES ») au-dessus de Teacher Joe en plein écran, sous la zone de TikTok.
        `Style: Card,Sora,${card.size},${WHITE},${WHITE},${card.box},${card.box},-1,0,0,0,100,100,0,0,3,26,0,8,${card.margin_left ?? sub.side_margin},${card.margin_right ?? sub.right_margin},${card.top},1`,
        `Style: Chapter,Sora,${ch.size},${accent},${WHITE},&H00000000&,&H00000000&,-1,0,0,0,100,100,0,0,1,0,0,7,${ch.x},${r.width - ch.x - ch.width},${ch.y},1`,
        '',
        '[Events]',
        'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text',
    ];
    const all = windows(job, total);

    // Bandeau : de sa phrase jusqu'à la fin des phrases qui gardent le même bandeau (aucun clignotement).
    all.forEach((w, index) => {
        const text = (w.card ?? []).join('\n');
        const previous = (all[index - 1]?.card ?? []).join('\n');

        if (text === '' || text === previous) {
            return;
        }

        let end = w.to;

        for (const next of all.slice(index + 1)) {
            if ((next.card ?? []).join('\n') !== text) {
                break;
            }

            end = next.to;
        }

        // *Mots* en orange ([en]…[/en]) : couleur rendue à la fin du passage (blanc sur la première ligne, orange ensuite).
        const color = (line, base) => line.replace(/[{}\\]/g, '').replace(/\[en\]/g, `{\\c${accent}}`).replace(/\[\/en\]/g, `{\\c${base}}`);
        const [first, ...rest] = w.card;
        const body = [color(first, WHITE), ...rest.map((line) => `{\\fs${card.second}\\c${accent}}${color(line, accent)}`)].join('\\N');
        lines.push(`Dialogue: 0,${assTime(w.from)},${assTime(end)},Card,,0,0,0,,${body}`);
    });

    // Chapitre : de sa phrase jusqu'au chapitre suivant ou au prochain plein écran.
    all.forEach((w, index) => {
        if (w.shot !== 'screen' || !w.chapter) {
            return;
        }

        let end = w.to;

        for (const next of all.slice(index + 1)) {
            if (next.shot !== 'screen' || next.chapter) {
                break;
            }

            end = next.to;
        }

        lines.push(`Dialogue: 0,${assTime(w.from)},${assTime(end)},Chapter,,0,0,0,,${w.chapter.replace(/[{}\\]/g, '')}`);
    });

    all.forEach((w) => {
        if (w.closing) {
            const plain = words(w.subtitle.replace(/\[\/?en\]/g, '')).map(({ word }) => ({ word, en: false }));
            lines.push(`Dialogue: 0,${assTime(w.start)},${assTime(w.to)},Sub,,0,0,0,,${highlightSite(renderWords(plain, accent), job.site, accent, Math.round(sub.size * 1.4))}`);

            return;
        }

        // Un morceau fait seulement de ponctuation (« ». ») rejoint le précédent…
        const pieces = chunk(w.subtitle, sub.chars ?? 56).reduce((list, piece) => {
            const text = (words) => words.map(({ word }) => word).join(' ');
            const previous = list[list.length - 1];
            // … ou une citation coupée en deux (« J'ai payé, j'envoie ma / capture »), si l'ensemble reste court.
            const openQuote = previous && text(previous).lastIndexOf('«') > text(previous).lastIndexOf('»');

            if (previous && (text(piece).replace(/[«».,;:!?\s]/g, '').length < 3 || (openQuote && text(previous).length + text(piece).length <= (sub.chars ?? 56) + 20))) {
                list[list.length - 1].push(...piece);
            } else {
                list.push(piece);
            }

            return list;
        }, []);
        // Ponctuation collée à un mot en orange (« cloud, ») : le découpage perd ce repère, il est remis ici.
        const flat = words(w.subtitle);
        let position = 0;
        pieces.forEach((piece) => piece.forEach((word, i) => {
            word.glue = i > 0 && Boolean(flat[position]?.glue);
            position++;
        }));
        const weights = pieces.map((piece) => piece.reduce((sum, { word }) => sum + word.length + 1, 0));
        const totalWeight = weights.reduce((sum, value) => sum + value, 0) || 1;
        const span = Math.max(0.5, w.end - w.start);
        let at = w.start;

        pieces.forEach((piece, i) => {
            const until = i === pieces.length - 1 ? w.to : at + span * (weights[i] / totalWeight);
            lines.push(`Dialogue: 0,${assTime(at)},${assTime(until)},Sub,,0,0,0,,${renderWords(piece, accent)}`);
            at = until;
        });
    });

    return `${lines.join('\n')}\n`;
}

/**
 * Bruitages des publicités, fabriqués par ffmpeg (aucun fichier, aucune licence) : « pop » (petit bruit sec, à
 * l'apparition d'un bandeau) et « ding » (la bonne réponse).
 */
export const SFX = {
    pop: "aevalsrc='0.6*sin(2*PI*(500+1400*t)*t)*exp(-28*t)':s=44100:d=0.18",
    ding: "aevalsrc='0.5*sin(2*PI*1318*t)*exp(-5*t)+0.25*sin(2*PI*1975*t)*exp(-7*t)':s=44100:d=0.9",
};

/** Heures des bruitages (début de leur phrase, un peu avant) ; aucun sans le réglage « sfx » (publicités). */
export function sfxEvents(job, settings) {
    if (!settings) {
        return [];
    }

    return job.segments
        .filter((segment) => SFX[segment.sfx])
        .map((segment) => ({ kind: segment.sfx, at: Math.max(0, segment.start - (settings.lead ?? 0)) }));
}

/** Fond, cadre du téléphone, anneau et masque du médaillon : dessinés par Chromium (police Sora, logo blanc). */
export async function renderLayers(browser, job, dir) {
    const r = job.render;
    const p = r.phone;
    const screenHeight = Math.round(p.width * r.viewport.height / r.viewport.width);
    const m = r.medallion;
    const ch = r.chapter;
    const page = await browser.newPage({ viewport: { width: r.width, height: r.height } });
    const font = `url(data:font/woff2;base64,${(await import('node:fs')).readFileSync(join(PUBLIC, 'fonts/sora-2.000-latin.woff2')).toString('base64')})`;
    const logo = `data:image/png;base64,${(await import('node:fs')).readFileSync(join(PUBLIC, 'brand/logo-blanc.png')).toString('base64')}`;
    const base = `<style>@font-face{font-family:Sora;src:${font};font-weight:100 900}html,body{margin:0;width:${r.width}px;height:${r.height}px;font-family:Sora;overflow:hidden}*{box-sizing:border-box}</style>`;

    const shots = {
        // Fond : bleu nuit, une lueur orange, logo et étiquette dans la colonne de droite (hors zone de TikTok).
        background: `${base}<body style="background:radial-gradient(circle at 85% 30%,rgba(255,122,26,.22),transparent 45%),radial-gradient(circle at 10% 90%,rgba(80,130,220,.25),transparent 50%),#0E2A5C">
            <img src="${logo}" style="position:absolute;left:${ch.x}px;top:${ch.y - 75}px;width:${ch.width - 60}px">
            <div style="position:absolute;left:${ch.x}px;top:${m.y - 64}px;width:${ch.width}px;color:#fff;opacity:.85;font-size:24px;font-weight:600;line-height:1.3">Écran de démonstration</div>`,
        // Cadre du téléphone : transparent sur l'écran (coins arrondis), bord sombre autour.
        bezel: `${base}<body style="background:transparent"><div style="position:absolute;left:${p.x}px;top:${p.y}px;width:${p.width}px;height:${screenHeight}px;border-radius:38px;box-shadow:0 0 0 16px #071633,0 0 0 18px rgba(255,255,255,.18),0 30px 60px 10px rgba(0,0,0,.45)"></div>`,
        ring: `${base}<body style="background:transparent"><div style="position:absolute;left:0;top:0;width:${m.size}px;height:${m.size}px;border-radius:50%;border:8px solid #FF7A1A;box-shadow:0 10px 30px rgba(0,0,0,.45)"></div>`,
        mask: `${base}<body style="background:#000"><div style="position:absolute;left:0;top:0;width:${m.size - 16}px;height:${m.size - 16}px;border-radius:50%;background:#fff"></div>`,
    };

    const files = {};

    for (const [name, html] of Object.entries(shots)) {
        await page.setContent(html);
        await page.evaluate(() => document.fonts.ready);
        files[name] = join(dir, `${name}.png`);
        const transparent = name === 'bezel' || name === 'ring';
        const clip = name === 'ring' ? { x: 0, y: 0, width: m.size, height: m.size } : name === 'mask' ? { x: 0, y: 0, width: m.size - 16, height: m.size - 16 } : undefined;
        await page.screenshot({ path: files[name], omitBackground: transparent, clip });
    }

    await page.close();

    return { ...files, screenHeight };
}

/**
 * Vidéo de l'écran sur toute la durée, à 30 images par seconde exactement : pour chaque image, la dernière image
 * filmée à cette heure-là ; du noir hors des prises (caché par Teacher Joe en plein écran).
 */
export function screenFrames(recorded, total, black, fps = 30) {
    const list = [];

    for (let k = 0; k < Math.round(total * fps); k++) {
        const at = k / fps;
        const take = recorded.find((t) => at >= t.from && at < t.to);
        let file = black;

        if (take) {
            const local = at - take.from;
            let chosen = take.frames[0];

            for (const frame of take.frames) {
                if (frame.t > local) {
                    break;
                }

                chosen = frame;
            }

            file = chosen?.file ?? black;
        }

        list.push(file);
    }

    return list;
}

export async function buildScreen(recorded, total, dir, black, size) {
    const out = join(dir, 'screen.mp4');
    const child = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'image2pipe', '-framerate', '30', '-c:v', 'mjpeg', '-i', '-',
        '-vf', `scale=${size.width}:${size.height}:flags=lanczos,format=yuv420p`, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '16', out], { stdio: ['pipe', 'inherit', 'inherit'] });
    const done = new Promise((ok, fail) => child.on('close', (code) => (code === 0 ? ok() : fail(new Error(`ffmpeg (écran) a échoué (code ${code})`)))));
    const cache = new Map();

    for (const file of screenFrames(recorded, total, black)) {
        if (!cache.has(file)) {
            cache.clear();
            cache.set(file, await readFile(file));
        }

        if (!child.stdin.write(cache.get(file))) {
            await new Promise((ok) => child.stdin.once('drain', ok));
        }
    }

    child.stdin.end();
    await done;

    return out;
}

/** Vidéo finale pour TikTok (pleine qualité) ; renvoie ses mesures de son. */
export async function compose(job, total, parts, dir, out) {
    const r = job.render;
    const p = r.phone;
    const m = r.medallion;
    const all = windows(job, total);
    const full = enableExpr(all.filter((w) => w.shot === 'full').map((w) => [w.from, w.to]));
    const medallion = enableExpr(all.filter((w) => w.shot === 'screen' && w.medallion).map((w) => [w.from, w.to]));
    const crop = r.medallion_crop ?? { x: 120, y: 120, size: 300 };
    const inner = m.size - 16;

    await writeFile(join(dir, 'subtitles.ass'), buildAss(job, total));
    const gainDb = gainFor(measureLoudness(parts.audio, AUDIO_PRE).lufs, r.audio);
    const effects = sfxEvents(job, r.sfx);
    const voiceChain = `${AUDIO_PRE},volume=${gainDb}dB`;
    const limiter = `alimiter=limit=${r.audio.limit}:attack=3:release=60:level=false,apad`;
    // Sans bruitage : exactement la chaîne d'avant (les autres vidéos ne changent pas).
    const audio = effects.length === 0
        ? { filter: [], map: '6:a', af: ['-af', `${voiceChain},${limiter}`], inputs: [] }
        : {
            filter: [
                `[6:a]${voiceChain}[vo]`,
                ...effects.map((e, i) => `[${7 + i}:a]volume=${r.sfx.volume_db}dB,adelay=${Math.round(e.at * 1000)}:all=1[fx${i}]`),
                `[vo]${effects.map((_, i) => `[fx${i}]`).join('')}amix=inputs=${effects.length + 1}:duration=first:normalize=0,${limiter}[au]`,
            ],
            map: '[au]',
            af: [],
            inputs: effects.flatMap((e) => ['-f', 'lavfi', '-i', SFX[e.kind]]),
        };
    const filter = [
        `[1:v]scale=${p.width}:${parts.layers.screenHeight}:flags=lanczos,setsar=1[scr]`,
        `[0:v][scr]overlay=${p.x}:${p.y}[a]`,
        '[a][3:v]overlay=0:0[b]',
        '[2:v]split=2[av1][av2]',
        `[av2]crop=${crop.size}:${crop.size}:${crop.x}:${crop.y},scale=${inner}:${inner},format=rgba[face]`,
        '[5:v]format=gray[mk]',
        '[face][mk]alphamerge[med]',
        `[b][med]overlay=${m.x + 8}:${m.y + 8}:enable='${medallion}'[c]`,
        `[c][4:v]overlay=${m.x}:${m.y}:enable='${medallion}'[d]`,
        `[av1]scale=${r.width}:${r.height}:flags=lanczos[fullav]`,
        `[d][fullav]overlay=0:0:enable='${full}'[e]`,
        `[e]ass=${join(dir, 'subtitles.ass')}:fontsdir=${FONTS},format=yuv420p[v]`,
        ...audio.filter,
    ].join(';');

    await run([
        '-loop', '1', '-framerate', String(r.fps), '-i', parts.layers.background,
        '-i', parts.screen,
        '-i', parts.avatar,
        '-loop', '1', '-framerate', String(r.fps), '-i', parts.layers.bezel,
        '-loop', '1', '-framerate', String(r.fps), '-i', parts.layers.ring,
        '-loop', '1', '-framerate', String(r.fps), '-i', parts.layers.mask,
        '-i', parts.audio,
        ...audio.inputs,
        '-filter_complex', filter,
        '-map', '[v]', '-map', audio.map,
        ...audio.af,
        '-t', total.toFixed(3),
        '-c:v', 'libx264', '-preset', 'medium', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-r', String(r.fps), '-g', String(r.fps * 2),
        '-b:v', r.video_bitrate, '-maxrate', '8000k', '-bufsize', '12000k',
        '-c:a', 'aac', '-b:a', r.audio_bitrate, '-ar', '44100', '-ac', '1',
        '-movflags', '+faststart', out,
    ], 'assemblage');

    return { gainDb, sound: measureLoudness(out) };
}

/**
 * Version légère pour WhatsApp (720 × 1280) sous la limite de taille : débit calculé d'après la durée, réduit si le
 * fichier dépasse encore.
 */
export async function lightVersion(source, total, limitBytes, out) {
    const audioKbps = 96;
    let videoKbps = Math.min(2500, Math.floor((limitBytes * 8 * 0.9) / total / 1000 - audioKbps));

    for (let attempt = 0; attempt < 4; attempt++) {
        if (videoKbps < 150) {
            throw new Error('version légère impossible sous la limite de taille');
        }

        await run([
            '-i', source, '-vf', 'scale=720:1280:flags=lanczos', '-c:v', 'libx264', '-preset', 'medium', '-profile:v', 'main', '-pix_fmt', 'yuv420p',
            '-b:v', `${videoKbps}k`, '-maxrate', `${Math.round(videoKbps * 1.3)}k`, '-bufsize', `${videoKbps * 2}k`,
            '-c:a', 'aac', '-b:a', `${audioKbps}k`, '-movflags', '+faststart', out,
        ], 'version légère');

        if ((await stat(out)).size <= limitBytes) {
            return { kbps: videoKbps };
        }

        videoKbps = Math.floor(videoKbps * 0.8);
    }

    throw new Error('version légère encore trop lourde');
}

export { copyFile };
