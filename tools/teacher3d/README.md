# Professeur 3D — outils locaux

Ces scripts tournent **sur un poste de développement**, jamais sur le serveur. Leur résultat est committé dans `public/teacher3d/`.

```bash
cd tools/teacher3d
npm install
npm run model    # régénère public/teacher3d/models/teacher-joe.glb
npm run vendor   # regroupe three.js + copie HeadAudio dans public/teacher3d/lib/
```

## Le personnage « Teacher Joe »

`build-teacher-joe.mjs` construit le personnage de A à Z (géométrie, couleurs, squelette, formes du visage), sans aucune ressource externe. Il est placé dans le **domaine public (CC0 1.0)**.

- Squelette aux noms Mixamo : `Hips`, `Spine`, `Spine1`, `Spine2`, `Neck`, `Head`, `LeftEye`/`RightEye`, `LeftShoulder`, `LeftArm`, `LeftForeArm`, `LeftHand` (et `Right…`). Tous les os sont sans rotation au repos : leurs axes sont ceux de la scène.
- 15 formes de bouche Oculus (`viseme_sil`, `viseme_PP`, `viseme_FF`, `viseme_TH`, `viseme_DD`, `viseme_kk`, `viseme_CH`, `viseme_SS`, `viseme_nn`, `viseme_RR`, `viseme_aa`, `viseme_E`, `viseme_I`, `viseme_O`, `viseme_U`).
- 17 expressions aux noms ARKit : `jawOpen`, `mouthSmileLeft/Right`, `mouthFrownLeft/Right`, `mouthPucker`, `eyeBlinkLeft/Right`, `eyeSquintLeft/Right`, `eyeWideLeft/Right`, `browInnerUp`, `browDownLeft/Right`, `browOuterUpLeft/Right`.
- Couleurs par sommet (aucune texture), compression meshopt + quantification : environ 300 Ko.

Pour modifier l'apparence (teinte de peau, tenue, lunettes…), changer les constantes en tête du script (`C`, `HEAD_SCALE`, `torsoProfile`…) puis relancer `npm run model`. Le test `tests/Unit/AvatarModelTest.php` vérifie le poids (< 5 Mo), les os et les formes.

## Remplacer le personnage

1. Déposer le nouveau fichier GLB dans `public/teacher3d/models/` (5 Mo au plus, compression meshopt ou sans compression ; Draco n'est pas pris en charge).
2. Dans `config/avatar.php`, changer `model.file`, `model.name`, le cadrage (`camera`) et la hauteur du bureau ; mettre `model.profile` à `generic` si le squelette n'est pas celui de Teacher Joe (les gestes des bras sont alors désactivés, la tête, le regard et le visage restent animés).
3. Mettre à jour LICENCES-TIERS.md (à la racine du dépôt) et CLAUDE.md.

Les avatars au format Ready Player Me, Avaturn ou MPFB + TalkingHead suivent ces conventions (vérifier leur licence : beaucoup interdisent l'usage commercial).

## Bibliothèques

| Fichier | Origine | Licence |
|---|---|---|
| `three-teacher.min.js` | three.js 0.185.1 (extrait : rendu, GLTFLoader, MeshoptDecoder), regroupé par esbuild | MIT |
| `headaudio.min.js`, `headworklet.min.js`, `lipsync-en.bin` | HeadAudio 0.1.0 (Mika Suominen) : visèmes déduits du son | MIT |

Les textes de licence sont dans `public/teacher3d/lib/LICENSES.txt`.
