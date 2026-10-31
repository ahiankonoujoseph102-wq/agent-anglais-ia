# Charte graphique — Talkami

Ce document décrit l'identité visuelle de la plateforme. Le nom, le nom court, l'accroche et les couleurs de l'application installée sont réglés dans `config/platform.php` ; les couleurs du site dans `public/css/tokens.css` (seul fichier de couleurs). Ne jamais écrire le nom ou une couleur en dur ailleurs.

- **Nom de la plateforme** : Talkami (`config('platform.name')`, nom court `config('platform.brand.short_name')`).
- **Nom du professeur** : Teacher Joe (`config('avatar.model.name')`). Ce n'est pas le nom de la plateforme.

## Couleurs

### Couleurs principales

| Rôle | Code | Variable | Usage |
|---|---|---|---|
| Principale : bleu nuit | `#0E2A5C` | `--c-primary` | en-tête, titres, pied de page, liens, boutons principaux, gilet de Teacher Joe |
| Accent : orange vif | `#FF7A1A` | `--c-accent` | boutons d'action (« Commencer l'évaluation gratuite »), cravate de Teacher Joe, détails |
| Fond | `#F4F6FB` | `--c-bg` | fond de toutes les pages |

### Nuances dérivées (toutes issues du bleu nuit ou de l'orange)

| Code | Variable | Usage |
|---|---|---|
| `#0A1F45` | `--c-primary-strong` | survol des boutons bleus, textes forts sur fond clair |
| `#E7ECF6` | `--c-primary-soft` | fonds de badges et de bulles d'icônes |
| `#C3CEE5` | `--c-primary-line` | bordures teintées |
| `#3D6BC4` | `--c-primary-bright` | détails décoratifs (jamais pour du texte) |
| `#FF8F3F` | `--c-accent-hover` | survol des boutons orange |
| `#B25512` | `--c-accent-strong` | **texte orange sur fond clair** (petits titres, mots mis en valeur), contour de focus |
| `#8A3A00` | `--c-accent-ink` | texte sur fond orange pâle |
| `#FFEDE0` | `--c-accent-soft` | fond orange pâle (bulles d'icônes d'accent) |
| `#E9EDF6` | `--c-bg-warm` | second fond (bandes, scène du professeur) |
| `#14213D` | `--c-text` | texte courant |
| `#4E5A70` | `--c-muted` | texte secondaire |
| `#C9D5EC` | `--c-on-dark-muted` | texte secondaire sur bleu nuit (pied de page) |
| `#DDE3EE` | `--c-border` | bordures des cartes |
| `#7C879B` | `--c-field` | bordures des champs de formulaire |

### Seule exception : WhatsApp

Le bouton « WhatsApp » garde un vert (`#0F7A40`, `--c-whatsapp`, texte blanc 5,4:1), parce que c'est la couleur que les apprenants reconnaissent. **Un seul autre vert** (décision du fondateur, 31 octobre 2026) : le professeur choisi sur la page de bienvenue (`--c-choice`, `#34C77B`, contour et « Votre choix » sur fond bleu nuit, 6,6:1). Aucun autre vert sur le site, dans la scène 3D ni dans les icônes.

## Contrastes (niveau AA)

Seuils : 4,5:1 pour le texte courant, 3:1 pour le grand texte et les éléments d'interface. Valeurs calculées selon la formule WCAG 2.1 et contrôlées avec axe-core (aucune violation à 360 et 1280 pixels).

| Texte | Fond | Contraste | Verdict |
|---|---|---|---|
| blanc | bleu nuit `#0E2A5C` | 14,0:1 | conforme |
| bleu nuit `#0E2A5C` | fond `#F4F6FB` | 12,9:1 | conforme |
| bleu nuit `#0E2A5C` | orange `#FF7A1A` | 5,35:1 | conforme : **texte des boutons orange** |
| bleu nuit `#0E2A5C` | orange de survol `#FF8F3F` | 6,2:1 | conforme |
| blanc | orange `#FF7A1A` | 2,6:1 | **interdit** |
| orange foncé `#B25512` | fond `#F4F6FB` | 4,6:1 | conforme : seul orange permis pour du texte sur fond clair |
| orange foncé `#B25512` | blanc | 5,0:1 | conforme |
| `#8A3A00` | orange pâle `#FFEDE0` | 6,9:1 | conforme |
| texte `#14213D` | fond `#F4F6FB` | 14,8:1 | conforme |
| gris `#4E5A70` | fond `#F4F6FB` | 6,4:1 | conforme |
| `#C9D5EC` | bleu nuit | 9,5:1 | conforme |
| bordure de champ `#7C879B` | blanc | 3,6:1 | conforme (élément d'interface) |

Règles :

1. Les boutons orange portent **toujours** un texte bleu nuit, jamais blanc.
2. Un texte orange sur fond clair utilise `--c-accent-strong` (`#B25512`), jamais l'orange vif `#FF7A1A`.
3. L'orange vif sert aux fonds de boutons, aux pictogrammes et aux détails décoratifs.

## Police

- **Sora** (SIL Open Font License 1.1, The Sora Project Authors), une seule famille pour tout le site.
- Fichier `public/fonts/sora-2.000-latin.woff2` : police variable réduite aux graisses **400 à 700** et aux caractères latins avec les lettres et la ponctuation françaises (guillemets, œ, espace insécable ; l'espace fine insécable des prix, absente de Sora, utilise l'espace insécable de la police). Poids : **21 Ko**. Licence : `public/fonts/OFL-Sora.txt`, et LICENCES-TIERS.md.
- Graisses : 400 (texte), 600 (`--fw-bold`), 700 (`--fw-heavy`, titres et boutons).

## Logos

Les quatre logos sont les images fournies par la plateforme, **non redessinées**. Originaux dans `docs/brand/reference/`. Le script `tools/brand/prepare-logos.py` rend leur fond transparent, coupe les marges vides et produit les fichiers de `public/brand/` :

| Logo | Fichiers | Usage |
|---|---|---|
| Logo horizontal | `logo-horizontal.png`, `.webp`, `-640.webp` | **en-tête du site** (version 640 pixels), documents sur fond clair |
| Logo vertical | `logo-vertical.png`, `.webp`, `-640.webp` | espaces carrés : affiches, réseaux sociaux, écran de démarrage |
| Icône (bulle) | `icone.png`, `.webp`, `-640.webp` | source du favicon et des icônes de l'application |
| Logo blanc | `logo-blanc.png`, `.webp`, `-640.webp` | **pied de page** et tout fond bleu nuit |

Icônes de l'application, tirées de l'icône sur fond blanc : `public/favicon.ico` (16, 32, 48), `public/icons/favicon-96.png`, `icon-192.png`, `icon-512.png`, `icon-maskable-512.png` (bulle dans la zone de sécurité), `apple-touch-icon.png` (180, iPhone).

Le chemin des logos de l'en-tête et du pied de page est réglé dans `config/platform.php` (`brand.logo.header`, `brand.logo.footer`) ; le composant `<x-logo>` (variante `header` ou `footer`) les affiche avec le nom de la plateforme comme texte de remplacement.

Zone de protection : laisser autour du logo un espace libre au moins égal à la hauteur de la lettre « a » de « Talkami ». Taille minimale à l'écran : 28 pixels de haut pour le logo horizontal.

## Teacher Joe

Le personnage 3D et le visage SVG de secours portent les couleurs de la marque : **gilet bleu nuit `#0E2A5C`**, **cravate orange `#FF7A1A`**, chemise blanche. Le décor de la classe n'utilise pas de vert (tableau bleu nuit, pot à crayons orange).

## Interdits

- **Déformer le logo** : ne jamais l'étirer, l'écraser, le pencher, le recadrer ni lui ajouter d'ombre ou de contour. Toujours garder ses proportions (`width` et `height` d'origine, ou une seule des deux).
- **Changer les couleurs du logo** : ne jamais le recolorer. Sur fond clair : logo horizontal ou vertical ; sur fond bleu nuit : logo blanc. Ne pas le poser sur une photo ou un fond orange.
- **Texte blanc sur orange** : interdit (contraste 2,6:1). Sur orange, le texte est bleu nuit.
- Pas d'autre vert que celui du bouton WhatsApp et du professeur choisi sur la page de bienvenue, pas d'autre police que Sora.
