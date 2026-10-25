// Effets visuels des vidéos de présentation, injectés dans la page du site de DÉMONSTRATION pendant
// l'enregistrement seulement (jamais dans le site lui-même) : doigt et cercle de toucher, cercle et flèche orange,
// zoom doux, défilement doux (l'étiquette « Écran de démonstration » est ajoutée à côté du téléphone, par
// compose.mjs). Chargé par record.mjs (addInitScript).
(() => {
    // Chaque page de la vidéo part de zéro : jamais de « retour depuis l'application Mobile Money » simulé.
    try {
        sessionStorage.removeItem('pay-away');
    } catch {}

    const ORANGE = '#FF7A1A';
    const NIGHT = '#0E2A5C';
    let layer = null;
    let finger = null;
    let last = { x: 195, y: 600 };

    const ready = () => {
        if (layer && document.body.contains(layer)) {
            return layer;
        }

        layer = document.createElement('div');
        layer.id = 'fx-layer';
        layer.setAttribute('aria-hidden', 'true');
        layer.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:2147483647;';
        document.body.appendChild(layer);

        const style = document.createElement('style');
        style.textContent = `
            #fx-layer * { box-sizing: border-box; }
            .fx-finger { position: absolute; width: 34px; height: 34px; margin: -17px 0 0 -17px; border-radius: 50%;
                background: rgba(255,255,255,.55); border: 3px solid ${NIGHT}; box-shadow: 0 2px 10px rgba(14,42,92,.35);
                transition: left .55s cubic-bezier(.4,0,.2,1), top .55s cubic-bezier(.4,0,.2,1), opacity .3s; opacity: 0; }
            .fx-ripple { position: absolute; width: 26px; height: 26px; margin: -13px 0 0 -13px; border-radius: 50%;
                border: 4px solid ${ORANGE}; animation: fx-ripple .7s ease-out forwards; }
            @keyframes fx-ripple { from { transform: scale(.6); opacity: 1; } to { transform: scale(3.2); opacity: 0; } }
            .fx-circle { position: absolute; border: 5px solid ${ORANGE}; border-radius: 18px;
                box-shadow: 0 0 0 4px rgba(255,122,26,.25); animation: fx-in .35s ease-out; }
            .fx-arrow { position: absolute; width: 70px; height: 70px; animation: fx-in .35s ease-out, fx-bob 1s ease-in-out .35s infinite; }
            @keyframes fx-in { from { opacity: 0; transform: scale(.85); } to { opacity: 1; transform: none; } }
            @keyframes fx-bob { 50% { translate: 0 8px; } }
            html.fx-zoom { transition: transform .7s cubic-bezier(.4,0,.2,1); }
        `;
        document.head.appendChild(style);

        finger = document.createElement('div');
        finger.className = 'fx-finger';
        layer.appendChild(finger);

        return layer;
    };

    const box = (target) => {
        const element = typeof target === 'string' ? document.querySelector(target) : target;

        return element ? element.getBoundingClientRect() : null;
    };

    window.__fx = {
        ready,

        /** Le doigt glisse jusqu'au point, puis l'onde du toucher. */
        async tap(x, y) {
            ready();
            finger.style.opacity = '1';
            finger.style.left = `${x}px`;
            finger.style.top = `${y}px`;
            await new Promise((ok) => setTimeout(ok, 600));
            const ripple = document.createElement('div');
            ripple.className = 'fx-ripple';
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            layer.appendChild(ripple);
            setTimeout(() => ripple.remove(), 800);
            setTimeout(() => { if (last.x === x && last.y === y) finger.style.opacity = '0'; }, 1400);
            last = { x, y };
        },

        hideFinger() {
            if (finger) {
                finger.style.opacity = '0';
            }
        },

        placeFinger(x, y) {
            ready();
            finger.style.transition = 'none';
            finger.style.left = `${x ?? last.x}px`;
            finger.style.top = `${y ?? last.y}px`;
            finger.offsetWidth;
            finger.style.transition = '';
        },

        /** Cercle orange autour d'un élément (coordonnées de l'écran au moment de l'appel). */
        circle(target, pad = 8) {
            ready();
            const element = typeof target === 'string' ? document.querySelector(target) : target;

            if (!element) {
                return false;
            }

            const ring = document.createElement('div');
            ring.className = 'fx-circle fx-mark';
            layer.appendChild(ring);
            // Suit l'élément à chaque image (blocs qui finissent d'apparaître, défilement, zoom).
            const place = () => {
                if (!ring.isConnected) {
                    return;
                }

                const r = element.getBoundingClientRect();
                ring.style.left = `${r.left - pad}px`;
                ring.style.top = `${r.top - pad}px`;
                ring.style.width = `${r.width + pad * 2}px`;
                ring.style.height = `${r.height + pad * 2}px`;
                requestAnimationFrame(place);
            };
            place();

            return true;
        },

        /** Flèche orange qui pointe l'élément, du dessus (ou du dessous s'il est tout en haut). */
        arrow(target) {
            ready();
            const element = typeof target === 'string' ? document.querySelector(target) : target;

            if (!element) {
                return false;
            }

            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 70 70');
            svg.classList.add('fx-arrow', 'fx-mark');
            svg.innerHTML = `<path d="M35 4 V52 M15 34 L35 56 L55 34" fill="none" stroke="${ORANGE}" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" style="paint-order:stroke"/>`;
            layer.appendChild(svg);
            const place = () => {
                if (!svg.isConnected) {
                    return;
                }

                const r = element.getBoundingClientRect();
                const below = r.top < 110;
                // Vers la droite de l'élément : le début d'une ligne (titre, texte) reste lisible.
                const x = r.width > 200 ? r.left + r.width * 0.8 : r.left + r.width / 2;
                svg.style.left = `${Math.min(window.innerWidth - 74, Math.max(4, x - 35))}px`;
                svg.style.top = below ? `${r.bottom + 6}px` : `${r.top - 76}px`;
                svg.style.transform = below ? 'rotate(180deg)' : '';

                if (below) {
                    svg.style.animation = 'none';
                }

                requestAnimationFrame(place);
            };
            place();

            return true;
        },

        clearMarks() {
            document.querySelectorAll('.fx-mark').forEach((mark) => mark.remove());
        },

        /** Zoom doux sur un élément (toute la page agrandie autour de lui). */
        zoom(target, scale = 1.6) {
            const r = box(target);

            if (!r) {
                return false;
            }

            // Jamais plus large que l'écran : l'élément agrandi reste entier.
            scale = Math.max(1, Math.min(scale, (window.innerWidth - 16) / r.width, (window.innerHeight - 140) / r.height));
            // L'élément vient au centre de l'écran, puis grandit : il reste entier et lisible.
            const html = document.documentElement;
            html.classList.add('fx-zoom');
            const ex = r.left + r.width / 2;
            const ey = r.top + r.height / 2;
            html.style.transformOrigin = `${ex}px ${ey + window.scrollY}px`;
            html.style.transform = `translate(${window.innerWidth / 2 - ex}px, ${window.innerHeight * 0.45 - ey}px) scale(${scale})`;

            return true;
        },

        unzoom() {
            document.documentElement.style.transform = '';
        },

        /** Défilement doux, en « ms » millisecondes, jusqu'à ce que l'élément soit à « offset » pixels du haut. */
        scrollTo(target, ms = 900, offset = 90) {
            const r = box(target);

            if (!r) {
                return Promise.resolve(false);
            }

            const from = window.scrollY;
            const to = Math.max(0, Math.min(document.documentElement.scrollHeight - window.innerHeight, from + r.top - offset));
            const start = performance.now();

            return new Promise((ok) => {
                const step = (now) => {
                    const p = Math.min(1, (now - start) / ms);
                    const eased = p < 0.5 ? 2 * p * p : 1 - (-2 * p + 2) ** 2 / 2;
                    window.scrollTo(0, from + (to - from) * eased);

                    if (p < 1) {
                        requestAnimationFrame(step);
                    } else {
                        ok(true);
                    }
                };
                requestAnimationFrame(step);
            });
        },

        scrollBy(dy, ms = 900) {
            const from = window.scrollY;
            const start = performance.now();

            return new Promise((ok) => {
                const step = (now) => {
                    const p = Math.min(1, (now - start) / ms);
                    const eased = p < 0.5 ? 2 * p * p : 1 - (-2 * p + 2) ** 2 / 2;
                    window.scrollTo(0, from + dy * eased);
                    p < 1 ? requestAnimationFrame(step) : ok(true);
                };
                requestAnimationFrame(step);
            });
        },
    };

    // Sur le site de démonstration : ni bandeau d'installation, ni messages passagers, ni choix du micro (le nom du
    // micro simulé n'a rien à faire dans une vidéo).
    const hide = document.createElement('style');
    hide.textContent = '#install, #mic-choice, .toast { display: none !important; }';
    document.addEventListener('DOMContentLoaded', () => {
        document.head.appendChild(hide);
        ready();
    });
})();
