/*
|--------------------------------------------------------------------------
| cOllect_Pay Mobile - Service Worker Premium
|--------------------------------------------------------------------------
| Version : v150
| Cache complet PWA + Dashboard Premium + GPS obligatoire
| + Cartographie recettes + Carte interactive GPS
|--------------------------------------------------------------------------
*/

const CACHE_NAME = 'collect-pay-pwa-v150';

const APP_SHELL = [

    './',
    './index.html',
    './login.html',
    './dashboard.html',
    './rapport.html',
    './tickets.html',
    './agents.html',
    './backup.html',
    './recettes_map.html',
    './carte_interactive.html',

    './manifest.json',

    './assets/css/app.css',
    './assets/css/dashboard.css',

    './assets/js/indexeddb.js',
    './assets/js/auth.js',
    './assets/js/login.js',
    './assets/js/app.js',
    './assets/js/gps.js',
    './assets/js/sync.js',
    './assets/js/ticket.js',
    './assets/js/tickets.js',
    './assets/js/rapport.js',
    './assets/js/dashboard.js',
    './assets/js/agents.js',
    './assets/js/backup.js',
    './assets/js/recettes_map.js',
    './assets/js/carte_interactive.js'
];

/*
|--------------------------------------------------------------------------
| INSTALLATION
|--------------------------------------------------------------------------
*/

self.addEventListener('install', event => {

    self.skipWaiting();

    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then(cache => {
                console.log('Cache installé :', CACHE_NAME);
                return cache.addAll(APP_SHELL);
            })
            .catch(error => {
                console.error('Erreur installation cache :', error);
            })
    );

});

/*
|--------------------------------------------------------------------------
| ACTIVATION
|--------------------------------------------------------------------------
*/

self.addEventListener('activate', event => {

    event.waitUntil(
        caches
            .keys()
            .then(keys => {
                return Promise.all(
                    keys.map(key => {
                        if (key !== CACHE_NAME) {
                            console.log('Suppression ancien cache :', key);
                            return caches.delete(key);
                        }
                    })
                );
            })
            .then(() => self.clients.claim())
    );

});

/*
|--------------------------------------------------------------------------
| FETCH
|--------------------------------------------------------------------------
*/

self.addEventListener('fetch', event => {

    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    /*
    |--------------------------------------------------------------------------
    | API PHP : jamais en cache.
    |--------------------------------------------------------------------------
    */

    if (url.pathname.includes('/api/')) {

        event.respondWith(
            fetch(request).catch(() => {
                return new Response(
                    JSON.stringify({
                        success: false,
                        offline: true,
                        message: 'API indisponible hors connexion.'
                    }),
                    {
                        headers: {
                            'Content-Type': 'application/json;charset=UTF-8'
                        }
                    }
                );
            })
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | CACHE FIRST pour pages et fichiers statiques
    |--------------------------------------------------------------------------
    */

    event.respondWith(
        caches.match(request).then(cached => {

            if (cached) {
                return cached;
            }

            return fetch(request)
                .then(response => {

                    if (!response || response.status !== 200) {
                        return response;
                    }

                    const responseClone = response.clone();

                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(request, responseClone);
                    });

                    return response;
                })
                .catch(() => {

                    if (request.mode === 'navigate') {

                        if (url.pathname.includes('dashboard')) {
                            return caches.match('./dashboard.html');
                        }

                        if (url.pathname.includes('login')) {
                            return caches.match('./login.html');
                        }

                        if (url.pathname.includes('recettes_map')) {
                            return caches.match('./recettes_map.html');
                        }

                        if (url.pathname.includes('carte_interactive')) {
                            return caches.match('./carte_interactive.html');
                        }

                        return caches.match('./dashboard.html')
                            || caches.match('./index.html');
                    }

                    return caches.match('./index.html');
                });
        })
    );

});

/*
|--------------------------------------------------------------------------
| MESSAGE : Mise à jour immédiate
|--------------------------------------------------------------------------
*/

self.addEventListener('message', event => {

    if (
        event.data &&
        event.data.type === 'SKIP_WAITING'
    ) {
        self.skipWaiting();
    }

});
