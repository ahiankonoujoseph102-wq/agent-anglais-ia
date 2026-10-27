# EN LOCAL (Python + Pillow) : logo Talkami en NOIR seul pour le prospectus (impression noir et blanc, photocopie).
# Bleu nuit et orange deviennent noirs ; le sourire orange DANS la bulle devient blanc (découpé), pour rester lisible.
# Usage : python3 tools/prospectus/logo-noir.py  → tools/prospectus/logo-noir.png
from pathlib import Path
from PIL import Image

here = Path(__file__).resolve().parent
source = Image.open(here / '../../public/brand/logo-horizontal.png').convert('RGBA')
width, height = source.size
bubble_right = int(width * 0.28)  # la bulle occupe le premier quart du logo horizontal

out = Image.new('RGBA', source.size, (255, 255, 255, 0))
src, dst = source.load(), out.load()
for y in range(height):
    for x in range(width):
        r, g, b, a = src[x, y]
        if a == 0:
            continue
        orange = max(0.0, min(1.0, (r - b) / 200))
        if x < bubble_right:
            v = int(255 * orange)  # sourire : blanc dans la bulle noire
            dst[x, y] = (v, v, v, a)
        else:
            dst[x, y] = (0, 0, 0, a)
out.save(here / 'logo-noir.png', optimize=True)
print('logo-noir.png', out.size)
