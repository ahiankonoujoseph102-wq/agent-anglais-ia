/*
 * Page « Alertes » de l'administration : abonner (ou désabonner) CE téléphone aux notifications de paiement.
 * Le service worker du site (public/sw.js) affiche les notifications et ouvre le paiement au toucher.
 * Rien n'est envoyé ailleurs qu'à notre serveur ; la clé publique VAPID est publique.
 */

const csrf = typeof document !== 'undefined' ? (document.querySelector('meta[name="csrf-token"]')?.content ?? '') : '';

export function keyToBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(padded);

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

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
    const enable = root.querySelector('[data-alerts-enable]');
    const disable = root.querySelector('[data-alerts-disable]');
    const note = root.querySelector('[data-alerts-note]');
    const publicKey = root.dataset.publicKey;

    if (!enable || !disable) {
        return;
    }

    const say = (text) => {
        note.textContent = text;
        note.hidden = text === '';
    };

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        say('Ce navigateur ne sait pas recevoir de notifications. Sur Android, utilisez Chrome ; sur iPhone, ajoutez d\'abord le site à l\'écran d\'accueil.');

        return;
    }

    const refresh = async () => {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        enable.hidden = subscription !== null;
        disable.hidden = subscription === null;

        if (Notification.permission === 'denied') {
            say('Les notifications sont bloquées pour ce site. Dans les réglages du téléphone, autorisez les notifications de l\'application, puis revenez ici.');
        }

        return subscription;
    };

    enable.addEventListener('click', async () => {
        say('');

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                say('Permission refusée. Autorisez les notifications pour recevoir les alertes.');

                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keyToBytes(publicKey),
            });
            await post(root.dataset.subscribeUrl, 'POST', subscription.toJSON());
            window.location.reload();
        } catch (error) {
            say('Impossible d\'activer les alertes sur ce téléphone. Réessayez dans un instant.');
        }
    });

    disable.addEventListener('click', async () => {
        say('');

        try {
            const subscription = await refresh();

            if (subscription) {
                await post(root.dataset.unsubscribeUrl, 'DELETE', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }

            window.location.reload();
        } catch (error) {
            say('Impossible d\'arrêter les alertes pour le moment. Réessayez.');
        }
    });

    refresh().catch(() => {});
}

const root = typeof document !== 'undefined' ? document.querySelector('[data-alerts]') : null;

if (root) {
    init(root);
}
