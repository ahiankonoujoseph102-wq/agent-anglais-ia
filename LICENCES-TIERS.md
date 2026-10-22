# Licences des éléments tiers

Éléments utilisés par le site qui ne sont pas écrits pour lui, avec leur auteur et leur licence.
Aucune page du site n'affiche ces mentions : elles sont gardées ici et dans les fichiers eux-mêmes
(en-têtes et fichiers de licence à côté). Mettre ce fichier à jour à chaque ajout ou retrait.

| Élément | Où | Auteur | Licence |
|---|---|---|---|
| Personnage 3D « Teacher Joe » | `public/teacher3d/models/teacher-joe.glb` | créé pour la plateforme (`tools/teacher3d/build-teacher-joe.mjs`) | [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/deed.fr) (domaine public) |
| Police Sora (graisses 400 à 700) | `public/fonts/sora-2.000-latin.woff2` | The Sora Project Authors (Jonathan Barnbrook, Julián Moncada), <https://github.com/sora-xor/sora-font> | [SIL Open Font License 1.1](https://openfontlicense.org), texte complet : `public/fonts/OFL-Sora.txt` |
| Police Sora figée en graisses 400 et 700 (PDF des documents de révision) | `resources/pdf/sora-regular.ttf`, `sora-bold.ttf` (produites par `tools/brand/prepare-pdf-assets.py`) | The Sora Project Authors | SIL Open Font License 1.1, texte complet : `public/fonts/OFL-Sora.txt` |
| dompdf 3.1 (PDF des documents de révision), avec php-font-lib et php-svg-lib | `vendor/dompdf/` (Composer, côté serveur, jamais servi au navigateur) | The Dompdf Community, <https://github.com/dompdf/dompdf> | LGPL 2.1 (dompdf, php-font-lib), LGPL 3.0 (php-svg-lib) ; textes dans `vendor/dompdf/*/LICENSE*` |
| masterminds/html5, sabberworm/php-css-parser (utilisés par dompdf) | `vendor/` (Composer) | leurs auteurs | MIT |
| three.js 0.185.1 (avec GLTFLoader et MeshoptDecoder) | `public/teacher3d/lib/three-teacher.min.js` | three.js authors, <https://threejs.org> | MIT, texte complet : `public/teacher3d/lib/LICENSES.txt` |
| HeadAudio 0.1.0 (lecture labiale) | `public/teacher3d/lib/headaudio.min.js`, `headworklet.min.js`, `lipsync-en.bin` | Mika Suominen, <https://github.com/met4citizen/HeadAudio> | MIT, texte complet : `public/teacher3d/lib/LICENSES.txt` |
| Icônes Lucide 1.49.0 (toutes les icônes, sauf le logo WhatsApp) | `resources/views/components/icon.blade.php` (généré par `tools/icons/build-icons.py`) | Lucide Icons and Contributors, <https://lucide.dev> ; une partie dérivée de Feather (Cole Bemis) | ISC, et MIT pour la partie Feather ; texte complet : `resources/icons/LICENSE-lucide.txt` |

## Photos (Unsplash)

Licence Unsplash (<https://unsplash.com/license>) : usage commercial permis, sans obligation de citer.
Licence vérifiée sur la page de chaque photo le 17 octobre 2026. Liste et textes de remplacement : `config/photos.php`.

| Fichiers | Photo | Auteur |
|---|---|---|
| `public/images/photos/eleve-ecouteurs-*.webp` | <https://unsplash.com/photos/a-person-wearing-headphones-and-holding-a-phone-nBiJoRbVHmA> | Akinyemi Gbadamosi (<https://unsplash.com/@mhyk3y>) |
| `public/images/photos/etudiante-parle-*.webp` | <https://unsplash.com/photos/a-woman-wearing-headphones-and-holding-a-cell-phone-agINaOByj4k> | Ato Aikins (<https://unsplash.com/@ato_aikins>) |
| `public/images/photos/parent-enfant-lecture-*.webp` | <https://unsplash.com/photos/a-woman-reading-a-book-to-a-child-Nu5819t1K6c> (« Reading with Mum », licence gratuite vérifiée sur la page le 17 octobre 2026) | Chidy Young (<https://unsplash.com/@ychidy>) |
| `public/images/photos/equipe-reunion-*.webp` | <https://unsplash.com/photos/a-group-of-people-sitting-around-a-white-table-YsJUxCIW14U> | Ninthgrid (<https://unsplash.com/@ninthgrid_>) |

## Photos (Pexels)

Licence Pexels (<https://www.pexels.com/license/>) : usage gratuit, commercial permis, sans obligation de citer.
Licence (« Free ») vérifiée sur la page de la photo le 20 octobre 2026 ; aucune mention d'image générée par une IA (photo d'appareil, Canon EOS 7D, 10 novembre 2020).

| Fichiers | Photo | Auteur |
|---|---|---|
| `public/images/photos/etudiants-bibliotheque-*.webp` | <https://www.pexels.com/photo/people-on-the-table-reading-books-10744384/> | Peter Chikubula (<https://www.pexels.com/@peter-chikubula-157991289/>) |

## Fabrication des vidéos (outil hors du site, GitHub Actions)

- **Playwright (playwright-core)**, licence Apache 2.0, Microsoft Corporation : pilote Chromium sans écran dans `tools/video/` (installé par GitHub Actions, jamais servi par le site).
- **FFmpeg**, licence LGPL ou GPL selon la compilation : installé par GitHub Actions pour assembler les vidéos (jamais dans le dépôt ni sur le site).
