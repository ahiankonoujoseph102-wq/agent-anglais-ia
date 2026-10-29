"""
Logos officiels Talkami : prépare les images de docs/brand/reference/ pour le site.

Pour chaque image (logo-horizontal, logo-vertical, icone, logo-blanc) :
  - fond rendu transparent (couleur du fond retirée progressivement, les bords
    adoucis gardent leur transparence partielle) ;
  - marges vides coupées (une petite marge régulière est gardée) ;
  - enregistrement dans public/brand/ en PNG et en WebP (taille d'origine), plus
    une version WebP de 640 pixels de large pour l'affichage sur le site.
À partir de « icone » : favicon (16, 32, 48 pixels) et icônes de l'application
(192, 512, adaptative 512 sur fond blanc, iPhone 180).

Utilisation (en local) : python3 tools/brand/prepare-logos.py  (Pillow et numpy)
"""

from pathlib import Path

import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'docs/brand/reference'
BRAND = ROOT / 'public/brand'
ICONS = ROOT / 'public/icons'
NAMES = ['logo-horizontal', 'logo-vertical', 'icone', 'logo-blanc']


def background(rgb):
    """Couleur du fond : médiane des quatre coins."""
    h, w, _ = rgb.shape
    corners = np.concatenate([rgb[:20, :20], rgb[:20, -20:], rgb[-20:, :20], rgb[-20:, -20:]]).reshape(-1, 3)
    return np.median(corners, axis=0)


def remove_background(image, low=18.0, high=70.0):
    """Retire la couleur du fond : transparent sous `low` (grain, dégradés légers du fond),
    opaque au-delà de `high`, bords adoucis entre les deux (couleur corrigée du fond)."""
    rgb = np.asarray(image.convert('RGB')).astype(np.float64)
    bg = background(rgb)
    distance = np.sqrt(((rgb - bg) ** 2).sum(axis=2))
    alpha = np.clip((distance - low) / (high - low), 0, 1)
    safe = np.where(alpha > 0, alpha, 1)[..., None]
    color = np.clip((rgb - (1 - alpha[..., None]) * bg) / safe, 0, 255)
    rgba = np.dstack([color, alpha * 255]).round().astype(np.uint8)

    return Image.fromarray(rgba, 'RGBA')


def trim(image, margin_ratio=0.02):
    """Coupe les marges vides et garde une petite marge régulière."""
    alpha = np.asarray(image)[..., 3]
    ys, xs = np.nonzero(alpha > 8)
    margin = int(max(image.size) * margin_ratio)
    box = (max(0, xs.min() - margin), max(0, ys.min() - margin),
           min(image.width, xs.max() + 1 + margin), min(image.height, ys.max() + 1 + margin))

    return image.crop(box)


def on_white(icon, size, ratio):
    """Icône carrée : bulle centrée sur fond blanc, occupant `ratio` de la largeur."""
    canvas = Image.new('RGBA', (size, size), (255, 255, 255, 255))
    bubble = icon.copy()
    bubble.thumbnail((int(size * ratio), int(size * ratio)), Image.LANCZOS)
    canvas.alpha_composite(bubble, ((size - bubble.width) // 2, (size - bubble.height) // 2))

    return canvas.convert('RGB')


def favicons(icon):
    """Icônes du site (onglet, résultats de Google) : la bulle sur fond blanc UNI, carré, tailles multiples de
    48 pixels (règles de Google : 48 × 48 au moins, idéalement 192 × 192 ; un fond transparent devient un globe
    gris ou un fond noir chez certains). favicon.ico garde aussi 16 et 32 pixels pour les anciens navigateurs."""
    for size in (48, 96, 192):
        on_white(icon, size, 0.86).save(ICONS / f'favicon-{size}.png', optimize=True)
    on_white(icon, 48, 0.86).save(ROOT / 'public/favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])


def main():
    BRAND.mkdir(parents=True, exist_ok=True)
    prepared = {}

    for name in NAMES:
        image = trim(remove_background(Image.open(SOURCE / f'{name}.png')))
        prepared[name] = image
        image.save(BRAND / f'{name}.png', optimize=True)
        image.save(BRAND / f'{name}.webp', quality=90, method=6)
        site = image.copy()
        site.thumbnail((640, 640), Image.LANCZOS)
        site.save(BRAND / f'{name}-640.webp', quality=88, method=6)
        # Version légère pour l'en-tête et le pied de page (affichés à environ 130 pixels de large).
        small = site.copy()
        small.thumbnail((320, 320), Image.LANCZOS)
        small.save(BRAND / f'{name}-320.webp', quality=90, method=6)
        print(f'{name} : {image.width}×{image.height}')

    icon = prepared['icone']
    # Icônes de l'application : fond blanc ; l'icône adaptative garde la bulle dans la zone de sécurité (80 %).
    on_white(icon, 192, 0.78).save(ICONS / 'icon-192.png', optimize=True)
    on_white(icon, 512, 0.78).save(ICONS / 'icon-512.png', optimize=True)
    on_white(icon, 512, 0.62).save(ICONS / 'icon-maskable-512.png', optimize=True)
    on_white(icon, 180, 0.74).save(ICONS / 'apple-touch-icon.png', optimize=True)
    favicons(icon)
    print('Icônes et favicon écrits.')


if __name__ == '__main__':
    main()
