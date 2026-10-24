# Images du site : sources et licences

Registre de chaque image affichée par le site (ou envoyée en aperçu). Une image dont la licence ne peut pas être prouvée
n'est jamais ajoutée. Aucune mention n'est affichée sur le site (voir aussi `LICENCES-TIERS.md` et `config/photos.php`).

Dernière vérification des licences : **23 octobre 2026** (page de chaque photo relue ce jour-là).

## Photos

| Fichier | Page(s) | Source exacte | Auteur | Licence | Vérifiée le |
|---|---|---|---|---|---|
| `public/images/photos/etudiants-bibliotheque-{480,960}.webp` | Accueil (carte « Vous êtes étudiant ? ») | <https://www.pexels.com/photo/people-on-the-table-reading-books-10744384/> | Peter Chikubula | Licence Pexels (« Free », usage commercial, citation non obligatoire) | 23 octobre 2026 |
| `public/images/photos/eleve-ecouteurs-{480,960}.webp` | Accueil (carte « Vous êtes élève ? »), page Côte d'Ivoire | <https://unsplash.com/photos/a-person-wearing-headphones-and-holding-a-phone-nBiJoRbVHmA> | Akinyemi Gbadamosi | Licence Unsplash (usage commercial, citation non obligatoire) | 23 octobre 2026 |
| `public/images/photos/etudiante-parle-{480,960}.webp` | Page « Apprendre à parler anglais au Togo » | <https://unsplash.com/photos/a-woman-wearing-headphones-and-holding-a-cell-phone-agINaOByj4k> | Ato Aikins | Licence Unsplash | 23 octobre 2026 |
| `public/images/photos/parent-enfant-lecture-{480,960}.webp` | Accueil (carte « Vous êtes parent ? »), page Bénin | <https://unsplash.com/photos/a-woman-reading-a-book-to-a-child-Nu5819t1K6c> | Chidy Young | Licence Unsplash | 23 octobre 2026 |
| `public/images/photos/equipe-reunion-{480,960}.webp` | Accueil (carte « Vous travaillez ? »), page Burkina Faso | <https://unsplash.com/photos/a-group-of-people-sitting-around-a-white-table-YsJUxCIW14U> | Ninthgrid | Licence Unsplash | 23 octobre 2026 |
| `public/images/fondateur-joseph-ahiankonou.webp` | Accueil (« Qui est derrière… »), À propos, Contact | Photo fournie par Joseph Ahiankonou, fondateur | Joseph Ahiankonou | Droits du fondateur (sa propre photo, fournie pour le site) | 23 octobre 2026 |

Ces photos illustrent des publics (élèves, parents, professionnels) : elles ne sont jamais présentées comme des
apprenants de la plateforme, ni accompagnées d'un témoignage, d'un nom ou d'une citation.

## Images créées pour le site

| Fichier | Page(s) | Origine | Licence |
|---|---|---|---|
| `public/images/teacher-joe.webp` | Accueil, résultat de l'évaluation, À propos, « Mon espace », pages d'erreur, hors connexion, états vides (`<x-teacher-face>`) | Rendu du personnage 3D Teacher Joe, créé par `tools/teacher3d/build-teacher-joe.mjs` (aucune ressource tierce) | CC0 1.0 (voir `LICENCES-TIERS.md`) |
| `public/images/teacher-joe-{pensif,accueil,attentif,encourageant,souriant}.webp` | Accueil (« Ce n'est pas un manque d'intelligence… », étapes à partir de 640 pixels) | Rendus de NOTRE modèle 3D Teacher Joe (mêmes salle de classe et lumières que les séances), expressions du module des séances, par `tools/brand/teacher-expressions.mjs` (EN LOCAL), 192 × 192, 3 à 5 Ko | CC0 1.0, création de la plateforme |
| `public/images/partage-talkami.jpg` | Aperçu des liens (WhatsApp, réseaux) | `tools/brand/share-image.html` + `share-image.mjs`, EN LOCAL ; logo, Teacher Joe et textes du site ; le carré du centre (x 285 à 915) reste lisible quand WhatsApp montre une petite vignette carrée | Création de la plateforme |
| `public/brand/*`, `public/icons/*`, `public/favicon.ico` | Toutes les pages, application installée | Logos fournis par le propriétaire ; icônes tirées du logo par `tools/brand/prepare-logos.py` | Propriété de la plateforme |
| `public/images/app/*.webp` | Fenêtre d'installation de Chrome | Captures d'un compte de démonstration (`tools/brand/app-screenshots.mjs`) | Création de la plateforme |

## Écartées

- **Photo de Lomé** (page Togo) : la seule photo libre trouvée le 23 octobre 2026 sur Unsplash
  (<https://unsplash.com/photos/a-tall-building-with-a-palm-tree-in-front-of-it-A1cGd-omvzI>, Ingeborg Korme, licence
  Unsplash) montre l'hôtel de la Paix, abandonné : image non valorisante, écartée. Wikimedia Commons n'a pas pu être
  consulté ce jour-là (limite de requêtes). Aucune illustration de remplacement.
- **Logos des opérateurs Mobile Money** : jamais (droits non accordés) ; les moyens de paiement restent écrits en texte.
