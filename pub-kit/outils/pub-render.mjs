// Fabrique les clips muets des professeurs (1080 × 1920, 30 images par seconde) : node pub-render.mjs jobs.json dossier
import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const REPO = '/home/user/agent-anglais-ia';
const { serve, launch } = await import(join(REPO, 'tools/video/common.mjs'));
const { chromium } = await import(join(REPO, 'tools/video/node_modules/playwright-core/index.mjs'));
const [jobsFile, outDir, only] = process.argv.slice(2);
const jobs = JSON.parse(readFileSync(jobsFile, 'utf8')).filter((j) => !only || only.split(',').includes(j.name));
const page = join(dirname(fileURLToPath(import.meta.url)), 'pub-render.html');
const server = await serve({ '/__pub/render.html': page });
const browser = await launch(chromium);
for (const job of jobs) {
    const started = Date.now();
    const p = await browser.newPage({ viewport: { width: job.width, height: job.height } });
    p.on('console', (m) => m.type() === 'error' && console.log('navigateur :', m.text()));
    const out = join(outDir, job.name + (job.transparent ? '.mov' : '.mp4'));
    const enc = job.transparent
        ? ['-c:v', 'prores_ks', '-profile:v', '4444', '-pix_fmt', 'yuva444p10le']
        : ['-c:v', 'libx264', '-preset', 'slow', '-crf', '16', '-pix_fmt', 'yuv420p', '-movflags', '+faststart'];
    const ff = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'image2pipe', '-framerate', String(job.fps), '-c:v', job.transparent ? 'png' : 'mjpeg', '-i', '-', ...enc, out], { stdio: ['pipe', 'inherit', 'inherit'] });
    const closed = new Promise((ok, ko) => ff.on('close', (c) => (c === 0 ? ok() : ko(new Error('ffmpeg ' + c)))));
    await p.exposeFunction('sendFrame', async (b64) => { if (!ff.stdin.write(Buffer.from(b64, 'base64'))) await new Promise((ok) => ff.stdin.once('drain', ok)); });
    await p.goto(`http://127.0.0.1:${server.address().port}/__pub/render.html`);
    const r = await p.evaluate((j) => window.renderClip(j), job);
    ff.stdin.end(); await closed; await p.close();
    console.log(`${job.name} : ${r.frames} images en ${((Date.now() - started) / 1000).toFixed(0)} secondes`);
}
await browser.close(); server.close();
