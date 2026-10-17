/*
 * Service worker unique du site (portée « / »).
 *
 * Ce qu'il met en cache : UNIQUEMENT des fichiers statiques publics, rangés
 * dans les dossiers de STATIC_PATHS (feuilles de style, scripts, police,
 * icônes, images, professeur 3D). Les adresses versionnées (?v=empreinte)
 * remplacent l'ancienne version du même fichier.
 *
 * Ce qu'il ne met JAMAIS en cache : les pages (espace personnel, paiements,
 * programme, administration…), les réponses JSON des séances (jetons), les
 * envois de formulaires. Une page demandée hors connexion affiche la page
 * « Vous êtes hors connexion » (seule page gardée, sans donnée personnelle).
 */

const CACHE = 'static-v1';
const OFFLINE_URL = '/hors-connexion';

// Dossiers de public/ dont les fichiers peuvent être mis en cache.
const STATIC_PATHS = ['/css/', '/js/', '/fonts/', '/icons/', '/images/', '/teacher3d/'];
// Fichiers dont le nom ne change jamais de contenu : gardés même sans ?v=.
const IMMUTABLE_PATHS = ['/fonts/', '/icons/'];

function isStatic(url) {
    return url.origin === self.location.origin
        && STATIC_PATHS.some((path) => url.pathname.startsWith(path))
        // Le modèle 3D a déjà son propre cache (teacher-3d.js) : pas de double copie.
        && !url.pathname.startsWith('/teacher3d/models/')
        && (url.search !== '' || IMMUTABLE_PATHS.some((path) => url.pathname.startsWith(path)));
}

// Page hors connexion et fichiers qu'elle utilise.
async function cacheOfflinePage(cache) {
    const response = await fetch(OFFLINE_URL, { credentials: 'omit', cache: 'no-store' });

    if (!response.ok) {
        return;
    }

    const html = await response.clone().text();
    await cache.put(OFFLINE_URL, response);

    const assets = [...html.matchAll(/(?:href|src)="([^"]+)"/g)]
        .map((match) => new URL(match[1], self.location.origin))
        .filter(isStatic);

    await Promise.all(assets.map((url) => cache.add(url.href).catch(() => {})));
}

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then(cacheOfflinePage).catch(() => {}).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    // Le modèle 3D garde son propre cache (« teacher3d », géré par teacher-3d.js).
    event.waitUntil(self.clients.claim());
});

// Fichier statique : depuis le cache s'il y est, sinon réseau puis cache.
async function staticResponse(request, url) {
    const cache = await caches.open(CACHE);
    const hit = await cache.match(request);

    if (hit) {
        return hit;
    }

    const response = await fetch(request);

    if (response.ok && response.type === 'basic') {
        if (url.search) {
            // Une seule version de chaque fichier versionné.
            for (const old of await cache.keys()) {
                const oldUrl = new URL(old.url);

                if (oldUrl.pathname === url.pathname && oldUrl.search !== url.search) {
                    await cache.delete(old);
                }
            }
        }

        await cache.put(request, response.clone());
    }

    return response;
}

// Page : toujours le réseau (jamais de cache) ; hors connexion, la page d'information.
async function pageResponse(request) {
    try {
        return await fetch(request);
    } catch {
        const offline = await caches.match(OFFLINE_URL, { cacheName: CACHE });

        return offline ?? new Response('Vous êtes hors connexion.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return; // Formulaires, signaux et bilans des séances : jamais interceptés.
    }

    const url = new URL(request.url);

    if (request.mode === 'navigate') {
        event.respondWith(pageResponse(request));

        return;
    }

    if (isStatic(url)) {
        event.respondWith(staticResponse(request, url));
    }

    // Tout le reste (JSON des séances, captures de paiement, manifeste…) : réseau, sans cache.
});
