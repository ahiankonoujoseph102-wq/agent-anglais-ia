# EN LOCAL (Python + segno) : code QR du prospectus en SVG noir sur blanc, marge de 4 modules (norme).
# Correction d'erreur « Q » (25 %) : reste lisible malgré une photocopie pâle, une tache ou un pli.
# Modules dessinés en surfaces pleines jointives (aucun trait) : pas de fin liseré blanc entre deux modules à l'impression.
# Usage : python3 qr.py "https://talkami.net/?utm_source=prospectus"  → SVG sur la sortie standard
import sys
import segno

qr = segno.make(sys.argv[1], error='q', micro=False, boost_error=False)
border = 4
rows = [list(row) for row in qr.matrix]
size = len(rows) + 2 * border
parts = []
for y, row in enumerate(rows):
    x = 0
    while x < len(row):
        if row[x]:
            start = x
            while x < len(row) and row[x]:
                x += 1
            parts.append(f'M{start + border} {y + border}h{x - start}v1h-{x - start}z')
        else:
            x += 1
sys.stdout.write(
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {size} {size}">'
    f'<rect width="{size}" height="{size}" fill="#fff"/><path fill="#000" d="{"".join(parts)}"/></svg>'
)
sys.stderr.write(f'version {qr.version}, {len(rows)} modules\n')
