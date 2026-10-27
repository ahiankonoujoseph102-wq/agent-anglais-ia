"""EN LOCAL (Python + Pillow) : images de la fiche Google (Google Business Profile) de Talkami, dans docs/fiche-google/.

Sources : les logos fournis (public/brand), les rendus de Teacher Joe et les VRAIES captures du site de démonstration
produits par tools/presentation/google-profile.mjs (comptes fictifs, faux fournisseur). Aucun texte ajouté sur les
images : seulement le logo. Aucune mention d'intelligence artificielle, aucun chiffre ni avis inventé.

Usage : python3 tools/brand/google-profile.py dossier-des-captures
"""

import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parents[2]
SRC = Path(sys.argv[1])
OUT = ROOT / 'docs/fiche-google'
OUT.mkdir(parents=True, exist_ok=True)

NAVY = (14, 42, 92)
NAVY_DEEP = (8, 26, 60)
ORANGE = (255, 122, 26)
BEZEL = (10, 20, 40)


def brand(name):
    return Image.open(ROOT / 'public/brand' / f'{name}.png').convert('RGBA')


def fit(image, width=None, height=None):
    w, h = image.size
    scale = (width / w) if width else (height / h)

    return image.resize((round(w * scale), round(h * scale)), Image.LANCZOS)


def save(image, name):
    path = OUT / f'{name}.jpg'
    image.convert('RGB').save(path, quality=90, optimize=True, progressive=True)
    print(name, image.size, path.stat().st_size // 1024, 'Ko')


def background(size):
    """Fond bleu nuit en dégradé vertical, avec un halo orange discret derrière le téléphone."""
    w, h = size
    top, bottom = NAVY, NAVY_DEEP
    image = Image.new('RGB', size)
    pixels = ImageDraw.Draw(image)
    for y in range(h):
        t = y / max(1, h - 1)
        pixels.line([(0, y), (w, y)], fill=tuple(round(top[i] + (bottom[i] - top[i]) * t) for i in range(3)))

    glow = Image.new('L', size, 0)
    ImageDraw.Draw(glow).ellipse([w * 0.18, h * 0.22, w * 0.82, h * 0.98], fill=110)
    glow = glow.filter(ImageFilter.GaussianBlur(min(size) * 0.16))
    image.paste(Image.new('RGB', size, ORANGE), (0, 0), glow.point(lambda v: v * 0.55))

    return image.convert('RGBA')


def phone(screen, height):
    """Capture d'écran dans un cadre de téléphone (bord sombre, coins arrondis, ombre)."""
    border = round(height * 0.02)
    inner_h = height - 2 * border
    shot = fit(screen.convert('RGBA'), height=inner_h)
    inner_w = shot.size[0]
    w = inner_w + 2 * border
    radius = round(w * 0.13)

    body = Image.new('RGBA', (w, height), (0, 0, 0, 0))
    ImageDraw.Draw(body).rounded_rectangle([0, 0, w - 1, height - 1], radius=radius, fill=BEZEL)
    mask = Image.new('L', shot.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, inner_w - 1, inner_h - 1], radius=radius - border, fill=255)
    body.paste(shot, (border, border), mask)

    pad = round(height * 0.06)
    shadowed = Image.new('RGBA', (w + 2 * pad, height + 2 * pad), (0, 0, 0, 0))
    shadow = Image.new('L', shadowed.size, 0)
    ImageDraw.Draw(shadow).rounded_rectangle([pad, pad + pad * 0.35, pad + w, pad + height + pad * 0.35], radius=radius, fill=150)
    shadowed.paste(Image.new('RGBA', shadowed.size, (0, 0, 0, 255)), (0, 0), shadow.filter(ImageFilter.GaussianBlur(pad * 0.45)))
    shadowed.alpha_composite(body, (pad, pad))

    return shadowed, pad


def with_logo(image):
    """Logo blanc, petit, en haut à gauche : la seule écriture ajoutée."""
    logo = fit(brand('logo-blanc'), width=round(image.size[0] * 0.2))
    margin = round(image.size[0] * 0.045)
    image.alpha_composite(logo, (margin, margin))

    return image


def phones_image(names, size=(1080, 1080)):
    image = background(size)
    w, h = size
    height = round(h * (0.84 if len(names) == 1 else 0.8))
    frames = [phone(Image.open(SRC / f'{n}.png'), height) for n in names]
    gap = round(w * 0.04)
    total = sum(f.size[0] - 2 * pad for f, pad in frames) + gap * (len(frames) - 1)
    x = (w - total) // 2
    y = h - height - round(h * 0.035)
    for frame, pad in frames:
        image.alpha_composite(frame, (x - pad, y - pad))
        x += frame.size[0] - 2 * pad + gap

    return with_logo(image)


# 1. Logo : carré 720 × 720, fond blanc uni, l'icône (bulle) seule, lisible en tout petit.
logo = Image.new('RGBA', (720, 720), (255, 255, 255, 255))
icon = fit(brand('icone'), width=520)
logo.alpha_composite(icon, ((720 - icon.size[0]) // 2, (720 - icon.size[1]) // 2 + 8))
save(logo, '01-logo-720x720')

# 2. Couverture 1024 × 576 : la salle de classe de Teacher Joe ; le logo blanc remplace « Hello! Let's talk! » au tableau.
cover = Image.open(SRC / 'teacher-couverture.png').convert('RGBA')
sx = cover.size[0] / 960
board = [round(v * sx) for v in (98, 44, 515, 243)]  # intérieur du tableau, mesuré sur l'image réduite à 960 × 540
chalk = cover.getpixel((board[0] + 12, board[1] + 12))
ImageDraw.Draw(cover).rectangle(board, fill=chalk)
mark = fit(brand('logo-blanc'), width=round((board[2] - board[0]) * 0.74))
cover.alpha_composite(mark, ((board[0] + board[2] - mark.size[0]) // 2, (board[1] + board[3] - mark.size[1]) // 2))
save(cover.resize((1024, 576), Image.LANCZOS), '02-couverture-1024x576')

# 3. Photos.
save(Image.open(SRC / 'teacher-classe.png').convert('RGBA'), '03-photo-teacher-joe')
save(phones_image(['ecran-accueil']), '04-photo-accueil')
save(phones_image(['ecran-evaluation']), '05-photo-evaluation-gratuite')
save(phones_image(['ecran-seance', 'ecran-tableau'], (1200, 900)), '06-photo-seance-orale')
save(phones_image(['ecran-resultat']), '07-photo-resultat-evaluation')
save(phones_image(['ecran-formules']), '08-photo-formules')
save(phones_image(['ecran-mon-espace']), '09-photo-mon-espace')
