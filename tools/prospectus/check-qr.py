# EN LOCAL (Python : zxing-cpp, le lecteur de code QR libre de nombreuses applications Android, Pillow, numpy ;
# pdftoppm de Poppler) : vérifie que le code QR du prospectus se lit
# encore après une impression de mauvaise qualité. La feuille de quatre est rendue à plusieurs résolutions, puis
# abîmée comme une photocopie (flou, encre trop pâle ou trop forte, bruit, compression JPEG, réduction à 70 %) ;
# chaque code QR doit donner exactement l'adresse attendue.
# Usage : python3 check-qr.py docs/prospectus/prospectus-4-par-a4.pdf "https://talkami.net/?utm_source=prospectus"
import io, subprocess, sys, tempfile
from pathlib import Path

import numpy as np
import zxingcpp
from PIL import Image, ImageFilter

pdf, expected = sys.argv[1], sys.argv[2]
def decode(img: Image.Image) -> list[str]:
    return [r.text for r in zxingcpp.read_barcodes(img.convert('L'), formats=zxingcpp.BarcodeFormat.QRCode)]

def damage(img, kind, dpi):
    if kind == 'net':
        return img
    if kind == 'flou':
        return img.filter(ImageFilter.GaussianBlur(1.2))
    if kind == 'pâle':  # photocopieuse qui manque d'encre : noir devenu gris clair
        return img.point(lambda v: 255 - (255 - v) * 0.45)
    if kind == 'trop d\'encre':  # bavure d'environ 0,1 millimètre : les modules noirs grossissent
        blurred = img.filter(ImageFilter.GaussianBlur(0.1 / 25.4 * dpi * 1.5))
        return blurred.point(lambda v: 0 if v < 175 else 255)
    if kind == 'penché':  # photo prise de travers
        return img.rotate(12, resample=Image.BICUBIC, expand=True, fillcolor=255)
    if kind == 'bruit':
        arr = np.array(img).astype(np.int16) + np.random.default_rng(1).normal(0, 40, np.array(img).shape).astype(np.int16)
        return Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8))
    if kind == 'jpeg':
        buf = io.BytesIO(); img.save(buf, 'JPEG', quality=25); return Image.open(buf).convert('L')
    if kind == 'réduit à 70 %':  # « ajuster à la page » ou photocopie réduite
        return img.resize((int(img.width * 0.7), int(img.height * 0.7)), Image.BILINEAR)
    raise ValueError(kind)

failures = 0
with tempfile.TemporaryDirectory() as tmp:
    for dpi in (300, 150, 100):
        subprocess.run(['pdftoppm', '-r', str(dpi), '-gray', '-png', pdf, f'{tmp}/p{dpi}'], check=True)
        page = Image.open(next(Path(tmp).glob(f'p{dpi}*.png'))).convert('L')
        w, h = page.size
        cells = [page.crop((x, y, x + w // 2, y + h // 2)) for y in (0, h // 2) for x in (0, w // 2)]
        for kind in ('net', 'flou', 'pâle', 'trop d\'encre', 'bruit', 'jpeg', 'réduit à 70 %', 'penché'):
            found = [decode(damage(cell, kind, dpi)) for cell in cells]
            good = sum(1 for texts in found if texts == [expected])
            status = 'lu' if good == 4 else 'ÉCHEC'
            failures += good != 4
            print(f'{dpi:>3} points par pouce, {kind:<14} : {good}/4 {status}')
print('Tous les codes QR se lisent.' if failures == 0 else f'{failures} essai(s) en échec.')
sys.exit(1 if failures else 0)
