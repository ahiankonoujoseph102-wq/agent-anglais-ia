/*
 * Capture d'écran d'un paiement : aperçu, et compression dans le navigateur
 * quand l'image est trop lourde (connexion mobile, forfait limité).
 * Les calculs sont testés par tests/js/proof-upload.test.mjs.
 */

// Au-delà, l'image est réduite : le texte d'une capture reste lisible à 2000 pixels.
export const COMPRESS_ABOVE_BYTES = 1.5 * 1024 * 1024;
export const MAX_SIDE = 2000;

/**
 * Faut-il compresser cette image ?
 */
export function needsCompression(bytes, width, height) {
    return bytes > COMPRESS_ABOVE_BYTES || Math.max(width, height) > MAX_SIDE;
}

/**
 * Dimensions après réduction (proportions gardées, jamais agrandie).
 */
export function fitWithin(width, height, maxSide = MAX_SIDE) {
    const scale = Math.min(1, maxSide / Math.max(width, height));

    return { width: Math.round(width * scale), height: Math.round(height * scale) };
}

/**
 * Taille lisible en français : « 850 kilooctets », « 2,4 mégaoctets ».
 */
export function formatBytes(bytes) {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} kilooctets`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} mégaoctets`;
}

async function compress(file) {
    const bitmap = await createImageBitmap(file);

    if (!needsCompression(file.size, bitmap.width, bitmap.height)) {
        bitmap.close();

        return file;
    }

    const size = fitWithin(bitmap.width, bitmap.height);
    const canvas = document.createElement('canvas');
    canvas.width = size.width;
    canvas.height = size.height;
    const context = canvas.getContext('2d');
    // Fond blanc : une image PNG transparente ne devient pas noire en JPEG.
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, size.width, size.height);
    context.drawImage(bitmap, 0, 0, size.width, size.height);
    bitmap.close();

    for (const quality of [0.85, 0.7, 0.55]) {
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));

        if (blob && (blob.size <= COMPRESS_ABOVE_BYTES || quality === 0.55)) {
            const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

            return blob.size < file.size ? new File([blob], name, { type: 'image/jpeg' }) : file;
        }
    }

    return file;
}

if (typeof document !== 'undefined') {
    const input = document.querySelector('[data-proof-input]');
    const preview = document.querySelector('[data-proof-preview]');
    const status = document.querySelector('[data-proof-status]');
    const form = document.querySelector('[data-proof-form]');
    const submit = document.querySelector('[data-proof-submit]');
    let busy = false;

    const say = (text) => {
        status.hidden = text === '';
        status.textContent = text;
    };

    const name = document.querySelector('[data-proof-name]');

    input?.addEventListener('change', async () => {
        const file = input.files?.[0];

        if (name) {
            name.textContent = file ? file.name : 'Aucune capture choisie';
        }

        // Capture choisie : le CSS montre l'aperçu et « Envoyer ma capture » (data-has-file).
        form?.toggleAttribute('data-has-file', Boolean(file));

        if (!file || !file.type.startsWith('image/')) {
            preview.hidden = true;
            say('');

            return;
        }

        busy = true;
        submit.disabled = true;
        say('Préparation de l\'image…');

        try {
            const ready = await compress(file);

            if (ready !== file && typeof DataTransfer !== 'undefined') {
                const transfer = new DataTransfer();
                transfer.items.add(ready);
                input.files = transfer.files;
                say(`Image allégée : ${formatBytes(file.size)} → ${formatBytes(ready.size)}.`);
            } else {
                say(`Image prête : ${formatBytes(file.size)}.`);
            }

            preview.src = URL.createObjectURL(ready);
            preview.hidden = false;
            // Capture prête : la page descend jusqu'à l'aperçu et « Envoyer ma capture ».
            const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
            requestAnimationFrame(() => submit.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' }));
        } catch {
            // Format que le navigateur ne sait pas lire : le serveur vérifiera l'image telle quelle.
            say('');
        } finally {
            busy = false;
            submit.disabled = false;
        }
    });

    form?.addEventListener('submit', (event) => {
        if (busy) {
            event.preventDefault();

            return;
        }

        submit.disabled = true;
        submit.dataset.label ??= submit.textContent;
        submit.textContent = 'Lecture de votre capture…';
    });

    // Retour arrière vers la page gardée en mémoire : le bouton redevient utilisable.
    window.addEventListener('pageshow', () => {
        submit.disabled = false;
        submit.textContent = submit.dataset.label ?? submit.textContent;
    });
}
