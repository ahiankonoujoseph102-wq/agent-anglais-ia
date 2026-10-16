/*
 * Choix entre le professeur 3D et le visage SVG sur l'écran de conversation.
 *
 * Le visage SVG s'affiche toujours d'abord. Si le téléphone en est capable,
 * le professeur 3D se charge en arrière-plan (barre de progression) puis
 * prend sa place. En cas d'échec, de lenteur ou de mode « économie de
 * données », le visage SVG reste : la séance n'est jamais bloquée.
 */

const PREFERENCE = 'teacher3d';

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
    } catch { /* stockage indisponible */ }
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
 * Raison de ne pas charger la 3D automatiquement (ou null).
 */
export function reasonToSkip() {
    if (preference() === 'off') {
        return 'preference';
    }

    if (preference() !== 'on' && navigator.connection?.saveData) {
        return 'save-data';
    }

    if (navigator.deviceMemory && navigator.deviceMemory < 1) {
        return 'memory';
    }

    if (!window.ResizeObserver || !supportsWebGL2()) {
        return 'webgl';
    }

    return null;
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
            setPreference('on');
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

        loadingPromise = import(config.module)
            .then(({ createTeacher3D }) => createTeacher3D({
                container: stage,
                config,
                reducedMotion,
                onProgress: (ratio) => { bar.value = Math.round(ratio * 100); },
            }))
            .then((instance) => {
                teacher = instance;
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
                    setPreference('off');
                    teacher?.dispose();
                    loadingPromise = null;
                    fallback();
                });
            })
            .catch((error) => {
                console.warn('Professeur 3D indisponible :', error);
                loadingPromise = null;
                fallback();
            });

        return loadingPromise;
    }

    const skip = reasonToSkip();

    if (skip) {
        root.dataset.teacher = 'svg';

        if (skip === 'preference' || skip === 'save-data') {
            showToggle('Afficher le professeur en 3D', () => {
                setPreference('on');
                load();
            });
        }
    } else {
        load();
    }

    return view;
}
