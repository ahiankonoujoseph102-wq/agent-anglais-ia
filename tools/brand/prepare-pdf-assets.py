"""
EN LOCAL (Python, fontTools, brotli, Pillow) : fichiers du PDF des documents de révision (resources/pdf).

- sora-regular.ttf et sora-bold.ttf : Sora (SIL Open Font License 1.1, voir public/fonts/OFL-Sora.txt) tirée de
  la police du site (public/fonts/sora-2.000-latin.woff2, variable, réduite aux caractères français) et figée en
  graisse 400 et 700 : dompdf ne lit pas le woff2 ni les polices variables.
- logo-blanc.png et logo-horizontal.png : logos de public/brand réduits à 480 pixels (le PDF reste léger).

    pip install fonttools brotli pillow
    python tools/brand/prepare-pdf-assets.py
"""
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "resources" / "pdf"
OUT.mkdir(parents=True, exist_ok=True)

for name, weight in (("regular", 400), ("bold", 700)):
    font = instancer.instantiateVariableFont(TTFont(ROOT / "public" / "fonts" / "sora-2.000-latin.woff2"), {"wght": weight})
    font.flavor = None
    font.save(OUT / f"sora-{name}.ttf")

for logo in ("logo-blanc", "logo-horizontal"):
    image = Image.open(ROOT / "public" / "brand" / f"{logo}.png")
    image.thumbnail((480, 480))
    image.save(OUT / f"{logo}.png", optimize=True)
