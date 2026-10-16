/*
 * Regroupe en local les bibliothèques du professeur 3D dans public/teacher3d/lib/.
 * Le résultat est committé : aucune compilation sur le serveur.
 *
 * Utilisation : npm install && npm run vendor
 */

import { build } from 'esbuild';
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const out = resolve(here, '../../public/teacher3d/lib');
const modules = resolve(here, 'node_modules');
const version = (name) => JSON.parse(readFileSync(resolve(modules, name, 'package.json'))).version;

mkdirSync(out, { recursive: true });

await build({
    entryPoints: [resolve(here, 'vendor-entry.js')],
    bundle: true,
    format: 'esm',
    minify: true,
    target: 'es2020',
    legalComments: 'none',
    outfile: resolve(out, 'three-teacher.min.js'),
    banner: { js: `/* three.js r${version('three').split('.')[1]} (MIT, three.js authors) — extrait pour le professeur 3D, voir LICENSES.txt */` },
});

const headaudio = resolve(modules, '@met4citizen/headaudio');
copyFileSync(resolve(headaudio, 'dist/headaudio.min.mjs'), resolve(out, 'headaudio.min.js'));
copyFileSync(resolve(headaudio, 'dist/headworklet.min.mjs'), resolve(out, 'headworklet.min.js'));
copyFileSync(resolve(headaudio, 'dist/model-en-mixed.bin'), resolve(out, 'lipsync-en.bin'));

writeFileSync(resolve(out, 'LICENSES.txt'), [
    `three.js ${version('three')} (three-teacher.min.js, dont GLTFLoader et MeshoptDecoder)`,
    readFileSync(resolve(modules, 'three/LICENSE'), 'utf8'),
    `HeadAudio ${version('@met4citizen/headaudio')} (headaudio.min.js, headworklet.min.js, lipsync-en.bin)`,
    readFileSync(resolve(headaudio, 'LICENSE'), 'utf8'),
].join('\n\n'));

console.log('Bibliothèques écrites dans', out);
