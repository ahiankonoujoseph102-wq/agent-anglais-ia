/*
 * « Mes cours » : choix des pages d'une leçon (photo, galerie ou PDF), aperçu, retrait d'une page
 * et réduction des photos dans le navigateur avant l'envoi (connexion mobile, forfait limité).
 *
 * - Photos : au plus 4 pages ; chaque photo est réduite (côté le plus long environ 1600 pixels, JPEG).
 *   Si le navigateur ne sait pas la réduire, la photo part telle quelle (le serveur vérifie son poids).
 * - PDF : au plus 10 mégaoctets (le serveur vérifie aussi les 10 pages). Un PDF remplace les photos.
 * Les calculs sont testés par tests/js/course-upload.test.mjs. Sans JavaScript, la galerie (pages[]) et
 * le PDF (pdf) s'envoient tels quels.
 */

export const DEFAULT_MAX_SIDE = 1600;
export const DEFAULT_QUALITY = 0.82;
// En dessous de ce poids et de cette taille, la photo est déjà légère : on ne la retouche pas.
export const KEEP_BELOW_BYTES = 350 * 1024;

/**
 * Faut-il réduire cette photo ?
 */
export function needsReduction(bytes, width, height, maxSide = DEFAULT_MAX_SIDE) {
    return Math.max(width, height) > maxSide || bytes > KEEP_BELOW_BYTES * 2;
}

/**
 * Dimensions après réduction (proportions gardées, jamais agrandie).
 */
export function fitWithin(width, height, maxSide = DEFAULT_MAX_SIDE) {
    const scale = Math.min(1, maxSide / Math.max(width, height));

    return { width: Math.max(1, Math.round(width * scale)), height: Math.max(1, Math.round(height * scale)) };
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

/**
 * Pages après un ajout : au plus `max`, les plus anciennes d'abord ; retourne aussi ce qui a été refusé.
 */
export function addPages(current, added, max) {
    const room = Math.max(0, max - current.length);

    return { pages: [...current, ...added.slice(0, room)], refused: Math.max(0, added.length - room) };
}

/**
 * Nom du fichier réduit : « page.png » → « page.jpg ».
 */
export function reducedName(name) {
    return `${name.replace(/\.[^.]+$/, '') || 'page'}.jpg`;
}

async function reduce(file, maxSide, quality) {
    if (typeof createImageBitmap !== 'function') {
        return file;
    }

    const bitmap = await createImageBitmap(file);

    try {
        if (!needsReduction(file.size, bitmap.width, bitmap.height, maxSide)) {
            return file;
        }

        const size = fitWithin(bitmap.width, bitmap.height, maxSide);
        const canvas = document.createElement('canvas');
        canvas.width = size.width;
        canvas.height = size.height;
        const context = canvas.getContext('2d');
        // Fond blanc : une image PNG transparente ne devient pas noire en JPEG.
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, size.width, size.height);
        context.drawImage(bitmap, 0, 0, size.width, size.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));

        return blob && blob.size < file.size ? new File([blob], reducedName(file.name), { type: 'image/jpeg' }) : file;
    } finally {
        bitmap.close();
    }
}

if (typeof document !== 'undefined' && document.querySelector('[data-course-form]')) {
    const form = document.querySelector('[data-course-form]');
    const camera = form.querySelector('[data-course-camera]');
    const gallery = form.querySelector('[data-course-gallery]');
    const pdfInput = form.querySelector('[data-course-pdf]');
    const list = form.querySelector('[data-course-previews]');
    const status = form.querySelector('[data-course-status]');
    const submit = form.querySelector('[data-course-submit]');
    const waiting = form.querySelector('[data-course-waiting]');
    const maxPhotos = Number(form.dataset.maxPhotos) || 4;
    const maxSide = Number(form.dataset.maxSide) || DEFAULT_MAX_SIDE;
    const quality = Number(form.dataset.quality) || DEFAULT_QUALITY;
    const photoMaxBytes = Number(form.dataset.photoMaxBytes) || Infinity;
    const pdfMaxBytes = Number(form.dataset.pdfMaxBytes) || Infinity;

    // Les pages choisies, réunies dans un seul champ « pages[] » (le champ de la galerie ne sert plus qu'à choisir).
    const aggregate = document.createElement('input');
    aggregate.type = 'file';
    aggregate.name = 'pages[]';
    aggregate.multiple = true;
    aggregate.hidden = true;
    form.append(aggregate);
    gallery.removeAttribute('name');
    pdfInput.removeAttribute('name');
    const pdfCarrier = document.createElement('input');
    pdfCarrier.type = 'file';
    pdfCarrier.name = 'pdf';
    pdfCarrier.hidden = true;
    form.append(pdfCarrier);

    const state = { photos: [], pdf: null, busy: false };

    const say = (text) => {
        status.hidden = text === '';
        status.textContent = text;
    };

    const setFiles = (input, files) => {
        if (typeof DataTransfer === 'undefined') {
            return;
        }

        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
    };

    const render = () => {
        list.replaceChildren();
        const items = state.pdf ? [{ file: state.pdf, pdf: true }] : state.photos.map((photo) => ({ file: photo.file, url: photo.url }));

        items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'course-preview';

            if (item.pdf) {
                li.innerHTML = '<span class="course-preview-pdf" aria-hidden="true">PDF</span>';
            } else {
                const img = document.createElement('img');
                img.src = item.url;
                img.alt = `Page ${index + 1}`;
                li.append(img);
            }

            const label = document.createElement('span');
            label.className = 'course-preview-name';
            label.textContent = item.pdf ? `${item.file.name} · ${formatBytes(item.file.size)}` : `Page ${index + 1} · ${formatBytes(item.file.size)}`;
            li.append(label);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-light course-preview-remove';
            remove.textContent = item.pdf ? 'Retirer le PDF' : `Retirer la page ${index + 1}`;
            remove.addEventListener('click', () => {
                if (item.pdf) {
                    state.pdf = null;
                } else {
                    const [removed] = state.photos.splice(index, 1);
                    URL.revokeObjectURL(removed.url);
                }

                sync();
                say('');
            });
            li.append(remove);
            list.append(li);
        });

        list.hidden = items.length === 0;
        form.toggleAttribute('data-has-files', items.length > 0);
    };

    const sync = () => {
        setFiles(aggregate, state.photos.map((photo) => photo.file));
        setFiles(pdfCarrier, state.pdf ? [state.pdf] : []);
        render();
    };

    const addPhotos = async (files) => {
        const images = files.filter((file) => file.type.startsWith('image/'));

        if (images.length === 0) {
            return;
        }

        if (state.pdf) {
            state.pdf = null;
            say('Le PDF a été retiré : une leçon est faite soit de photos, soit d\'un PDF.');
        }

        const { pages, refused } = addPages(state.photos, images, maxPhotos);
        const fresh = pages.slice(state.photos.length);

        state.busy = true;
        submit.disabled = true;
        say('Préparation des photos…');

        try {
            for (const file of fresh) {
                let ready = file;

                try {
                    ready = await reduce(file, maxSide, quality);
                } catch {
                    // Format que le navigateur ne sait pas lire : la photo part telle quelle.
                }

                if (ready.size > photoMaxBytes) {
                    say(`Une photo est trop lourde (${formatBytes(ready.size)}) : ${formatBytes(photoMaxBytes)} au plus.`);
                    continue;
                }

                state.photos.push({ file: ready, url: URL.createObjectURL(ready) });
            }
        } finally {
            state.busy = false;
            submit.disabled = false;
        }

        sync();

        if (refused > 0) {
            say(`${maxPhotos} pages au plus par leçon : ${refused} photo${refused > 1 ? 's' : ''} non ajoutée${refused > 1 ? 's' : ''}.`);
        } else if (status.textContent === 'Préparation des photos…') {
            say('');
        }
    };

    const addPdf = (file) => {
        if (!file) {
            return;
        }

        if (file.size > pdfMaxBytes) {
            say(`Ce PDF est trop lourd (${formatBytes(file.size)}) : ${formatBytes(pdfMaxBytes)} au plus.`);

            return;
        }

        state.photos.forEach((photo) => URL.revokeObjectURL(photo.url));
        const hadPhotos = state.photos.length > 0;
        state.photos = [];
        state.pdf = file;
        sync();
        say(hadPhotos ? 'Les photos ont été retirées : une leçon est faite soit de photos, soit d\'un PDF.' : '');
    };

    // Photo prise ou choisie : on ajoute, puis on vide le champ pour pouvoir rechoisir le même fichier.
    for (const input of [camera, gallery]) {
        input.addEventListener('change', async () => {
            const files = [...(input.files ?? [])];
            input.value = '';
            await addPhotos(files);
        });
    }

    pdfInput.addEventListener('change', () => {
        const file = pdfInput.files?.[0];
        pdfInput.value = '';
        addPdf(file);
    });

    form.addEventListener('submit', (event) => {
        if (state.busy || (state.photos.length === 0 && !state.pdf)) {
            event.preventDefault();

            if (!state.busy) {
                say('Ajoutez d\'abord une photo ou un PDF.');
            }

            return;
        }

        submit.disabled = true;
        submit.dataset.label ??= submit.textContent;
        submit.textContent = 'Lecture de votre leçon…';
        waiting.hidden = false;
    });

    // Retour arrière vers la page gardée en mémoire : le bouton redevient utilisable.
    window.addEventListener('pageshow', () => {
        submit.disabled = false;
        submit.textContent = submit.dataset.label ?? submit.textContent;
        waiting.hidden = true;
    });

    render();
}
