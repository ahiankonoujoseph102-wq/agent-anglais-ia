/*
 * Micro de l'apprenant, sans dépendance au navigateur : testé par
 * tests/js/mic-input.test.mjs.
 *   - libellés compréhensibles pour la liste « Micro utilisé » ;
 *   - niveau sonore (témoin) ;
 *   - alerte quand rien n'est capté alors que c'est à l'apprenant de parler
 *     (cas typique : un appareil Bluetooth a pris le micro).
 */

/**
 * Micros disponibles (résultat de enumerateDevices), sans doublons : sur
 * ordinateur, Chrome ajoute « default » et « communications », copies d'un micro réel.
 *
 * @return {{ id: string, label: string }[]}
 */
export function listMicrophones(devices) {
    const inputs = devices.filter((device) => device.kind === 'audioinput' && device.deviceId);
    const real = inputs.filter((device) => !['default', 'communications'].includes(device.deviceId));
    const list = real.length > 0 ? real : inputs;
    const seen = new Map();

    return list.map((device, index) => {
        let label = micLabel(device.label, index);
        const count = (seen.get(label) ?? 0) + 1;
        seen.set(label, count);

        if (count > 1) {
            label += ` (${count})`;
        }

        return { id: device.deviceId, label };
    });
}

/**
 * Libellé en français à partir du nom technique donné par le navigateur.
 */
export function micLabel(name, index = 0) {
    const raw = (name || '').toLowerCase();

    if (/bluetooth|\bbt\b|airpods|buds|hands-?free|\bsco\b/.test(raw)) {
        return 'Écouteurs ou enceinte Bluetooth';
    }

    if (/usb/.test(raw)) {
        return 'Micro USB';
    }

    // Android : « Speakerphone » et « Headset earpiece » sont des micros du téléphone lui-même.
    if (/speakerphone|earpiece|built-?in|internal|interne|bottom|top|back|front/.test(raw)) {
        return 'Micro du téléphone';
    }

    if (/headset|headphone|casque|écouteur|wired|jack/.test(raw)) {
        return 'Écouteurs avec fil';
    }

    if (raw === '') {
        return `Micro ${index + 1}`;
    }

    if (raw.startsWith('default')) {
        return 'Micro par défaut';
    }

    if (/phone|téléphone|microphone|micro/.test(raw)) {
        return 'Micro du téléphone';
    }

    return name.length > 40 ? `${name.slice(0, 37)}…` : name;
}

/**
 * Niveau sonore (valeur efficace) d'un bloc d'échantillons entre -1 et 1.
 */
export function rms(samples) {
    let sum = 0;

    for (let i = 0; i < samples.length; i++) {
        sum += samples[i] * samples[i];
    }

    return samples.length ? Math.sqrt(sum / samples.length) : 0;
}

/**
 * Niveau affiché par le témoin, entre 0 et 1 (échelle adaptée à la voix).
 */
export function meterLevel(level) {
    return Math.min(1, Math.sqrt(Math.max(0, level) / 0.2));
}

/**
 * Silence prolongé pendant le tour de l'apprenant.
 */
export class SilenceWatch {
    /**
     * @param {number} seconds    durée de silence avant l'alerte
     * @param {number} threshold  niveau en dessous duquel on considère que rien n'est capté
     */
    constructor({ seconds = 8, threshold = 0.004 } = {}) {
        this.seconds = seconds;
        this.threshold = threshold;
        this.quietSince = null;
    }

    /**
     * @param {number} now        secondes
     * @param {number} level      niveau du micro (rms)
     * @param {boolean} listening c'est à l'apprenant de parler (micro ouvert, pas en pause, pas coupé)
     * @return {boolean} vrai s'il faut afficher l'alerte
     */
    update(now, level, listening) {
        if (!listening || level >= this.threshold) {
            this.quietSince = null;

            return false;
        }

        this.quietSince ??= now;

        return now - this.quietSince >= this.seconds;
    }
}
