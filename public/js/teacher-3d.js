/*
 * Professeur animé en 3D (Teacher Joe).
 *
 * Chargé uniquement si le téléphone en est capable (voir teacher-view.js).
 * La bibliothèque three.js et le modèle GLB sont des fichiers statiques de
 * public/teacher3d/ ; le modèle est gardé dans le cache du navigateur.
 *
 * Comportement :
 *   - la bouche suit la voix : HeadAudio déduit les visèmes du son (analyse
 *     MFCC dans un AudioWorklet) ; à défaut, volume et timbre de la voix ;
 *   - au repos : respiration, clignements, petits mouvements de tête ;
 *   - l'apprenant parle : posture d'écoute, regard, hochements de tête ;
 *   - le professeur parle : gestes des mains et de la tête ;
 *   - expressions déclenchées par Gemini (fonction set_expression).
 */

const CACHE = 'teacher3d';

/* ---------- Tableau noir du décor ---------- */

// Mots écrits à la craie : courts, pour tenir entiers même sur un téléphone en portrait.
export const BOARD_LINES = ['Hello!', "Let's talk!"];
const BOARD_Z = -0.6;
// Plus grand tableau possible (celui d'avant), et bord droit gardé à l'écart de la tête.
const BOARD_MAX = { left: -1.12, right: -0.22, bottom: 0.535, top: 1.065 };
const BOARD_MARGIN = 0.06;
const BOARD_MIN_WIDTH = 0.12;
// En dessous de cette largeur, seule la première ligne est écrite (assez grande pour être lue).
const BOARD_ONE_LINE_WIDTH = 0.3;

/**
 * Position du tableau (en mètres, plan du mur) d'après ce que voit la caméra :
 * il commence au bord gauche VISIBLE (avec une marge) et s'arrête avant la
 * tête, donc il n'est jamais coupé par le bord de l'image. Renvoie null s'il
 * n'y a pas la place (image très étroite). Même calcul que resize().
 * Fonction pure, testée par tests/js/teacher-3d.test.mjs.
 */
export function boardLayout({ aspect, distance, fov = 30, target = [0, 0.57, 0] }) {
    const [cx, cy, cz] = target;
    const tan = Math.tan((fov * Math.PI / 180) / 2);
    const cameraZ = cz + distance;
    const cameraY = cy + distance * 0.06;
    const depth = cameraZ - BOARD_Z;
    const halfHeight = tan * depth;
    const halfWidth = halfHeight * aspect;
    // Centre de l'image sur le mur (la caméra regarde un peu vers le bas).
    const centerY = cameraY - (cameraY - cy) * (depth / distance);

    const left = Math.max(BOARD_MAX.left, cx - halfWidth + BOARD_MARGIN);
    const right = BOARD_MAX.right;
    const top = Math.min(BOARD_MAX.top, centerY + halfHeight - BOARD_MARGIN * 1.6);
    const bottom = BOARD_MAX.bottom;

    if (right - left < BOARD_MIN_WIDTH || top - bottom < 0.2) {
        return null;
    }

    const lines = right - left < BOARD_ONE_LINE_WIDTH ? BOARD_LINES.slice(0, 1) : BOARD_LINES;

    return { left, right, bottom, top, lines, visible: { left: cx - halfWidth, right: cx + halfWidth, top: centerY + halfHeight, bottom: centerY - halfHeight } };
}

/* ---------- Expressions et gestes ---------- */

// Expressions : formes du visage, attitude de la tête [inclinaison avant, rotation, penché], geste.
export const EXPRESSIONS = {
    neutral: { morphs: {}, duration: 0.1 },
    welcome: {
        morphs: { mouthSmileLeft: 0.8, mouthSmileRight: 0.8, eyeSquintLeft: 0.3, eyeSquintRight: 0.3, browInnerUp: 0.35, browOuterUpLeft: 0.35, browOuterUpRight: 0.35 },
        head: [-0.04, 0, 0.05], gesture: 'wave', duration: 3.4,
    },
    happy: {
        morphs: { mouthSmileLeft: 1, mouthSmileRight: 1, eyeSquintLeft: 0.5, eyeSquintRight: 0.5, browOuterUpLeft: 0.3, browOuterUpRight: 0.3 },
        head: [-0.03, 0, -0.04], gesture: 'openBoth', nod: 0.10, duration: 3,
    },
    encouraging: {
        morphs: { mouthSmileLeft: 0.6, mouthSmileRight: 0.6, browInnerUp: 0.5, eyeSquintLeft: 0.15, eyeSquintRight: 0.15 },
        head: [0.02, 0, 0.07], gesture: 'explainLeft', nod: 0.07, duration: 3.5,
    },
    attentive: {
        morphs: { mouthSmileLeft: 0.25, mouthSmileRight: 0.25, browInnerUp: 0.3, eyeWideLeft: 0.15, eyeWideRight: 0.15 },
        head: [0.05, 0, -0.06], lean: 0.05, gesture: 'raiseLeft', duration: 4.5,
    },
    thinking: {
        morphs: { browDownLeft: 0.35, browOuterUpRight: 0.45, mouthPucker: 0.15, mouthSmileRight: 0.2 },
        head: [-0.03, 0.08, 0.08], gaze: [-0.12, -0.15], duration: 2.5,
    },
    goodbye: {
        morphs: { mouthSmileLeft: 0.85, mouthSmileRight: 0.85, eyeSquintLeft: 0.35, eyeSquintRight: 0.35, browInnerUp: 0.3, browOuterUpLeft: 0.3, browOuterUpRight: 0.3 },
        head: [-0.03, 0, 0.06], gesture: 'wave', nod: 0.06, duration: 3.6,
    },
};

/*
 * Gestes (profil « teacher-joe ») : orientation de chaque segment du bras
 * dans la scène, en angles [x, y, z] (radians, ordre XYZ) par rapport à la
 * pose de repos. Repère : x vers la gauche du personnage, y vers le haut,
 * z vers l'apprenant. Au repos, le bras descend, l'avant-bras pointe vers
 * l'apprenant, paume vers le bas. Exemples : bras z+ = écarté (côté gauche),
 * avant-bras x− = relevé, main z− = paume tournée vers le haut (côté gauche).
 * Le côté droit est le symétrique du côté gauche (y et z inversés).
 */
const LEFT_GESTURES = {
    explain: { LeftArm: [-0.45, 0, 0.10], LeftForeArm: [-0.95, -0.15, 0], LeftHand: [-0.80, -0.15, -1.45] },
    raise: { LeftArm: [-0.20, 0, 0.40], LeftForeArm: [-1.30, 0.10, 0], LeftHand: [-1.45, 0.1, 0] },
    wave: { LeftArm: [-0.15, 0, 0.80], LeftForeArm: [-1.40, 0.15, 0], LeftHand: [-1.50, 0.15, 0] },
};

function mirrorPose(pose) {
    const out = {};

    for (const [bone, [x, y, z]] of Object.entries(pose)) {
        out[bone.replace(/^Left/, 'Right')] = [x, -y, -z];
    }

    return out;
}

const GESTURES = {
    explainLeft: LEFT_GESTURES.explain,
    explainRight: mirrorPose(LEFT_GESTURES.explain),
    openBoth: { ...LEFT_GESTURES.explain, ...mirrorPose(LEFT_GESTURES.explain) },
    raiseLeft: LEFT_GESTURES.raise,
    wave: mirrorPose(LEFT_GESTURES.wave),
};

const SPEAKING_GESTURES = ['explainRight', 'explainLeft', 'openBoth', null, 'explainRight'];

const BONES = ['Spine', 'Spine1', 'Spine2', 'Neck', 'Head', 'LeftEye', 'RightEye',
    'LeftShoulder', 'LeftArm', 'LeftForeArm', 'LeftHand', 'RightShoulder', 'RightArm', 'RightForeArm', 'RightHand'];

const VISEMES = ['viseme_aa', 'viseme_E', 'viseme_I', 'viseme_O', 'viseme_U', 'viseme_PP', 'viseme_SS', 'viseme_TH',
    'viseme_DD', 'viseme_FF', 'viseme_kk', 'viseme_nn', 'viseme_RR', 'viseme_CH', 'viseme_sil'];

/* ---------- Petits outils ---------- */

const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const approach = (value, target, rate, dt) => value + (target - value) * (1 - Math.exp(-rate * dt));
const random = (a, b) => a + Math.random() * (b - a);

// Bruit doux (somme de sinus) pour les petits mouvements naturels.
const wobble = (t, seed) => Math.sin(t * 0.73 + seed) * 0.5 + Math.sin(t * 1.37 + seed * 2.1) * 0.3 + Math.sin(t * 2.11 + seed * 3.7) * 0.2;

// Enveloppe d'un mouvement : montée, maintien, descente (0 → 1 → 0).
function envelope(age, attack, hold, release) {
    if (age < 0) {
        return 0;
    }

    if (age < attack) {
        const t = age / attack;

        return t * t * (3 - 2 * t);
    }

    if (age < attack + hold) {
        return 1;
    }

    const t = clamp((age - attack - hold) / release);

    return 1 - t * t * (3 - 2 * t);
}

/* ---------- Téléchargement du modèle (avec progression et cache) ---------- */

async function fetchModel(url, expectedSize, onProgress) {
    let cache = null;

    try {
        cache = await caches.open(CACHE);
        const hit = await cache.match(url);

        if (hit) {
            onProgress(1);

            return await hit.arrayBuffer();
        }
    } catch {
        cache = null; // Pas de cache (navigation privée…) : on télécharge.
    }

    const response = await fetch(url, { credentials: 'same-origin' });

    if (!response.ok || !response.body) {
        throw new Error(`model ${response.status}`);
    }

    const total = Number(response.headers.get('Content-Length')) || expectedSize || 0;
    const reader = response.body.getReader();
    const chunks = [];
    let loaded = 0;

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        chunks.push(value);
        loaded += value.length;

        if (total) {
            onProgress(Math.min(0.99, loaded / total));
        }
    }

    const bytes = new Uint8Array(loaded);
    let offset = 0;
    chunks.forEach((chunk) => { bytes.set(chunk, offset); offset += chunk.length; });

    if (cache) {
        try {
            // Une seule version du modèle en cache.
            const path = new URL(url, location.href).pathname;

            for (const request of await cache.keys()) {
                if (new URL(request.url).pathname === path && request.url !== new URL(url, location.href).href) {
                    await cache.delete(request);
                }
            }

            await cache.put(url, new Response(bytes, { headers: { 'Content-Type': 'model/gltf-binary' } }));
        } catch { /* cache plein : tant pis */ }
    }

    onProgress(1);

    return bytes.buffer;
}

/* ---------- Création ---------- */

export async function createTeacher3D({ container, config, onProgress = () => {}, reducedMotion = false }) {
    const [THREE, buffer] = await Promise.all([
        import(config.lib.three),
        fetchModel(config.model.url, config.model.size, onProgress),
    ]);

    const loader = new THREE.GLTFLoader();
    loader.setMeshoptDecoder(THREE.MeshoptDecoder);
    const gltf = await loader.parseAsync(buffer, '');

    return new Teacher3D(THREE, gltf, container, config, reducedMotion);
}

class Teacher3D {
    constructor(THREE, gltf, container, config, reducedMotion) {
        this.THREE = THREE;
        this.config = config;
        this.container = container;
        this.reducedMotion = reducedMotion;
        this.onfail = null;
        this.failed = false;
        this.time = 0;

        // Page de bienvenue : fond aux couleurs de la marque (derrière le canevas transparent), sans salle de classe.
        this.brand = config.scene?.mode === 'brand';

        const renderer = new THREE.WebGLRenderer({ antialias: (window.devicePixelRatio || 1) < 2, powerPreference: 'low-power', alpha: this.brand });
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.pixelRatio = Math.min(window.devicePixelRatio || 1, 1.5);
        renderer.setPixelRatio(this.pixelRatio);
        renderer.domElement.className = 'teacher3d-canvas';
        renderer.domElement.setAttribute('aria-hidden', 'true');
        renderer.domElement.addEventListener('webglcontextlost', (event) => {
            event.preventDefault();
            this.fail('context lost');
        });
        container.append(renderer.domElement);
        this.renderer = renderer;
        // Accès pour les tests dans le navigateur (lecture seule).
        container.teacher3d = this;

        this.scene = new THREE.Scene();
        if (this.brand) {
            renderer.setClearColor(0x000000, 0);
        } else {
            this.scene.background = new THREE.Color('#ecd8bd');
        }

        this.camera = new THREE.PerspectiveCamera(30, 4 / 3, 0.1, 10);
        this.buildLights();
        this.buildClassroom();

        this.model = gltf.scene;
        this.scene.add(this.model);
        this.prepareModel();

        this.state = {
            speaking: false, speakingSince: 0, silentSince: 0, teacherLevel: 0, emphasis: 0,
            listening: false, learnerLevel: 0, learnerFloor: 0.01, learnerVoice: 0, lastLearnerAt: -10,
            blink: 0, nextBlink: random(1, 3), blinkStart: -1, doubleBlink: false,
            gaze: [0, 0], gazeTarget: [0, 0], nextSaccade: 1,
            head: [0, 0, 0], lean: 0,
            nods: [], nextNod: 0,
            // Petits changements de posture au repos (tête un peu penchée, tournée…).
            idle: [0, 0, 0], idleTarget: [0, 0, 0], nextIdle: random(4, 8),
            expression: null, expressionAt: -10,
            gesture: null, gestureAt: -10, gestureHold: 0, gestureScale: 1, nextGesture: 0,
            visemes: Object.fromEntries(VISEMES.map((name) => [name, 0])),
            frameTimes: [], quality: 0,
        };

        this.resize = this.resize.bind(this);
        this.observer = new ResizeObserver(this.resize);
        this.observer.observe(container);
        this.resize();

        this.last = performance.now();
        this.frame = this.frame.bind(this);
        this.raf = requestAnimationFrame(this.frame);
    }

    /* ---------- Scène ---------- */

    buildLights() {
        const { THREE } = this;
        this.scene.add(new THREE.HemisphereLight('#fff4e6', '#8c6a4f', 1.7));
        const key = new THREE.DirectionalLight('#fff0dc', 2.1);
        key.position.set(-0.8, 1.6, 1.7);
        this.scene.add(key);
        const fill = new THREE.DirectionalLight('#fff6ee', 0.9);
        fill.position.set(0.3, 0.7, 2);
        this.scene.add(fill);
        const rim = new THREE.DirectionalLight('#d8e8ff', 0.9);
        rim.position.set(1.2, 1.3, -1);
        this.scene.add(rim);
    }

    // Décor simple de salle de classe : mur, tableau noir, fenêtre, bureau.
    buildClassroom() {
        const { THREE } = this;
        const room = new THREE.Group();
        const mat = (color) => new THREE.MeshLambertMaterial({ color });
        const box = (w, h, d, color, x, y, z) => {
            const mesh = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat(color));
            mesh.position.set(x, y, z);
            room.add(mesh);

            return mesh;
        };

        // Mur, tableau et fenêtre : seulement dans la salle de classe (pas sur le fond de la page de bienvenue).
        if (!this.brand) {
            box(4, 3, 0.02, '#ecd8bd', 0, 0.9, -0.62);
            box(4, 0.5, 0.03, '#d9bf9c', 0, -0.05, -0.6);

            // Tableau noir : placé et redessiné à chaque changement de taille (resize → placeBoard).
            this.board = new THREE.Group();
            room.add(this.board);

            // Fenêtre ensoleillée.
            box(0.56, 0.5, 0.01, '#bfe0f2', 0.66, 0.88, -0.605);
            for (const [w, h, x, y] of [[0.6, 0.03, 0.66, 1.13], [0.6, 0.03, 0.66, 0.63], [0.03, 0.53, 0.37, 0.88], [0.03, 0.53, 0.95, 0.88], [0.02, 0.5, 0.66, 0.88], [0.56, 0.02, 0.66, 0.88]]) {
                box(w, h, 0.03, '#f6efe4', x, y, -0.59);
            }
        }

        // Bureau, livres et pot à crayons.
        const top = this.config.model.desk_height ?? 0.175;
        box(1.5, 0.035, 0.62, '#8a5a36', 0, top - 0.018, 0.38);
        box(1.5, 0.4, 0.03, '#76482a', 0, top - 0.22, 0.68);
        box(0.16, 0.035, 0.22, '#ff7a1a', 0.42, top + 0.018, 0.34);
        box(0.15, 0.03, 0.21, '#2f6f9f', 0.42, top + 0.05, 0.34);
        box(0.14, 0.028, 0.2, '#e3b448', 0.42, top + 0.079, 0.34);
        const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.036, 0.1, 12), mat('#ff7a1a'));
        pot.position.set(-0.45, top + 0.05, 0.3);
        room.add(pot);

        // Crayons dans le pot.
        for (let i = 0; i < 4; i++) {
            const pencil = new THREE.Mesh(new THREE.CylinderGeometry(0.006, 0.006, 0.12, 6), mat(['#0e2a5c', '#e3b448', '#3d6bc4', '#f6efe4'][i]));
            const a = (i / 4) * Math.PI * 2;
            pencil.position.set(-0.45 + Math.cos(a) * 0.018, top + 0.11, 0.3 + Math.sin(a) * 0.018);
            pencil.rotation.set(Math.sin(a) * 0.18, 0, -Math.cos(a) * 0.18);
            room.add(pencil);
        }

        this.scene.add(room);
    }

    // Tableau noir dans la partie visible à gauche de la tête, texte court centré : jamais coupé.
    placeBoard(distance) {
        if (!this.board) {
            return;
        }

        const { THREE } = this;
        const layout = boardLayout({ aspect: this.camera.aspect, distance, fov: this.camera.fov, target: this.config.model.camera.target });
        const key = layout ? [layout.left, layout.right, layout.bottom, layout.top].map((v) => v.toFixed(3)).join() : 'none';

        if (key === this.boardKey) {
            return;
        }

        this.boardKey = key;

        for (const child of [...this.board.children]) {
            this.board.remove(child);
            child.geometry?.dispose();
            child.material?.map?.dispose();
            child.material?.dispose();
        }

        if (!layout) {
            return;
        }

        const { left, right, bottom, top } = layout;
        const width = right - left;
        const height = top - bottom;
        const cx = (left + right) / 2;
        const cy = (top + bottom) / 2;

        // Texture de 512 pixels sur le côté le plus long ; texte réduit s'il dépasse la largeur.
        const scale = 512 / Math.max(width, height);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(64, Math.round(width * scale));
        canvas.height = Math.max(64, Math.round(height * scale));
        const g = canvas.getContext('2d');
        g.fillStyle = '#16305e';
        g.fillRect(0, 0, canvas.width, canvas.height);
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        const fit = (text, size, weight) => {
            g.font = `${weight} ${size}px sans-serif`;
            const room = canvas.width * 0.84;
            const measured = g.measureText(text).width;

            if (measured > room) {
                g.font = `${weight} ${Math.floor(size * room / measured)}px sans-serif`;
            }
        };
        const { lines } = layout;
        const big = Math.min(canvas.height * 0.3, 96);
        fit(lines[0], big, 'bold');
        g.fillStyle = 'rgba(255,255,255,0.88)';
        g.fillText(lines[0], canvas.width / 2, canvas.height * (lines.length > 1 ? 0.4 : 0.42));

        if (lines[1]) {
            fit(lines[1], big * 0.55, 'normal');
            g.fillStyle = 'rgba(255,255,255,0.72)';
            g.fillText(lines[1], canvas.width / 2, canvas.height * 0.7);
        }

        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        const board = new THREE.Mesh(new THREE.PlaneGeometry(width, height), new THREE.MeshLambertMaterial({ map: texture }));
        board.position.set(cx, cy, BOARD_Z);
        this.board.add(board);

        // Cadre en bois.
        const frame = (w, h, x, y) => {
            const mesh = new THREE.Mesh(new THREE.BoxGeometry(w, h, 0.03), new THREE.MeshLambertMaterial({ color: '#8a5a36' }));
            mesh.position.set(x, y, BOARD_Z + 0.01);
            this.board.add(mesh);
        };
        frame(width + 0.06, 0.03, cx, bottom - 0.015);
        frame(width + 0.06, 0.03, cx, top + 0.015);
        frame(0.03, height + 0.06, left - 0.015, cy);
        frame(0.03, height + 0.06, right + 0.015, cy);
    }

    prepareModel() {
        const { THREE } = this;
        this.morphs = new Map(); // nom → [[mesh, index], …]
        this.bones = {};

        this.model.traverse((object) => {
            if (object.isMesh) {
                object.frustumCulled = false;

                for (const [name, index] of Object.entries(object.morphTargetDictionary ?? {})) {
                    if (!this.morphs.has(name)) {
                        this.morphs.set(name, []);
                    }

                    this.morphs.get(name).push([object, index]);
                }
            }

            if (object.isBone && BONES.includes(object.name)) {
                this.bones[object.name] = { bone: object, rest: object.quaternion.clone() };
            }
        });

        this.euler = new THREE.Euler();
        this.quat = new THREE.Quaternion();
        // Les gestes ne sont valables que pour le squelette d'origine (axes connus).
        this.gesturesEnabled = this.config.model.profile === 'teacher-joe';
    }

    resize() {
        const width = Math.max(1, this.container.clientWidth);
        const height = Math.max(1, this.container.clientHeight);
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;

        // Cadrage : du bureau au-dessus de la tête, et la largeur des épaules.
        const [cx, cy, cz] = this.config.model.camera.target;
        const halfHeight = this.config.model.camera.half_height;
        const halfWidth = this.config.model.camera.half_width;
        const fov = 30 * (Math.PI / 180);
        const distance = Math.max(halfHeight, halfWidth / this.camera.aspect) / Math.tan(fov / 2);
        this.camera.fov = 30;
        this.camera.position.set(cx, cy + distance * 0.06, cz + distance);
        this.camera.lookAt(cx, cy, cz);
        this.camera.updateProjectionMatrix();
        this.eyeLevelPitch = Math.atan2((this.config.model.camera.eye_height ?? 0.68) - this.camera.position.y, distance);
        this.placeBoard(distance);
    }

    /* ---------- Audio ---------- */

    /**
     * Branche la voix du professeur (nœud avant le haut-parleur) et le micro.
     * HeadAudio analyse la voix ; un analyseur sert au volume et au repli.
     */
    async attachAudio(context, voiceNode, micNode = null) {
        this.detachAudio();
        const analyser = context.createAnalyser();
        analyser.fftSize = 1024;
        analyser.smoothingTimeConstant = 0.3;
        voiceNode.connect(analyser);
        this.audio = { context, voiceNode, analyser, time: new Float32Array(analyser.fftSize), freq: new Uint8Array(analyser.frequencyBinCount) };

        if (micNode) {
            const mic = context.createAnalyser();
            mic.fftSize = 512;
            micNode.connect(mic);
            this.audio.mic = mic;
            this.audio.micNode = micNode;
            this.audio.micTime = new Float32Array(mic.fftSize);
        }

        try {
            await context.audioWorklet.addModule(this.config.lib.worklet);
            const { HeadAudio } = await import(this.config.lib.headaudio);
            const node = new HeadAudio(context, {
                processorOptions: {},
                parameterData: {
                    vadMode: 1, vadGateActiveDb: -42, vadGateInactiveDb: -52, silMode: 0,
                    speakerMeanHz: this.config.model.voice_hz ?? 150,
                },
            });
            await node.loadModel(this.config.lib.lipsync);
            node.onvalue = (key, value) => { this.state.visemes[key] = value; };

            if (this.audio?.voiceNode === voiceNode) {
                voiceNode.connect(node);
                this.audio.headaudio = node;
            }
        } catch (error) {
            // Repli : formes de bouche déduites du volume et du timbre.
            console.warn('HeadAudio indisponible', error);
        }
    }

    detachAudio() {
        if (!this.audio) {
            return;
        }

        const { voiceNode, analyser, headaudio, micNode, mic } = this.audio;

        try { voiceNode.disconnect(analyser); } catch { /* déjà débranché */ }

        if (headaudio) {
            try { voiceNode.disconnect(headaudio); } catch { /* idem */ }
            headaudio.onvalue = null;
        }

        if (micNode && mic) {
            try { micNode.disconnect(mic); } catch { /* idem */ }
        }

        this.audio = null;
        Object.keys(this.state.visemes).forEach((key) => { this.state.visemes[key] = 0; });
    }

    readLevels(dt) {
        const s = this.state;
        const audio = this.audio;

        if (!audio || audio.context.state === 'closed') {
            s.teacherLevel = approach(s.teacherLevel, 0, 10, dt);
            s.learnerLevel = 0;

            return;
        }

        audio.analyser.getFloatTimeDomainData(audio.time);
        let sum = 0;

        for (const v of audio.time) {
            sum += v * v;
        }

        const rms = Math.sqrt(sum / audio.time.length);
        const previous = s.teacherLevel;
        s.teacherLevel = approach(s.teacherLevel, rms, 25, dt);
        // Accent : montée rapide du volume.
        s.emphasis = approach(s.emphasis, clamp((s.teacherLevel - previous) * 60), 6, dt);

        if (audio.mic) {
            audio.mic.getFloatTimeDomainData(audio.micTime);
            let m = 0;

            for (const v of audio.micTime) {
                m += v * v;
            }

            s.learnerLevel = Math.sqrt(m / audio.micTime.length);
            // Niveau de bruit de fond, suivi lentement.
            s.learnerFloor = s.learnerLevel < s.learnerFloor ? approach(s.learnerFloor, s.learnerLevel, 4, dt) : approach(s.learnerFloor, s.learnerLevel, 0.05, dt);
        }
    }

    // Repli sans HeadAudio : ouverture selon le volume, forme selon le timbre.
    fallbackVisemes(dt) {
        const s = this.state;
        const audio = this.audio;
        const v = s.visemes;
        const open = clamp((s.teacherLevel - 0.01) * 9);
        let bright = 0.5;

        if (audio && open > 0.05) {
            audio.analyser.getByteFrequencyData(audio.freq);
            const binHz = audio.context.sampleRate / audio.analyser.fftSize;
            let low = 0;
            let high = 0;

            audio.freq.forEach((value, i) => {
                const hz = i * binHz;

                if (hz > 250 && hz < 1000) {
                    low += value;
                } else if (hz >= 1500 && hz < 4500) {
                    high += value;
                }
            });

            bright = clamp(high / (low + high + 1) * 1.8);
        }

        v.viseme_aa = approach(v.viseme_aa, open * 0.7 * (1 - Math.abs(bright - 0.5)), 18, dt);
        v.viseme_E = approach(v.viseme_E, open * 0.6 * clamp((bright - 0.45) * 3), 18, dt);
        v.viseme_O = approach(v.viseme_O, open * 0.6 * clamp((0.45 - bright) * 3), 18, dt);
        v.viseme_PP = approach(v.viseme_PP, open < 0.05 && s.speaking ? 0.3 : 0, 18, dt);
    }

    /* ---------- Commandes ---------- */

    expression(name) {
        const preset = EXPRESSIONS[name];

        if (!preset) {
            return;
        }

        const s = this.state;

        // Pas de saturation : la même expression redemandée trop vite est ignorée.
        if (s.expression === name && this.time - s.expressionAt < 3 && name !== 'neutral') {
            return;
        }

        s.expression = name;
        s.expressionAt = this.time;
        // Un changement d'expression s'accompagne souvent d'un clignement.
        s.nextBlink = Math.min(s.nextBlink, this.time + 0.12);

        if (preset.gesture && this.gesturesEnabled) {
            this.startGesture(preset.gesture, preset.duration - 1.2, 1);
        }

        if (preset.nod) {
            this.nod(preset.nod);
        }
    }

    // L'apprenant vient de parler (sous-titres) : posture d'écoute.
    learnerSpoke() {
        this.state.lastLearnerAt = this.time;
    }

    nod(amplitude = 0.08, duration = 0.7) {
        this.state.nods.push({ at: this.time, amplitude, duration });
    }

    startGesture(name, hold, scale) {
        const s = this.state;
        s.gesture = name;
        s.gestureAt = this.time;
        s.gestureHold = Math.max(0.3, hold);
        s.gestureScale = scale;
    }

    /* ---------- Animation ---------- */

    frame(now) {
        if (this.failed) {
            return;
        }

        this.raf = requestAnimationFrame(this.frame);
        const elapsed = now - this.last;

        // 30 images par seconde suffisent et ménagent la batterie.
        if (elapsed < 1000 / 31) {
            return;
        }

        this.last = now;
        const dt = Math.min(0.1, elapsed / 1000);
        this.time += dt;
        this.watchPerformance(elapsed);

        try {
            this.update(dt);
            this.renderer.render(this.scene, this.camera);
        } catch (error) {
            this.fail(error);
        }
    }

    // Téléphone trop lent : on baisse la définition (sous 20 images/s), puis on
    // revient au visage simple (toujours sous 12 images/s).
    watchPerformance(elapsed) {
        const s = this.state;

        // Retour sur l'onglet après une pause : intervalle sans signification.
        if (elapsed > 1000) {
            return;
        }

        s.frameTimes.push(elapsed);

        if (s.frameTimes.length < 45) {
            return;
        }

        const mean = s.frameTimes.reduce((sum, v) => sum + v, 0) / s.frameTimes.length;
        s.frameTimes = [];

        if (document.hidden || this.time < 3) {
            return;
        }

        if (mean > 50 && s.quality < 2) {
            s.quality++;
            this.pixelRatio = s.quality === 1 ? Math.min(this.pixelRatio, 1) : 0.75;
            this.renderer.setPixelRatio(this.pixelRatio);
            this.resize();

            if (s.quality === 2) {
                this.simplifyMaterials();
            }
        } else if (mean > 83 && s.quality >= 2) {
            this.fail('too slow');
        }
    }

    simplifyMaterials() {
        const { THREE } = this;

        this.model.traverse((object) => {
            if (object.isMesh && object.material?.isMeshStandardMaterial) {
                const old = object.material;
                object.material = new THREE.MeshLambertMaterial({ vertexColors: old.vertexColors, side: old.side });
                old.dispose();
            }
        });
    }

    update(dt) {
        const s = this.state;
        const t = this.time;
        const calm = this.reducedMotion ? 0.4 : 1;
        this.readLevels(dt);

        // Le professeur parle-t-il ? (avec un peu d'inertie)
        const speakingNow = s.teacherLevel > 0.012;

        if (speakingNow) {
            if (!s.speaking && t - s.silentSince > 0.5) {
                s.speakingSince = t;
                // Début d'une nouvelle phrase : regard vers l'apprenant, clignement, petit geste.
                s.nextGesture = Math.min(s.nextGesture, t + 0.3);
                s.nextBlink = Math.min(s.nextBlink, t + 0.15);
            }

            s.speaking = true;
            s.silentSince = t;
        } else if (s.speaking && t - s.silentSince > 0.35) {
            s.speaking = false;
        }

        // L'apprenant parle-t-il ? (micro au-dessus du bruit de fond, pendant que le professeur se tait)
        const voice = !s.speaking && s.learnerLevel > Math.max(0.015, s.learnerFloor * 2.5);
        s.learnerVoice = approach(s.learnerVoice, voice ? 1 : 0, voice ? 6 : 1.5, dt);

        if (s.learnerVoice > 0.5) {
            s.lastLearnerAt = t;
        }

        const wasListening = s.listening;
        s.listening = !s.speaking && t - s.lastLearnerAt < 1.6;

        // Hochements de tête pendant que l'apprenant parle, et à la fin de sa phrase.
        if (s.listening && t > s.nextNod) {
            this.nod(random(0.04, 0.08) * calm, random(0.55, 0.85));

            // Parfois un double hochement (« oui, oui »).
            if (Math.random() < 0.25) {
                s.nods.push({ at: t + 0.45, amplitude: 0.035 * calm, duration: 0.5 });
            }

            s.nextNod = t + random(1.6, 3.4);
        }

        if (wasListening && !s.listening && !s.speaking) {
            this.nod(0.06 * calm);
        }

        // Gestes pendant la parole.
        if (this.gesturesEnabled && s.speaking && t > s.nextGesture && (!s.gesture || t - s.gestureAt > s.gestureHold + 1.5)) {
            const name = SPEAKING_GESTURES[Math.floor(Math.random() * SPEAKING_GESTURES.length)];

            if (name) {
                this.startGesture(name, random(0.8, 1.8), random(0.55, 1) * calm);
            }

            s.nextGesture = t + random(2.5, 5);
        }

        this.updateFace(dt, t, calm);
        this.updateBody(dt, t, calm);
    }

    updateFace(dt, t, calm) {
        const s = this.state;
        const values = {};
        const set = (name, value) => { values[name] = (values[name] ?? 0) + value; };

        // Visage au repos : léger sourire bienveillant.
        const restSmile = this.config.model.resting_smile ?? 0.25;
        set('mouthSmileLeft', restSmile);
        set('mouthSmileRight', restSmile);

        // Écoute : sourire et sourcils un peu levés.
        const listen = s.listening ? 1 : 0;
        s.listenWeight = approach(s.listenWeight ?? 0, listen, 3, dt);
        // Écoute : sourire chaleureux (avec les yeux), sourcils un peu levés.
        set('mouthSmileLeft', 0.22 * s.listenWeight);
        set('mouthSmileRight', 0.22 * s.listenWeight);
        set('eyeSquintLeft', 0.12 * s.listenWeight);
        set('eyeSquintRight', 0.12 * s.listenWeight);
        set('browInnerUp', 0.15 * s.listenWeight);

        // Expression demandée par le professeur.
        const preset = s.expression ? EXPRESSIONS[s.expression] : null;
        const weight = preset ? envelope(t - s.expressionAt, 0.35, preset.duration, 0.9) : 0;

        if (preset) {
            for (const [name, value] of Object.entries(preset.morphs)) {
                set(name, value * weight);
            }
        }

        // Accents de la voix : sourcils.
        if (s.speaking) {
            set('browInnerUp', 0.35 * s.emphasis * calm);
            set('browOuterUpLeft', 0.25 * s.emphasis * calm);
            set('browOuterUpRight', 0.25 * s.emphasis * calm);
        }

        // Bouche : visèmes.
        if (this.audio && !this.audio.headaudio) {
            this.fallbackVisemes(dt);
        } else if (this.audio?.headaudio) {
            this.audio.headaudio.update(dt * 1000);
            // HeadAudio donne la forme de la bouche ; le volume ajoute l'amplitude de la mâchoire.
            set('jawOpen', 0.3 * clamp((s.teacherLevel - 0.01) * 8));
        } else {
            VISEMES.forEach((name) => { s.visemes[name] = approach(s.visemes[name], 0, 12, dt); });
        }

        let mouth = 0;

        for (const name of VISEMES) {
            const v = s.visemes[name];
            set(name, v);
            mouth = Math.max(mouth, v);
        }

        // Pendant la parole, le sourire s'atténue pour laisser la bouche articuler.
        values.mouthSmileLeft *= 1 - 0.5 * mouth;
        values.mouthSmileRight *= 1 - 0.5 * mouth;

        // Clignements naturels (parfois doubles).
        if (t > s.nextBlink && s.blinkStart < 0) {
            s.blinkStart = t;
        }

        if (s.blinkStart >= 0) {
            const age = t - s.blinkStart;
            s.blink = age < 0.07 ? age / 0.07 : Math.max(0, 1 - (age - 0.07) / 0.1);

            if (age > 0.17) {
                s.blinkStart = -1;
                s.blink = 0;
                s.doubleBlink = !s.doubleBlink && Math.random() < 0.15;
                // Intervalle naturel : le plus souvent 2 à 4,5 secondes, parfois plus long.
                s.nextBlink = t + (s.doubleBlink ? 0.12 : (Math.random() < 0.75 ? random(2, 4.5) : random(4.5, 7)));
            }
        }

        set('eyeBlinkLeft', s.blink);
        set('eyeBlinkRight', s.blink);

        for (const [name, list] of this.morphs) {
            const value = clamp(values[name] ?? 0);

            for (const [mesh, index] of list) {
                mesh.morphTargetInfluences[index] = value;
            }
        }
    }

    updateBody(dt, t, calm) {
        const s = this.state;
        const preset = s.expression ? EXPRESSIONS[s.expression] : null;
        const weight = preset ? envelope(t - s.expressionAt, 0.4, preset.duration, 1) : 0;
        const head = [0, 0, 0];

        // Petits mouvements de repos, et de temps en temps un léger changement de posture.
        if (t > s.nextIdle) {
            const free = !s.speaking && !s.listening;
            s.idleTarget = free ? [random(-0.03, 0.03), random(-0.07, 0.07), random(-0.05, 0.05)] : [0, 0, 0];
            s.nextIdle = t + random(5, 11);
        }

        s.idle = s.idle.map((v, i) => approach(v, s.idleTarget[i], 0.8, dt));
        head[0] += s.idle[0] * calm;
        head[1] += s.idle[1] * calm;
        head[2] += s.idle[2] * calm;
        head[0] += 0.025 * wobble(t * 0.6, 1) * calm;
        head[1] += 0.035 * wobble(t * 0.5, 2) * calm;
        head[2] += 0.015 * wobble(t * 0.45, 3) * calm;

        // Parole : la tête accompagne le rythme de la voix.
        if (s.speaking) {
            head[0] += (0.04 * wobble(t * 2.2, 4) - 0.05 * s.emphasis) * calm;
            head[1] += 0.05 * wobble(t * 1.4, 5) * calm;
            head[2] += 0.025 * wobble(t * 1.1, 6) * calm;
        }

        // Écoute : tête légèrement penchée, buste vers l'apprenant.
        head[2] += 0.06 * (s.listenWeight ?? 0);
        let lean = 0.035 * (s.listenWeight ?? 0);

        if (preset?.head) {
            preset.head.forEach((v, i) => { head[i] += v * weight; });
        }

        lean += (preset?.lean ?? 0) * weight;

        // Hochements.
        s.nods = s.nods.filter((n) => t - n.at < n.duration);

        for (const n of s.nods) {
            if (t < n.at) {
                continue;
            }

            const u = (t - n.at) / n.duration;
            head[0] += n.amplitude * Math.sin(Math.PI * 2 * u) * (1 - u) * 1.3;
        }

        s.head = s.head.map((v, i) => approach(v, head[i], 8, dt));
        s.lean = approach(s.lean, lean, 3, dt);

        // Respiration.
        const breath = Math.sin(t * (Math.PI * 2 / 4.2));
        this.rotate('Spine1', -0.008 * breath + s.lean * 0.5, 0, 0);
        this.rotate('Spine2', -0.01 * breath + s.lean * 0.5, 0.01 * wobble(t * 0.3, 7) * calm, 0);
        this.rotate('LeftShoulder', 0, 0, 0.012 * breath);
        this.rotate('RightShoulder', 0, 0, -0.012 * breath);
        this.rotate('Neck', s.head[0] * 0.35 - s.lean * 0.6, s.head[1] * 0.35, s.head[2] * 0.3);
        this.rotate('Head', s.head[0] * 0.65 - s.lean * 0.4, s.head[1] * 0.65, s.head[2] * 0.7);

        // Regard : vers l'apprenant, avec de petites saccades au repos.
        if (t > s.nextSaccade) {
            const free = !s.speaking && !s.listening;
            s.gazeTarget = free ? [random(-0.08, 0.08), random(-0.12, 0.12)] : [random(-0.02, 0.02), random(-0.03, 0.03)];
            s.nextSaccade = t + random(0.8, free ? 2.5 : 3.5);
        }

        const gaze = [s.gazeTarget[0], s.gazeTarget[1]];

        if (preset?.gaze) {
            gaze[0] += preset.gaze[0] * weight;
            gaze[1] += preset.gaze[1] * weight;
        }

        s.gaze = s.gaze.map((v, i) => approach(v, gaze[i], 25, dt));
        // Les yeux compensent en partie la tête pour rester sur l'apprenant.
        const pitch = this.eyeLevelPitch * -1 + s.gaze[0] - s.head[0] * 0.5;
        const yaw = s.gaze[1] - s.head[1] * 0.6;
        this.rotate('LeftEye', pitch, yaw, 0);
        this.rotate('RightEye', pitch, yaw, 0);

        // Bras : geste en cours.
        const pose = {};

        if (this.gesturesEnabled && s.gesture) {
            const g = envelope(t - s.gestureAt, 0.55, s.gestureHold, 0.8) * s.gestureScale;

            for (const [bone, [x, y, z]] of Object.entries(GESTURES[s.gesture] ?? {})) {
                pose[bone] = [x * g, y * g, z * g];
            }

            // Main qui salue.
            if (s.gesture === 'wave' && g > 0.3) {
                const sway = 0.25 * Math.sin((t - s.gestureAt) * 9) * g;
                pose.RightForeArm[2] += sway;
                pose.RightHand[2] += sway * 1.3;
            }

            if (t - s.gestureAt > s.gestureHold + 1.4) {
                s.gesture = null;
            }
        }

        // Main non précisée : dans le prolongement de l'avant-bras.
        for (const side of ['Left', 'Right']) {
            if (pose[`${side}ForeArm`] && !pose[`${side}Hand`]) {
                pose[`${side}Hand`] = pose[`${side}ForeArm`];
            }
        }

        const world = {};

        for (const name of ['LeftArm', 'LeftForeArm', 'LeftHand', 'RightArm', 'RightForeArm', 'RightHand']) {
            const target = pose[name] ?? [0, 0, 0];
            const current = (s.arms ??= {})[name] ?? [0, 0, 0];
            world[name] = target.map((v, i) => approach(current[i], v, 10, dt));
            s.arms[name] = world[name];
        }

        // Orientations dans la scène → rotations locales (chaque os dépend de son parent).
        for (const side of ['Left', 'Right']) {
            const arm = this.worldQuat(world[`${side}Arm`]);
            const fore = this.worldQuat(world[`${side}ForeArm`]);
            const hand = this.worldQuat(world[`${side}Hand`]);
            this.setLocal(`${side}Arm`, arm);
            this.setLocal(`${side}ForeArm`, arm.clone().invert().multiply(fore));
            this.setLocal(`${side}Hand`, fore.clone().invert().multiply(hand));
        }
    }

    worldQuat([x, y, z]) {
        this.euler.set(x, y, z, 'XYZ');

        return new this.THREE.Quaternion().setFromEuler(this.euler);
    }

    setLocal(name, quaternion) {
        const entry = this.bones[name];

        if (entry) {
            entry.bone.quaternion.copy(entry.rest).multiply(quaternion);
        }
    }

    rotate(name, x, y, z) {
        const entry = this.bones[name];

        if (!entry) {
            return;
        }

        this.euler.set(x, y, z, 'XYZ');
        this.quat.setFromEuler(this.euler);
        entry.bone.quaternion.copy(entry.rest).multiply(this.quat);
    }

    /* ---------- Fin ---------- */

    fail(reason) {
        if (this.failed) {
            return;
        }

        console.warn('Professeur 3D désactivé :', reason);
        this.dispose();
        this.onfail?.(reason);
    }

    dispose() {
        this.failed = true;
        cancelAnimationFrame(this.raf);
        this.observer?.disconnect();
        this.detachAudio();
        this.scene?.traverse((object) => {
            object.geometry?.dispose();
            [object.material].flat().forEach((m) => { m?.map?.dispose(); m?.dispose(); });
        });
        this.renderer?.dispose();
        this.renderer?.domElement.remove();
    }
}
