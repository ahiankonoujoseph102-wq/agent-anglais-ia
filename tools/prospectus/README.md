# Prospectus Talkami (A6, noir sur blanc)

Outils LOCAUX, jamais lancés sur le serveur. Ils fabriquent le prospectus à distribuer dans la rue et ne touchent pas au site.

- `prospectus.html` : la mise en page (A6, texte noir sur papier blanc, aucun fond plein : impression économique et photocopie).
- `logo-noir.png` : le logo en noir seul, produit par `python3 logo-noir.py` à partir de `public/brand/logo-horizontal.png`.
- `values.php` : nom de la plateforme et du professeur, formule la moins chère, durée de l'évaluation gratuite et WhatsApp de l'équipe, lus dans la configuration du site (jamais écrits à la main).
- `qr.py` : code QR en SVG (segno, correction d'erreur « Q », modules pleins jointifs).
- `build.mjs` : fabrique `prospectus-a6.pdf` (imprimerie), `prospectus-4-par-a4.pdf` (quatre par feuille, traits de coupe discrets) et `prospectus.png` (WhatsApp, 300 points par pouce).
- `check-qr.py` : vérifie que le code QR se lit encore après une mauvaise impression (100 à 300 points par pouce, flou, encre pâle ou trop forte, bruit, compression, réduction à 70 %, photo penchée), avec zxing-cpp.

## Fabriquer

```bash
pip install segno zxing-cpp numpy pillow          # une fois
node tools/prospectus/build.mjs                   # prospectus commun → docs/prospectus/
python3 tools/prospectus/check-qr.py docs/prospectus/prospectus-4-par-a4.pdf "https://talkami.net/?utm_source=prospectus"
```

## Un prospectus par distributeur

```bash
node tools/prospectus/build.mjs --campagne=afi    # → docs/prospectus/distributeurs/afi/
```

Le code QR mène alors à `https://talkami.net/?utm_source=prospectus&utm_campaign=afi`, et « Réf. afi » est écrit en tout petit en bas du prospectus. Dans l'administration, onglet « Provenance » : ligne « Prospectus (papier) » et tableau « Prospectus, par distributeur » (inscrits, évaluation passée, abonnés).

Règles du texte : celles des pages publiques (aucune mention d'intelligence artificielle ni de fournisseur, aucun lien TikTok, aucun chiffre ni avis inventé, vrais prix lus dans la configuration). `tests/Feature/ProspectusTest.php`.
