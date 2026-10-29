// Teacher Joe filmé image par image pour une vidéo de présentation : la page de fabrication des vidéos de leçon
// (tools/video/render.html : salle de classe, bouche qui suit la voix, HeadAudio), à 30 images par seconde, écrite
// dans une vidéo intermédiaire sans son (540 × 960). Utilisée en plein écran et, recadrée, dans le médaillon.

import { spawn } from 'node:child_process';
import { lipCheck } from '../video/lip-check.mjs';
import { serve } from '../video/common.mjs';

export async function renderAvatar(browser, job, audioFile, out, log = console.log) {
    const server = await serve({ '/__video/voice.wav': audioFile });
    const port = server.address().port;
    const r = job.render;

    try {
        const page = await browser.newPage({ viewport: { width: r.avatar_width, height: r.avatar_height } });
        page.on('console', (message) => {
            if (message.type() === 'error') {
                log(`navigateur : ${message.text()}`);
            }
        });

        const ffmpeg = spawn('ffmpeg', [
            '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'image2pipe', '-framerate', String(r.fps), '-c:v', 'mjpeg', '-i', '-',
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '16', '-pix_fmt', 'yuv420p', out,
        ], { stdio: ['pipe', 'inherit', 'inherit'] });
        const done = new Promise((ok, fail) => ffmpeg.on('close', (code) => (code === 0 ? ok() : fail(new Error(`ffmpeg (Teacher Joe) a échoué (code ${code})`)))));
        let count = 0;
        const started = Date.now();

        await page.exposeFunction('sendFrame', async (base64) => {
            if (!ffmpeg.stdin.write(Buffer.from(base64, 'base64'))) {
                await new Promise((ok) => ffmpeg.stdin.once('drain', ok));
            }

            if (++count % 300 === 0) {
                log(`${job.teacher ?? 'joe'} : ${count} images (${((Date.now() - started) / count).toFixed(0)} millisecondes par image)`);
            }
        });

        await page.goto(`http://127.0.0.1:${port}/__video/render.html`);
        const result = await page.evaluate(([j, url]) => window.renderVideo(j, url), [job, '/__video/voice.wav']);
        ffmpeg.stdin.end();
        await done;
        await page.close();

        const lips = lipCheck(result.rms, result.open, r.lips);
        log(`${job.teacher ?? 'joe'} : ${result.frames} images en ${((Date.now() - started) / 1000).toFixed(0)} secondes ; lèvres ${result.lipSync ? 'HeadAudio' : 'selon le volume'}, accord ${lips.correlation}, ouverture ${lips.open_speech}.`);

        return { total: result.total, frames: result.frames, lips };
    } finally {
        server.close();
    }
}
