/*
 * « Diagnostic des fournisseurs », bloc « Limites d'envoi » : deux essais réels sur le site en ligne. Un envoi de la
 * taille du plus gros PDF accepté par « Mes cours », puis une page qui attend aussi longtemps qu'une lecture. Le
 * résultat dit en clair ce qui passe et ce qui bloque (même message que l'élève verrait).
 */
import { messageFor, MESSAGES } from './long-submit.js';

const box = document.querySelector('[data-upload-check]');

if (box) {
    const result = box.querySelector('[data-upload-check-result]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const show = (text) => {
        result.hidden = false;
        result.textContent = text;
    };

    const post = async (url, body, timeoutMs) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeoutMs);
        const started = Date.now();

        try {
            const response = await fetch(url, { method: 'POST', body, signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token } });
            const data = await response.json().catch(() => null);

            return { status: response.status, data, seconds: Math.round((Date.now() - started) / 1000) };
        } catch {
            return { status: 0, data: null, timedOut: controller.signal.aborted, seconds: Math.round((Date.now() - started) / 1000) };
        } finally {
            clearTimeout(timer);
        }
    };

    box.querySelector('[data-upload-check-size]')?.addEventListener('click', async (event) => {
        event.currentTarget.disabled = true;
        show('Envoi en cours…');
        const body = new FormData();
        body.append('essai', new Blob([new Uint8Array(Number(box.dataset.bytes))], { type: 'application/pdf' }), 'essai.pdf');
        const answer = await post(box.dataset.sizeUrl, body, 120000);
        event.currentTarget.disabled = false;

        if (answer.data?.ok) {
            show(`Réussi : ${answer.data.label} reçus en ${answer.seconds} secondes. Un PDF de cette taille passe.`);
        } else {
            show(`Échec (code ${answer.status || 'aucun'}) : ${answer.data?.message ?? messageFor(answer.status, answer.timedOut) ?? MESSAGES.server}`);
        }
    });

    box.querySelector('[data-upload-check-wait]')?.addEventListener('click', async (event) => {
        event.currentTarget.disabled = true;
        show('Attente en cours… (gardez cette page ouverte)');
        const answer = await post(box.dataset.waitUrl, new FormData(), 150000);
        event.currentTarget.disabled = false;

        show(answer.data?.ok
            ? `Réussi : la page a attendu ${answer.data.seconds} secondes sans être coupée. Une longue lecture passe.`
            : `Échec après ${answer.seconds} secondes (code ${answer.status || 'aucun'}) : le serveur coupe avant la fin. L'élève verrait : « ${messageFor(answer.status, answer.timedOut) ?? MESSAGES.timeout} »`);
    });
}
