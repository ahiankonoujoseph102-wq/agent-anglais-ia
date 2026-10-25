import test from 'node:test';
import assert from 'node:assert/strict';
import { posterArgs, siteArgs } from '../../tools/presentation/site-version.mjs';
import { watch } from '../../public/js/watch-video.js';

const job = { width: 480, height: 854, video_kbps: 380, audio_kbps: 64, poster_second: 4 };

test('la version du site est réduite, légère et lisible tout de suite', () => {
    const args = siteArgs(job, 'source.mp4', 'site.mp4');
    assert.ok(args.includes('scale=480:854:flags=lanczos'));
    assert.deepEqual(args.slice(args.indexOf('-b:v'), args.indexOf('-b:v') + 2), ['-b:v', '380k']);
    assert.ok(args.includes('+faststart'));
    assert.deepEqual(args.slice(args.indexOf('-ac'), args.indexOf('-ac') + 2), ['-ac', '1']);
    assert.equal(args.at(-1), 'site.mp4');
});

test("l'aperçu est une seule image prise à la seconde réglée", () => {
    const args = posterArgs(job, 'source.mp4', 'apercu.jpg');
    assert.deepEqual(args.slice(args.indexOf('-ss'), args.indexOf('-ss') + 2), ['-ss', '4']);
    assert.ok(args.includes('-frames:v'));
});

test('la vidéo n’apparaît qu’au toucher, à la place du lien', () => {
    const made = [];
    let replaced = null;
    const doc = { createElement: (tag) => { const element = { tag, attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }, play: () => Promise.resolve() }; made.push(element); return element; } };
    const link = {
        href: 'https://talkami.example/comment-ca-marche/evaluation/video',
        dataset: { title: 'Votre évaluation orale gratuite' },
        querySelector: () => ({ getAttribute: (name) => (name === 'width' ? '480' : '854') }),
        replaceWith: (element) => { replaced = element; },
    };

    const video = watch(link, doc);
    assert.equal(made.length, 1);
    assert.equal(video.tag, 'video');
    assert.equal(replaced, video);
    assert.equal(video.src, link.href);
    assert.equal(video.controls, true);
    assert.equal(video.attributes.playsinline, '');
    assert.equal(video.attributes['aria-label'], 'Votre évaluation orale gratuite');
});
