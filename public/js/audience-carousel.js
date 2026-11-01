/*
 * Accueil, cartes des publics (étudiant, élève, parent, professionnel) sur téléphone.
 *
 * Avant : il fallait deviner qu'on pouvait faire glisser les cartes. Maintenant, quand le bloc est à l'écran, les
 * cartes défilent toutes seules, une après l'autre (défilement natif et doux : la page reste fluide) ; quatre onglets
 * montrent la carte en cours, avec une barre orange qui se remplit (seulement transform), et le bouton sous les
 * cartes suit la carte affichée (texte et adresse de son bouton, en fondu).
 *
 * Arrêt pour de bon au premier toucher, à la molette, au clavier ou au focus : le carrousel reste utilisable à la
 * main (glisser, toucher un onglet). Pause quand le bloc sort de l'écran ou que l'onglet du navigateur est caché.
 * « Réduire les animations » : jamais de défilement automatique (onglets et bouton marchent quand même).
 * À partir de 720 pixels : rien (les cartes sont en grille, chacune avec son bouton).
 */

/** Carte la plus proche de la position de défilement (la dernière quand on est tout au bout). */
export function nearestIndex(lefts, scrollLeft, maxScroll) {
    if (!lefts.length) {
        return 0;
    }

    if (maxScroll > 0 && scrollLeft >= maxScroll - 2) {
        return lefts.length - 1;
    }

    let best = 0;

    lefts.forEach((left, index) => {
        if (Math.abs(left - scrollLeft) < Math.abs(lefts[best] - scrollLeft)) {
            best = index;
        }
    });

    return best;
}

/** Carte suivante, en revenant à la première après la dernière. */
export function nextIndex(index, count) {
    return count > 0 ? (index + 1) % count : 0;
}

export function setup(root, win = window) {
    const track = root.querySelector('[data-audience-track]');
    const tabs = [...root.querySelectorAll('[data-audience-tab]')];
    const follow = root.querySelector('[data-audience-follow]');
    const cards = track ? [...track.children] : [];

    if (!track || !follow || cards.length < 2 || tabs.length !== cards.length) {
        return null;
    }

    const phone = win.matchMedia('(max-width: 719.98px)');
    const reduced = win.matchMedia('(prefers-reduced-motion: reduce)');
    const state = { index: -1, stopped: reduced.matches, visible: false, frame: 0, swap: 0, until: 0 };

    root.classList.add('is-ready');

    if (state.stopped) {
        root.classList.add('is-stopped');
    }

    function lefts() {
        const origin = track.getBoundingClientRect().left - track.scrollLeft;

        return cards.map((card) => card.getBoundingClientRect().left - origin);
    }

    function updatePaused() {
        const playing = !state.stopped && state.visible && phone.matches && !win.document.hidden;
        root.classList.toggle('is-paused', !playing);
    }

    function setFollow(card) {
        const cta = card.querySelector('.audience-cta');
        const inner = follow.querySelector('.audience-follow-inner');

        if (!cta || !inner || follow.getAttribute('href') === cta.getAttribute('href') && inner.textContent.trim() === cta.textContent.trim()) {
            return;
        }

        win.clearTimeout(state.swap);
        follow.classList.add('is-swapping');
        state.swap = win.setTimeout(() => {
            follow.setAttribute('href', cta.getAttribute('href'));
            inner.innerHTML = cta.innerHTML;
            follow.classList.remove('is-swapping');
        }, reduced.matches ? 0 : 160);
    }

    function activate(index) {
        if (index === state.index) {
            return;
        }

        state.index = index;
        tabs.forEach((tab, i) => {
            const active = i === index;
            tab.classList.toggle('is-active', active);

            if (active) {
                tab.setAttribute('aria-current', 'true');
                // Relance la barre qui se remplit (animation CSS) depuis le début.
                const bar = tab.querySelector('.audience-tab-bar');

                if (bar) {
                    bar.style.animation = 'none';
                    void bar.offsetWidth;
                    bar.style.animation = '';
                }
            } else {
                tab.removeAttribute('aria-current');
            }
        });
        setFollow(cards[index]);
    }

    function scrollToCard(index) {
        const left = lefts()[index] ?? 0;
        // Pendant un défilement lancé par le carrousel, les positions intermédiaires ne changent pas la carte en cours
        // (sinon, en revenant de la dernière à la première, onglets et bouton clignoteraient).
        state.until = Date.now() + 1000;
        track.scrollTo({ left, behavior: reduced.matches ? 'auto' : 'smooth' });
        activate(index);
    }

    function stop() {
        if (state.stopped) {
            return;
        }

        state.stopped = true;
        state.until = 0;
        root.classList.add('is-stopped');
        updatePaused();
    }

    track.addEventListener('scroll', () => {
        win.cancelAnimationFrame(state.frame);
        state.frame = win.requestAnimationFrame(() => {
            if (Date.now() < state.until) {
                return;
            }

            activate(nearestIndex(lefts(), track.scrollLeft, track.scrollWidth - track.clientWidth));
        });
    }, { passive: true });

    ['pointerdown', 'touchstart', 'wheel', 'keydown', 'focusin'].forEach((type) => {
        root.addEventListener(type, (event) => {
            // Le bouton qui suit les cartes n'arrête rien : il mène à sa page.
            if (!follow.contains(event.target)) {
                stop();
            }
        }, { passive: true });
    });

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', (event) => {
            if (!phone.matches) {
                return;
            }

            event.preventDefault();
            stop();
            scrollToCard(index);
        });

        // Fin de la barre qui se remplit : carte suivante.
        tab.querySelector('.audience-tab-bar')?.addEventListener('animationend', () => {
            if (index === state.index && !state.stopped && phone.matches) {
                scrollToCard(nextIndex(index, cards.length));
            }
        });
    });

    if ('IntersectionObserver' in win) {
        new win.IntersectionObserver((entries) => {
            state.visible = entries.some((entry) => entry.isIntersecting);
            updatePaused();
        }, { threshold: 0.35 }).observe(track);
    } else {
        stop();
    }

    win.document.addEventListener('visibilitychange', updatePaused);
    phone.addEventListener?.('change', updatePaused);
    reduced.addEventListener?.('change', () => reduced.matches && stop());

    activate(0);
    updatePaused();

    return { stop, activate, scrollToCard, state };
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-audience-carousel]').forEach((root) => setup(root));
}
