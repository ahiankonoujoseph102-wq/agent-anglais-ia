/*
 * Choix entre le professeur 3D et le visage SVG sur l'écran de conversation.
 *
 * Le professeur 3D est affiché PAR DÉFAUT. Le visage SVG reste visible le
 * temps du chargement en arrière-plan (barre de progression) : la page et le
 * bouton « Commencer » sont utilisables tout de suite. Repli silencieux sur le
 * visage SVG si WebGL 2 manque, si le chargement échoue ou dure plus de
 * LOAD_TIMEOUT_MS, ou si le téléphone est trop lent : la séance n'est jamais
 * bloquée. Le lien « Visage simple » / « Afficher le professeur en 3D »
 * mémorise le choix de l'apprenant dans le navigateur.
 */

// Nouvelle clé : l'ancien réglage (« teacher3d ») n'est plus lu, la 3D redevient le choix par défaut.
const PREFERENCE = 'teacher-view';
const LEGACY_PREFERENCE = 'teacher3d';
const LOAD_TIMEOUT_MS = 30000;

function preference() {
    try {
        return localStorage.getItem(PREFERENCE);
    } catch {
        return null;
    }
}

function setPreference(value) {
    try {
        localStorage.setItem(PREFERENCE, value);
        localStorage.removeItem(LEGACY_PREFERENCE);
    } catch { /* stockage indisponible : le choix vaut pour cette page seulement */ }
}

// WebGL 2 disponible ? (three.js en a besoin)
function supportsWebGL2() {
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl2');
        gl?.getExtension('WEBGL_lose_context')?.loseContext();

        return !!gl;
    } catch {
        return false;
    }
}

/**
 * Raison de ne pas charger la 3D (ou null) : choix « Visage simple » de
 * l'apprenant, moins de 1 Go de mémoire, ou pas de WebGL 2.
 * Fonction pure, testée par tests/js/teacher-view.test.mjs.
 */
export function skipReason({ preference, deviceMemory, webgl }) {
    if (preference === 'svg') {
        return 'preference';
    }

    if (deviceMemory && deviceMemory < 1) {
        return 'memory';
    }

    if (!webgl) {
        return 'webgl';
    }

    return null;
}

export function reasonToSkip() {
    return skipReason({
        preference: preference(),
        deviceMemory: navigator.deviceMemory,
        webgl: !!window.ResizeObserver && supportsWebGL2(),
    });
}

/**
 * Promesse rejetée si elle n'aboutit pas dans le délai.
 */
export function withTimeout(promise, ms) {
    let timer;

    return Promise.race([
        promise,
        new Promise((resolve, reject) => { timer = setTimeout(() => reject(new Error('timeout')), ms); }),
    ]).finally(() => clearTimeout(timer));
}

export function createTeacherView(root, config) {
    const box = root.querySelector('.teacher');
    const stage = root.querySelector('#teacher3d');
    const loading = root.querySelector('#teacher3d-loading');
    const bar = loading.querySelector('progress');
    const toggle = root.querySelector('#teacher3d-toggle');
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    let teacher = null;
    let audio = null;
    let pendingExpression = null;
    let loadingPromise = null;
    // « Enregistrer ma séance » : appelé avec le canevas 3D juste après chaque rendu.
    let onRender = null;

    const view = {
        get active() {
            return teacher !== null;
        },

        attachAudio(context, voiceNode, micNode) {
            audio = { context, voiceNode, micNode };
            teacher?.attachAudio(context, voiceNode, micNode);
        },

        detachAudio() {
            audio = null;
            teacher?.detachAudio();
        },

        expression(name) {
            if (teacher) {
                teacher.expression(name);
            } else {
                pendingExpression = name;
            }
        },

        learnerSpoke() {
            teacher?.learnerSpoke();
        },

        set onRender(callback) {
            onRender = callback;

            if (teacher) {
                teacher.onrender = callback;
            }
        },
    };

    function showToggle(text, action) {
        toggle.textContent = text;
        toggle.onclick = action;
        toggle.hidden = false;
    }

    function fallback() {
        teacher = null;
        root.dataset.teacher = 'svg';
        box.classList.remove('teacher-3d');
        stage.replaceChildren();
        loading.hidden = true;
        showToggle('Afficher le professeur en 3D', () => {
            setPreference('3d');
            load();
        });
    }

    function load() {
        if (loadingPromise) {
            return loadingPromise;
        }

        toggle.hidden = true;
        loading.hidden = false;
        bar.value = 0;

        // Cache durable des fichiers 3D (bibliothèque, lecture labiale).
        if ('serviceWorker' in navigator && config.serviceWorker) {
            const scope = new URL('./', config.serviceWorker).pathname;
            navigator.serviceWorker.register(config.serviceWorker, { scope }).catch(() => {});
        }

        const attempt = import(config.module)
            .then(({ createTeacher3D }) => createTeacher3D({
                container: stage,
                config,
                reducedMotion,
                onProgress: (ratio) => { bar.value = Math.round(ratio * 100); },
            }));

        loadingPromise = withTimeout(attempt, LOAD_TIMEOUT_MS)
            .then((instance) => {
                teacher = instance;
                teacher.onrender = onRender;
                teacher.onfail = () => {
                    loadingPromise = null;
                    fallback();
                };
                box.classList.add('teacher-3d');
                loading.hidden = true;
                root.dataset.teacher = '3d';

                if (audio) {
                    teacher.attachAudio(audio.context, audio.voiceNode, audio.micNode);
                }

                if (pendingExpression) {
                    teacher.expression(pendingExpression);
                    pendingExpression = null;
                }

                showToggle('Visage simple', () => {
                    setPreference('svg');
                    teacher?.dispose();
                    loadingPromise = null;
                    fallback();
                });
            })
            .catch((error) => {
                // Aucune erreur affichée à l'apprenant : le visage simple reste.
                console.warn('Professeur 3D indisponible :', error);

                // Chargement trop long : le personnage qui arriverait en retard est libéré.
                if (error?.message === 'timeout') {
                    attempt.then((instance) => instance.dispose(), () => {});
                }

                loadingPromise = null;
                fallback();
            });

        return loadingPromise;
    }

    const skip = reasonToSkip();

    if (skip) {
        root.dataset.teacher = 'svg';

        if (skip === 'preference') {
            showToggle('Afficher le professeur en 3D', () => {
                setPreference('3d');
                load();
            });
        }
    } else {
        load();
    }

    return view;
}
