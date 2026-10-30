/*
 * « Mon espace », carte « Heure de mes séances » : abonner (ou désabonner) CE téléphone aux rappels de séance.
 * Le service worker du site (public/sw.js) affiche la notification et ouvre la séance au toucher. Rien n'est
 * envoyé ailleurs qu'à notre serveur ; la clé publique VAPID est publique.
 */

import { keyToBytes } from './admin-alerts.js';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function post(url, method, body) {
    const response = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Réponse ${response.status}`);
    }
}

function init(root) {
    const enable = root.querySelector('[data-reminders-enable]');
    const on = root.querySelector('[data-reminders-on]');
    const disable = root.querySelector('[data-reminders-disable]');
    const note = root.querySelector('[data-reminders-note]');
    root.hidden = false;

    const say = (text) => {
        note.textContent = text;
        note.hidden = text === '';
    };

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        enable.hidden = true;
        say('Ce navigateur ne sait pas recevoir de notifications : vous recevrez vos rappels par e-mail. Sur iPhone, installez d\'abord l\'application.');

        return;
    }

    const show = (subscribed) => {
        enable.hidden = subscribed;
        on.hidden = !subscribed;
    };

    const refresh = async () => {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        show(subscription !== null);

        if (Notification.permission === 'denied') {
            say('Les notifications sont bloquées pour ce site. Autorisez-les dans les réglages du téléphone, puis revenez ici.');
        }

        return subscription;
    };

    enable.addEventListener('click', async () => {
        say('');

        try {
            if (await Notification.requestPermission() !== 'granted') {
                say('Permission refusée : vous recevrez vos rappels par e-mail seulement.');

                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keyToBytes(root.dataset.publicKey),
            });
            await post(root.dataset.subscribeUrl, 'POST', subscription.toJSON());
            show(true);
        } catch {
            say('Impossible d\'activer les rappels sur ce téléphone. Réessayez dans un instant.');
        }
    });

    disable.addEventListener('click', async () => {
        say('');

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await post(root.dataset.unsubscribeUrl, 'DELETE', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }

            show(false);
        } catch {
            say('Impossible d\'arrêter les rappels pour le moment. Réessayez.');
        }
    });

    refresh().catch(() => {});
}

const root = document.querySelector('[data-reminders]');

if (root) {
    init(root);
}
