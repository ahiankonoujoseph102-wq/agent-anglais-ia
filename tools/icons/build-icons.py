"""Génère resources/views/components/icon.blade.php à partir des icônes Lucide.

À lancer EN LOCAL (jamais sur le serveur) après avoir téléchargé les SVG de la version
figée ci-dessous, par exemple :
    for n in mic …; do curl -o svg/$n.svg https://cdn.jsdelivr.net/npm/lucide-static@1.49.0/icons/$n.svg; done
    python3 tools/icons/build-icons.py chemin/vers/svg

Lucide : https://lucide.dev, licence ISC (usage commercial permis), copie dans
resources/icons/LICENSE-lucide.txt et dans LICENCES-TIERS.md.
"""

import pathlib
import re
import sys

LUCIDE_VERSION = '1.49.0'

# Nom utilisé dans les vues (<x-icon name="…">) => nom de l'icône Lucide.
ICONS = {
    'bubble': 'message-circle',
    'messages': 'messages-square',
    'mic': 'mic',
    'mic-off': 'mic-off',
    'headphones': 'headphones',
    'level': 'chart-no-axes-column-increasing',
    'chart': 'chart-line',
    'trending': 'trending-up',
    'calendar': 'calendar-days',
    'calendar-check': 'calendar-check',
    'calendar-clock': 'calendar-clock',
    'clock': 'clock',
    'hourglass': 'hourglass',
    'award': 'award',
    'badge-check': 'badge-check',
    'mobile': 'smartphone',
    'money': 'banknote',
    'wallet': 'wallet',
    'gift': 'gift',
    'check': 'check',
    'check-circle': 'circle-check',
    'list-checks': 'list-checks',
    'arrow-right': 'arrow-right',
    'arrow-left': 'arrow-left',
    'chevron-right': 'chevron-right',
    'chevron-down': 'chevron-down',
    'user': 'user',
    'user-plus': 'user-plus',
    'user-round': 'circle-user-round',
    'lock': 'lock',
    'home': 'house',
    'book': 'book-open',
    'school': 'graduation-cap',
    'work': 'briefcase-business',
    'languages': 'languages',
    'list': 'list',
    'mail': 'mail',
    'phone': 'phone',
    'pin': 'map-pin',
    'globe': 'globe',
    'download': 'download',
    'upload': 'upload',
    'wifi': 'wifi',
    'wifi-off': 'wifi-off',
    'refresh': 'refresh-cw',
    'share': 'share',
    'plus-square': 'square-plus',
    'close': 'x',
    'close-circle': 'circle-x',
    'menu': 'menu',
    'alert': 'triangle-alert',
    'info': 'info',
    'help': 'circle-help',
    'play': 'play',
    'pause': 'pause',
    'logout': 'log-out',
    'login': 'log-in',
    'target': 'target',
    'sparkle': 'sparkles',
    'copy': 'copy',
    'shield': 'shield-check',
    'fire': 'flame',
    'edit': 'pencil',
    'hand': 'hand',
    'eye': 'eye',
    'eye-off': 'eye-off',
    'settings': 'settings',
    'users': 'users',
    'user-off': 'user-x',
    'palette': 'palette',
    'history': 'history',
}

# Lucide ne dessine aucune marque : le logo WhatsApp (bulle et combiné) reste dessiné pour le site,
# au même trait que les icônes Lucide.
WHATSAPP = '<path d="M20.5 12a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.2-4.2A8.5 8.5 0 1 1 20.5 12z"/><path d="M9 8.5c0 3.6 2.9 6.5 6.5 6.5l1-1.6-2-1-1 1a4 4 0 0 1-2.4-2.4l1-1-1-2z"/>'

TEMPLATE = """{{{{--
    Icônes SVG intégrées : famille Lucide {version} (https://lucide.dev, licence ISC, usage commercial permis ;
    licence dans resources/icons/LICENSE-lucide.txt et LICENCES-TIERS.md). Grille de 24 × 24, trait arrondi.
    FICHIER GÉNÉRÉ par tools/icons/build-icons.py : ajouter une icône là-bas, pas ici.
    Utilisation : <x-icon name="mic" /> ; avec label="…" l'icône est annoncée aux lecteurs d'écran.
    Taille et couleur : CSS (.icon, pastilles .icon-bubble) ; épaisseur du trait : 2, 2,25 dans les pastilles.
    Classe d : forme fermée, remplie en orange clair dans le style « duotone » (config platform.brand.icon_style).
--}}}}
@props(['name', 'label' => null])

@php
    $paths = [
{entries}
    ];
@endphp

<svg {{{{ $attributes->class(['icon']) }}}} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     @if ($label) role="img" aria-label="{{{{ $label }}}}" @else aria-hidden="true" @endif focusable="false">{{!! $paths[$name] ?? $paths['bubble'] !!}}</svg>
"""


def fillable(element: str) -> bool:
    """Forme fermée : remplie en orange clair dans le style « duotone » (classe d)."""
    if re.match(r'<(rect|circle|ellipse|polygon)\b', element):
        return True
    data = re.search(r' d="([^"]*)"', element)
    return bool(data) and data.group(1).strip().lower().endswith('z')


def inner(svg: str) -> str:
    body = re.search(r'<svg[^>]*>(.*)</svg>', svg, re.S).group(1)
    body = re.sub(r'<!--.*?-->', '', body, flags=re.S)
    parts = []
    for part in re.findall(r'<[^>]+/>', body):
        part = re.sub(r'\s+', ' ', part).replace(' />', '/>').strip()
        if fillable(part):
            part = re.sub(r'^<(\w+)', r'<\1 class="d"', part)
        parts.append(part)
    # Formes remplies d'abord : leur remplissage ne cache jamais un trait de l'icône.
    return ''.join(sorted(parts, key=lambda part: 'class="d"' not in part))


def main() -> None:
    folder = pathlib.Path(sys.argv[1])
    entries = []
    for name, lucide in ICONS.items():
        markup = inner((folder / f'{lucide}.svg').read_text())
        entries.append(f"        '{name}' => '{markup}',")
    entries.append(f"        'whatsapp' => '{WHATSAPP}',")
    out = pathlib.Path(__file__).resolve().parents[2] / 'resources/views/components/icon.blade.php'
    out.write_text(TEMPLATE.format(version=LUCIDE_VERSION, entries='\n'.join(entries)))
    print(f'{len(entries)} icônes écrites dans {out}')


if __name__ == '__main__':
    main()
