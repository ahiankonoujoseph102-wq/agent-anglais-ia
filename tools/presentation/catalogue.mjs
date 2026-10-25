// CATALOGUE DES ÉCRANS pour un montage (GitHub Actions, .github/workflows/catalogue.yml, lancé à la main ; ou en local
// pour une répétition) : UNE vidéo muette, 1080 × 1920, 30 images par seconde, de tous les écrans du site de
// DÉMONSTRATION (catalogue-scenes.mjs), bout à bout, séparés par une seconde de noir ; la voix de Teacher Joe du
// passage où il parle, dans un fichier à part ; l'index des passages (début, fin, moments forts).
//
// Site LOCAL de démonstration (demo-site.mjs, base CatalogueDemoSeeder) : comptes fictifs « Élève », faux fournisseur
// des séances, aucun paiement, jamais le site en ligne. Rien d'ajouté à l'écran : ni voix, ni médaillon, ni
// sous-titres, ni cercle, ni flèche ; seulement le doigt au toucher.
//
// Talkami ne donne que la voix de Teacher Joe (vidéo de présentation déjà prête, jeton TALKAMI_VIDEO_TOKEN) et reçoit
// les deux fichiers (bucket privé) ; la page « Vidéos » de l'administration les propose au téléchargement.
// Répétition locale, sans Talkami : node catalogue.mjs --out=dossier [--voice=voix.wav] [--only=clé,clé]
// (sans --voice : un son de remplacement, seulement pour vérifier ; jamais déposé).

import { spawn, spawnSync } from 'node:child_process';
import { mkdir, mkdtemp, readFile, rm, stat, writeFile, copyFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { ROOT, launch } from '../video/common.mjs';
import { startFakeGemini, speechLike } from './fake-gemini.mjs';
import { openDemo, recordTake } from './record.mjs';
import { renderAvatar } from './avatar.mjs';
import { CALM, passages as allPassages } from './catalogue-scenes.mjs';
import { FAKE_PORT, SITE, demoEnv, resetDemo, startDemoSite } from './demo-site.mjs';
import { call } from './talkami-api.mjs';
import { FPS, GAP, HEIGHT, WIDTH, clock, indexText, timeline } from './catalogue-index.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const args = Object.fromEntries(process.argv.slice(2).map((arg) => arg.replace(/^--/, '').split('=')));
const base = (process.env.TALKAMI_URL ?? '').replace(/\/+$/, '');
const token = process.env.TALKAMI_VIDEO_TOKEN ?? '';

// Téléphone de 360 × 640 points, écran 3x : exactement 1080 × 1920.
const VIEWPORT = { width: 360, height: 640 };
const SCALE = 3;
const RATE = 24000;
const ADMIN_PHONE = '+22899000009';

function log(message) {
    console.log(`[${new Date().toISOString().slice(11, 19)}] ${message}`);
}

async function api(path, options = {}, extra = {}) {
    const url = path.startsWith('http') ? path : `${base}${path}`;

    return call(url, { ...options, headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(options.headers ?? {}) } }, { log, ...extra });
}

function run(command, list, what) {
    const result = spawnSync(command, list, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });

    if (result.status !== 0) {
        throw new Error(`${what} : ${(result.stderr || result.stdout || '').trim().slice(-300)}`);
    }

    return result.stdout;
}

/** WAV PCM 16 bits mono. */
function wav(pcm, rate = RATE) {
    const header = Buffer.alloc(44);
    header.write('RIFF', 0);
    header.writeUInt32LE(36 + pcm.length, 4);
    header.write('WAVEfmt ', 8);
    header.writeUInt32LE(16, 16);
    header.writeUInt16LE(1, 20);
    header.writeUInt16LE(1, 22);
    header.writeUInt32LE(rate, 24);
    header.writeUInt32LE(rate * 2, 28);
    header.writeUInt16LE(2, 32);
    header.writeUInt16LE(16, 34);
    header.write('data', 36);
    header.writeUInt32LE(pcm.length, 40);

    return Buffer.concat([header, pcm]);
}

/**
 * Voix du passage où Teacher Joe parle : CALM de silence, les premières phrases « plein écran » de la vidéo de
 * présentation (« max_seconds » au plus, sans couper une phrase), puis du silence jusqu'à la durée du passage
 * moins 1,2 seconde (la page de rendu ajoute 1,2 seconde après la voix). Renvoie aussi la voix seule, à la durée
 * exacte du passage, pour le fichier à part.
 */
async function teacherVoice(source, voice, seconds, dir) {
    const pieces = [];

    for (const segment of voice.segments) {
        if (segment.shot !== 'full' || segment.end - (pieces[0]?.start ?? segment.start) > voice.max_seconds) {
            break;
        }

        pieces.push(segment);
    }

    const from = pieces[0]?.start ?? 0;
    const to = pieces.at(-1)?.end ?? Math.min(voice.max_seconds, 8);
    const cut = join(dir, 'voix-coupee.wav');
    run('ffmpeg', ['-v', 'error', '-y', '-i', source, '-ss', from.toFixed(3), '-to', to.toFixed(3), '-ac', '1', '-ar', String(RATE), '-c:a', 'pcm_s16le', cut], 'voix');
    const pcm = (await readFile(cut)).subarray(44);
    const silence = (s) => Buffer.alloc(Math.round(s * RATE) * 2);
    const lead = silence(CALM / 1000);
    const forRender = Buffer.concat([lead, pcm, silence(Math.max(0, seconds - 1.2 - CALM / 1000 - pcm.length / 2 / RATE))]);
    const forEditor = Buffer.concat([lead, pcm, silence(Math.max(0, seconds - CALM / 1000 - pcm.length / 2 / RATE))]);
    await writeFile(join(dir, 'voix-rendu.wav'), wav(forRender));
    await writeFile(join(dir, 'voix-monteur.wav'), wav(forEditor));

    return { render: join(dir, 'voix-rendu.wav'), editor: join(dir, 'voix-monteur.wav'), speech: pcm.length / 2 / RATE };
}

/** Images de la prise rangées en 30 images par seconde, sur exactement « seconds » secondes. */
function* sampled(frames, seconds) {
    let index = 0;

    for (let i = 0; i < Math.round(seconds * FPS); i++) {
        const t = i / FPS;

        while (index + 1 < frames.length && frames[index + 1].t <= t) {
            index++;
        }

        yield frames[index].file;
    }
}

/** Passage filmé : vidéo intermédiaire de très bonne qualité (la compression finale se fait une seule fois). */
async function encodeFrames(frames, seconds, out) {
    const child = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'mjpeg', '-i', '-',
        '-vf', `scale=${WIDTH}:${HEIGHT}:flags=lanczos,setsar=1,format=yuv420p`, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '12', '-r', String(FPS), out], { stdio: ['pipe', 'inherit', 'inherit'] });
    const done = new Promise((ok, fail) => child.on('close', (code) => (code === 0 ? ok() : fail(new Error(`ffmpeg (passage) a échoué (code ${code})`)))));
    let last = null;
    let buffer = null;

    for (const file of sampled(frames, seconds)) {
        if (file !== last) {
            buffer = await readFile(file);
            last = file;
        }

        if (!child.stdin.write(buffer)) {
            await new Promise((ok) => child.stdin.once('drain', ok));
        }
    }

    child.stdin.end();
    await done;
}

/** Page de cahier DESSINÉE (aucune vraie photo), choisie dans « Mes cours » pour l'aperçu, jamais envoyée. */
async function notebookPhoto(browser) {
    const page = await browser.newPage({ viewport: { width: 600, height: 800 } });
    await page.setContent(`<body style="margin:0;height:800px;background:repeating-linear-gradient(#FCFAF0 0 39px,#BECDE6 39px 40px);font:26px Georgia,serif;color:#0E2A5C;padding:60px 50px;box-sizing:border-box">
        <h1 style="font-size:34px;margin:0 0 30px">Present simple</h1><p>I work in Lomé.</p><p>She works in a bank.</p><p>He plays football.</p>
        <h2 style="font-size:28px;margin:40px 0 10px">Exercise 1</h2><p>She ___ (to work) in a bank.</p><p>They ___ (to go) to school.</p></body>`);
    const image = await page.screenshot();
    await page.close();

    return image;
}

/** Réglages de l'image de Teacher Joe, lus dans le code du site de démonstration (aucun appel à Talkami). */
function renderSettings(env) {
    const out = spawnSync('php', ['artisan', 'videos:catalogue-render', '--no-interaction'], { cwd: ROOT, env, encoding: 'utf8' });

    if (out.status !== 0) {
        throw new Error(`réglages de Teacher Joe : ${(out.stderr || out.stdout).slice(-300)}`);
    }

    return JSON.parse(out.stdout.trim().split('\n').pop());
}

/** Valeurs de la base de démonstration utiles aux scènes (code d'attestation, leçon « Mes cours »). */
function demoValues(env) {
    const php = 'echo json_encode(["attestationCode" => App\\Models\\Attestation::query()->value("code"), "courseId" => App\\Models\\CourseLesson::query()->value("id")]);';
    const out = spawnSync('php', ['artisan', 'tinker', '--execute', php], { cwd: ROOT, env, encoding: 'utf8' });

    return JSON.parse((out.stdout || '{}').trim().split('\n').pop());
}

/** Teacher Joe en grand, lèvres synchronisées (page de fabrication des vidéos de leçon). */
async function teacherClip(browser, settings, voiceFile, seconds, out) {
    const render = { ...settings.render, fps: FPS, avatar_width: WIDTH, avatar_height: HEIGHT, antialias: true };
    const job = { id: 1, key: 'catalogue', duration: 0, segments: [], render, avatar: settings.avatar };
    const result = await renderAvatar(browser, job, voiceFile, out, log);

    if (!result.lips.ok) {
        throw new Error(`Lèvres : ${result.lips.reason}.`);
    }

    // Exactement la durée du passage.
    const fixed = `${out}.fixe.mp4`;
    run('ffmpeg', ['-v', 'error', '-y', '-i', out, '-vf', `scale=${WIDTH}:${HEIGHT}:flags=lanczos,setsar=1,format=yuv420p,tpad=stop_mode=clone:stop_duration=2`, '-t', String(seconds), '-r', String(FPS), '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '12', fixed], 'Teacher Joe');

    return { file: fixed, lips: result.lips };
}

function probe(file) {
    const out = spawnSync('ffprobe', ['-v', 'error', '-count_frames', '-show_entries', 'format=duration:stream=width,height,r_frame_rate,nb_read_frames', '-of', 'json', file], { encoding: 'utf8' });

    return JSON.parse(out.stdout || '{}');
}

/** Images de contrôle d'un passage (début après le temps calme, milieu, fin), regardées avant toute livraison. */
function stills(file, seconds, dir, key) {
    for (const [name, at] of [['debut', CALM / 1000 + 0.3], ['milieu', seconds / 2], ['fin', seconds - CALM / 1000 - 0.3]]) {
        spawnSync('ffmpeg', ['-v', 'error', '-y', '-ss', at.toFixed(2), '-i', file, '-frames:v', '1', '-vf', 'scale=540:960', '-q:v', '3', join(dir, `${key}-${name}.jpg`)]);
    }
}

/** Assemblage final : une seconde de noir entre deux passages, une seule compression. */
function assemble(clips, dir, out) {
    const black = join(dir, 'noir.mp4');
    run('ffmpeg', ['-v', 'error', '-y', '-f', 'lavfi', '-i', `color=c=black:s=${WIDTH}x${HEIGHT}:r=${FPS}:d=${GAP}`, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '12', '-pix_fmt', 'yuv420p', black], 'noir');
    const list = clips.flatMap((clip, index) => (index === 0 ? [clip] : [black, clip])).map((file) => `file '${file}'`).join('\n');
    const listFile = join(dir, 'liste.txt');
    spawnSync('sh', ['-c', `cat > '${listFile}'`], { input: `${list}\n` });
    // Qualité : CRF 22, « slow » ; écrans de site et noir se compressent très bien (poids raisonnable pour un téléphone).
    run('ffmpeg', ['-v', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', listFile, '-c:v', 'libx264', '-preset', 'slow', '-crf', process.env.CATALOGUE_CRF || '22',
        '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-r', String(FPS), '-g', String(FPS * 2), '-an', '-movflags', '+faststart', out], 'assemblage');
}

async function make({ voiceSource, voice, outDir, only }) {
    const workdir = await mkdtemp(join(tmpdir(), 'catalogue-'));
    const env = demoEnv(join(workdir, 'demo.sqlite'));
    await writeFile(env.DB_DATABASE, '');
    const fake = await startFakeGemini(FAKE_PORT);
    const browser = await launch(chromium, ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream']);
    let site = null;

    try {
        await mkdir(join(outDir, 'controle'), { recursive: true });
        resetDemo(env, 'CatalogueDemoSeeder');
        const settings = renderSettings(env);
        const values = demoValues(env);
        const list = allPassages(values).filter((p) => !only || only.includes(p.key));
        const entries = timeline(list);
        const teacherIndex = list.findIndex((p) => p.teacher);
        const teacherSeconds = list[teacherIndex]?.seconds ?? 20;

        // 1. Teacher Joe en grand (avant le site : son image sert aussi d'aperçu aux vidéos de la base de démonstration).
        log('Teacher Joe : voix et image par image');
        const voiceFiles = await teacherVoice(voiceSource, voice, teacherSeconds, workdir);
        const teacher = await teacherClip(browser, settings, voiceFiles.render, teacherSeconds, join(workdir, 'teacher.mp4'));
        log(`Teacher Joe : ${voiceFiles.speech.toFixed(1)} secondes de voix, lèvres accord ${teacher.lips.correlation}, ouverture ${teacher.lips.open_speech}.`);

        // Vidéos de la base de démonstration (« Comment ça marche », page « Vidéos ») : Teacher Joe, en petit.
        const media = join(ROOT, 'storage/framework/catalogue');
        await mkdir(media, { recursive: true });
        run('ffmpeg', ['-v', 'error', '-y', '-i', teacher.file, '-vf', 'scale=480:854', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-an', '-movflags', '+faststart', join(media, 'teacher.mp4')], 'vidéo de démonstration');
        run('ffmpeg', ['-v', 'error', '-y', '-ss', '6', '-i', teacher.file, '-frames:v', '1', '-vf', 'scale=480:854', '-q:v', '4', join(media, 'poster.jpg')], 'aperçu de démonstration');

        // 2. Le site de démonstration, passage par passage.
        resetDemo(env, 'CatalogueDemoSeeder');
        site = await startDemoSite(env);
        const effects = await readFile(join(HERE, 'effects.js'), 'utf8');
        const photo = await notebookPhoto(browser);
        const demo = await openDemo(browser, { site: SITE, fakePort: FAKE_PORT, viewport: VIEWPORT, scale: SCALE, effects, proofImage: photo ?? Buffer.alloc(0), coursePhoto: photo, accounts: { admin: ADMIN_PHONE } });
        const clips = [];

        for (const [index, passage] of list.entries()) {
            const out = join(workdir, `passage-${String(index + 1).padStart(2, '0')}.mp4`);

            if (passage.teacher) {
                await copyFile(teacher.file, out);
            } else {
                log(`${index + 1}. ${passage.key} (${passage.seconds} secondes)`);
                const take = await recordTake(demo, [{ name: passage.key, ms: passage.seconds * 1000, def: passage }], join(workdir, `prise-${index + 1}`));
                await encodeFrames(take.frames, passage.seconds, out);
                await rm(join(workdir, `prise-${index + 1}`), { recursive: true, force: true });
            }

            stills(out, passage.seconds, join(outDir, 'controle'), `${String(index + 1).padStart(2, '0')}-${passage.key}`);
            clips.push(out);
        }

        await demo.context.close();

        // 3. La vidéo unique, la voix à part, l'index.
        log('Assemblage de la vidéo unique');
        const video = join(outDir, 'talkami-catalogue-des-ecrans.mp4');
        assemble(clips, workdir, video);
        const voiceStart = teacherIndex >= 0 ? entries[teacherIndex].start : null;
        await copyFile(voiceFiles.editor, join(outDir, 'talkami-catalogue-voix-de-teacher-joe.wav'));
        await writeFile(join(outDir, 'index.txt'), indexText(entries, voiceStart));
        await writeFile(join(outDir, 'index.json'), JSON.stringify({ passages: entries, voice_start: voiceStart }, null, 2));

        // Contrôles : durée, taille, images par seconde.
        const info = probe(video);
        const stream = info.streams?.[0] ?? {};
        const duration = Number(info.format?.duration ?? 0);
        const expected = entries.at(-1).end;
        const size = (await stat(video)).size;
        log(`Vidéo : ${clock(duration)} (${duration.toFixed(2)} secondes, attendu ${expected.toFixed(2)}), ${stream.width} × ${stream.height}, ${stream.r_frame_rate} images par seconde, ${(size / 1048576).toFixed(1)} mégaoctets.`);

        if (stream.width !== WIDTH || stream.height !== HEIGHT || Math.abs(duration - expected) > 0.2) {
            throw new Error('vidéo du catalogue non conforme (taille ou durée)');
        }

        return { video, voice: join(outDir, 'talkami-catalogue-voix-de-teacher-joe.wav'), entries, voiceStart, duration, size };
    } finally {
        await browser.close();
        site?.kill();
        await fake.close();
        await rm(workdir, { recursive: true, force: true });
    }
}

async function upload(target, file) {
    const size = (await stat(file)).size;
    const headers = { ...target.headers, 'Content-Length': String(size) };

    if (!target.direct) {
        headers.Authorization = `Bearer ${token}`;
    }

    await call(target.url, { method: target.method, headers, body: await readFile(file) }, { log });
}

async function local() {
    const outDir = resolve(args.out ?? 'catalogue');
    const workdir = await mkdtemp(join(tmpdir(), 'catalogue-voix-'));
    let voiceSource = args.voice ? resolve(args.voice) : null;
    let voice = { segments: [{ shot: 'full', start: 0, end: 12 }], max_seconds: 15.5 };

    if (!voiceSource) {
        // Son de remplacement (bruit modulé en syllabes) : seulement pour la répétition, jamais déposé.
        voiceSource = join(workdir, 'remplacement.wav');
        await writeFile(voiceSource, wav(speechLike(12, 7)));
    } else {
        voice = { segments: [{ shot: 'full', start: 0, end: 15 }], max_seconds: 15.5 };
    }

    const result = await make({ voiceSource, voice, outDir, only: args.only?.split(',') });
    await rm(workdir, { recursive: true, force: true });
    log(`Catalogue écrit dans ${outDir}.`);

    return result;
}

async function remote() {
    if (!base || !token) {
        throw new Error('TALKAMI_URL ou TALKAMI_VIDEO_TOKEN manquant (variables du dépôt GitHub).');
    }

    const job = await (await api('/api/catalogue/travail')).json();
    const workdir = await mkdtemp(join(tmpdir(), 'catalogue-voix-'));
    const voiceSource = join(workdir, 'voix.wav');
    await writeFile(voiceSource, Buffer.from(await (await api(job.voice.audio_url)).arrayBuffer()));
    const outDir = resolve(process.env.CATALOGUE_OUT || join(workdir, 'sortie'));
    const result = await make({ voiceSource, voice: job.voice, outDir });

    log('Envoi de la vidéo et de la voix…');
    await upload(job.upload.video, result.video);
    await upload(job.upload.voix, result.voice);
    await api(job.done_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ duration: result.duration, voice_start: result.voiceStart, passages: result.entries }),
    });
    log(`Catalogue prêt : ${(result.size / 1048576).toFixed(0)} mégaoctets, ${clock(result.duration)}. Page « Vidéos » de l'administration, « Catalogue des écrans ».`);
    process.stdout.write(`\n${indexText(result.entries, result.voiceStart)}`);
}

if (import.meta.url === `file://${process.argv[1]}`) {
    await (args.out || args.voice || args.only ? local() : remote());
}
