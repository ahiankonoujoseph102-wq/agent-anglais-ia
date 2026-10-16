/*
 * Service worker du professeur 3D : garde en cache les fichiers de
 * /teacher3d/ (bibliothèque 3D, lecture labiale, modèle) pour qu'ils ne
 * soient téléchargés qu'une fois. Les adresses portent l'empreinte du
 * contenu (?v=…) : une nouvelle version remplace l'ancienne.
 * Toutes les autres requêtes du site passent sans intervention.
 */

const CACHE = 'teacher3d';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== self.location.origin || !url.pathname.includes('/teacher3d/') || !url.search) {
        return;
    }

    event.respondWith(caches.open(CACHE).then(async (cache) => {
        const hit = await cache.match(event.request);

        if (hit) {
            return hit;
        }

        const response = await fetch(event.request);

        if (response.ok) {
            // Une seule version de chaque fichier.
            for (const request of await cache.keys()) {
                if (new URL(request.url).pathname === url.pathname) {
                    await cache.delete(request);
                }
            }

            await cache.put(event.request, response.clone());
        }

        return response;
    }));
});
