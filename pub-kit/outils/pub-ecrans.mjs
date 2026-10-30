// Écrans du site de DÉMONSTRATION pour la publicité de 28 secondes (kit pub-kit/) : chaque passage de
// pub-scenes.mjs filmé à part en 1080 × 1920 (téléphone de 360 × 640 points, écran 3x), 30 images par seconde.
// node pub-ecrans.mjs --out=dossier [--only=clé,clé]
import { spawn, spawnSync } from 'node:child_process';
import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { ROOT, launch } from '../video/common.mjs';
import { startFakeGemini } from './fake-gemini.mjs';
import { openDemo, recordTake } from './record.mjs';
import { pubPassages } from './pub-scenes.mjs';
import { FAKE_PORT, SITE, demoEnv, resetDemo, startDemoSite } from './demo-site.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const args = Object.fromEntries(process.argv.slice(2).map((a) => a.replace(/^--/, '').split('=')));
const FPS = 30, WIDTH = 1080, HEIGHT = 1920;
const log = (m) => console.log(`[${new Date().toISOString().slice(11, 19)}] ${m}`);
const tinker = (env, php) => spawnSync('php', ['artisan', 'tinker', '--execute', php], { cwd: ROOT, env, encoding: 'utf8' });

function* sampled(frames, seconds) {
    let index = 0;
    for (let i = 0; i < Math.round(seconds * FPS); i++) {
        while (index + 1 < frames.length && frames[index + 1].t <= i / FPS) index++;
        yield frames[index].file;
    }
}

async function encode(frames, seconds, out) {
    const child = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'mjpeg', '-i', '-',
        '-vf', `scale=${WIDTH}:${HEIGHT}:flags=lanczos,setsar=1,format=yuv420p`, '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-r', String(FPS), '-movflags', '+faststart', out], { stdio: ['pipe', 'inherit', 'inherit'] });
    const done = new Promise((ok, ko) => child.on('close', (c) => (c === 0 ? ok() : ko(new Error('ffmpeg ' + c)))));
    let last = null, buffer = null;
    for (const file of sampled(frames, seconds)) {
        if (file !== last) { buffer = await readFile(file); last = file; }
        if (!child.stdin.write(buffer)) await new Promise((ok) => child.stdin.once('drain', ok));
    }
    child.stdin.end();
    await done;
}

const outDir = resolve(args.out ?? 'pub-ecrans');
const only = args.only?.split(',');
const workdir = await mkdtemp(join(tmpdir(), 'pub-'));
const env = demoEnv(join(workdir, 'demo.sqlite'));
await writeFile(env.DB_DATABASE, '');
await mkdir(outDir, { recursive: true });
const fake = await startFakeGemini(FAKE_PORT);
const browser = await launch(chromium, ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream']);
resetDemo(env, 'CatalogueDemoSeeder');
const site = await startDemoSite(env);
try {
    const effects = await readFile(join(HERE, 'effects.js'), 'utf8');
    const demo = await openDemo(browser, { site: SITE, fakePort: FAKE_PORT, viewport: args.tablet ? { width: 900, height: 1600 } : { width: 360, height: 640 }, scale: args.tablet ? 1.2 : 3, effects, proofImage: Buffer.alloc(0), accounts: {} });
    const notes = [];
    for (const passage of pubPassages().filter((p) => !only || only.includes(p.key))) {
        log(`${passage.key} (${passage.seconds} secondes)`);
        tinker(env, 'App\\Models\\Lesson::query()->where("status", App\\Enums\\LessonStatus::InProgress)->get()->each(fn ($l) => $l->finish(now()));');
        if (passage.teacher) {
            const value = passage.teacher === 'joe' ? 'null' : `"${passage.teacher}"`;
            tinker(env, `App\\Models\\User::query()->where("role", "learner")->update(["teacher" => ${value}]);`);
        }
        const take = await recordTake(demo, [{ name: passage.key, ms: passage.seconds * 1000, def: passage }], join(workdir, passage.key));
        await encode(take.frames, passage.seconds, join(outDir, `${passage.key}${args.tablet ? '-tablette' : ''}.mp4`));
        await rm(join(workdir, passage.key), { recursive: true, force: true });
        notes.push({ key: passage.key, seconds: passage.seconds, note: passage.note });
    }
    await writeFile(join(outDir, 'ecrans.json'), JSON.stringify(notes, null, 2));
    await demo.context.close();
} finally {
    site.kill();
    await browser.close();
    fake.close?.();
    await rm(workdir, { recursive: true, force: true });
}
log('Terminé.');
process.exit(0);
