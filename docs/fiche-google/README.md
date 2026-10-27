# Images de la fiche Google de Talkami

Images de la fiche Google (Google Business Profile), toutes en JPG, entre 18 et 120 kilooctets.

| Fichier | Taille | Où la mettre dans la fiche Google |
|---|---|---|
| `01-logo-720x720.jpg` | 720 × 720 | Logo |
| `02-couverture-1024x576.jpg` | 1024 × 576 | Photo de couverture |
| `03-photo-teacher-joe.jpg` | 1080 × 1080 | Photos |
| `04-photo-accueil.jpg` | 1080 × 1080 | Photos |
| `05-photo-evaluation-gratuite.jpg` | 1080 × 1080 | Photos |
| `06-photo-seance-orale.jpg` | 1200 × 900 | Photos |
| `07-photo-resultat-evaluation.jpg` | 1080 × 1080 | Photos |
| `08-photo-formules.jpg` | 1080 × 1080 | Photos |
| `09-photo-mon-espace.jpg` | 1080 × 1080 | Photos |

Sources : les logos fournis (`public/brand`), notre modèle 3D de Teacher Joe et de vraies captures du site de
DÉMONSTRATION (base locale, comptes fictifs « Élève », faux fournisseur des séances). Aucun texte ajouté en dehors du
logo, aucune mention d'intelligence artificielle, aucun chiffre ni avis inventé.

Refaire les images (EN LOCAL, après une modification du site) :

```bash
cd tools/presentation && npm install && cd ../..
node tools/presentation/google-profile.mjs /tmp/captures-fiche-google
python3 tools/brand/google-profile.py /tmp/captures-fiche-google
```
