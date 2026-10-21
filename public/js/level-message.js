// Page de résultat de l'évaluation : message vocal de Teacher Joe. Il est préparé dès le verdict ; prêt, il est
// chargé en arrière-plan et part au premier toucher sur « Écouter ». Pas encore prêt : le texte s'affiche tout de
// suite, Teacher Joe fait un léger geste avec « Un instant… », et le message démarre tout seul dès qu'il est prêt.
// Jamais de lecture avant le premier toucher.
const box = document.querySelector('[data-level-message]');

if (box) {
    const { ReadyWatch, silentWav } = await import(box.dataset.voiceWait);
    const wait = JSON.parse(box.dataset.wait || '{}');
    const button = box.querySelector('[data-level-play]');
    const audio = box.querySelector('[data-level-audio]');
    const status = box.querySelector('[data-level-status]');
    const playLabel = box.querySelector('[data-play-label]');
    const pauseLabel = box.querySelector('[data-pause-label]');
    const text = document.querySelector('[data-level-text]');
    const teacher = document.querySelector('.result-teacher');

    let ready = false;
    let wanted = false;
    let silence = null;

    const show = (playing) => {
        button.setAttribute('aria-pressed', String(playing));
        playLabel.hidden = playing;
        pauseLabel.hidden = !playing;
    };
    const say = (message) => {
        status.textContent = message;
        status.hidden = !message;
    };
    const waiting = (on) => teacher?.toggleAttribute('data-waiting', on);

    const load = () => {
        if (audio.dataset.loaded) {
            return;
        }

        audio.dataset.loaded = '1';
        audio.preload = 'auto';
        audio.src = audio.dataset.src;
        audio.load();
    };

    const play = () => {
        load();
        audio.play().catch(() => {});
    };

    const makeWatch = () => new ReadyWatch({
        check: async () => {
            const response = await fetch(box.dataset.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            return response.ok && (await response.json()).ready === true;
        },
        pollMs: (wait.poll_seconds ?? 3) * 1000,
        lateMs: (wait.late_seconds ?? 30) * 1000,
        giveUpMs: (wait.give_up_seconds ?? 180) * 1000,
        onReady() {
            ready = true;

            if (wanted) {
                waiting(false);
                say('');
                play();
            } else {
                load();
            }
        },
        onLate() {
            if (wanted) {
                say(wait.late);
            }
        },
        onGiveUp() {
            waiting(false);

            if (wanted) {
                say(wait.unavailable);
            }
        },
    });
    let watch = makeWatch();

    audio.addEventListener('playing', () => {
        if (audio.dataset.loaded) {
            say('');
            waiting(false);
            show(true);
        }
    });
    audio.addEventListener('pause', () => show(false));
    audio.addEventListener('ended', () => show(false));
    audio.addEventListener('error', () => {
        if (!audio.dataset.loaded) {
            return;
        }

        show(false);
        waiting(false);
        say(wait.unavailable);
    });

    button.addEventListener('click', () => {
        if (!audio.paused && audio.dataset.loaded) {
            audio.pause();

            return;
        }

        if (ready) {
            play();

            return;
        }

        // Pas encore prêt : le texte tout de suite, « Un instant… » et un léger geste ; il démarrera tout seul.
        wanted = true;
        say(wait.instant);
        waiting(true);

        if (text) {
            text.open = true;
        }

        // Le toucher « déverrouille » le lecteur avec un court silence (lecture automatique permise ensuite).
        if (!silence) {
            silence = URL.createObjectURL(new Blob([silentWav()], { type: 'audio/wav' }));
            audio.src = silence;
            audio.play().catch(() => {});
        }

        // La page attendait déjà depuis longtemps sans réponse : on redemande.
        if (watch.done) {
            watch = makeWatch();
        }

        watch.start();
    });

    // Dès l'arrivée sur la page : prêt, il est chargé en arrière-plan ; sinon, la page attend qu'il le soit.
    watch.start();
}
