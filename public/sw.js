// Service Worker for Caching Static Assets
const CACHE_NAME = 'membership-app-v3';
const STATIC_CACHE_NAME = 'membership-app-static-v3';
const IMAGE_CACHE_NAME = 'membership-app-images-v3';

// Assets to cache immediately — no HTML pages; they contain dynamic server data (Ziggy routes etc.)
const STATIC_ASSETS = [
    '/css/app.css',
    '/js/app.js',
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.log('Cache install error:', err);
            });
        })
    );
    self.skipWaiting();
});

// Activate event - clean up all old caches
self.addEventListener('activate', (event) => {
    const validCaches = [CACHE_NAME, STATIC_CACHE_NAME, IMAGE_CACHE_NAME];
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((cacheName) => !validCaches.includes(cacheName))
                    .map((cacheName) => caches.delete(cacheName))
            );
        })
    );
    return self.clients.claim();
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip cross-origin requests
    if (url.origin !== location.origin) {
        return;
    }

    // Never cache HTML navigation requests — they contain dynamic server data (Ziggy routes,
    // CSRF tokens, shared Inertia props) that must always be fresh from the server.
    if (request.mode === 'navigate' || request.destination === 'document') {
        return;
    }

    // Handle different types of requests
    if (request.destination === 'image') {
        // Cache images with cache-first strategy
        event.respondWith(
            caches.open(IMAGE_CACHE_NAME).then((cache) => {
                return cache.match(request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    return fetch(request).then((networkResponse) => {
                        if (networkResponse.ok) {
                            cache.put(request, networkResponse.clone());
                        }
                        return networkResponse;
                    });
                });
            })
        );
    } else if (
        request.destination === 'script' ||
        request.destination === 'style' ||
        url.pathname.startsWith('/css/') ||
        url.pathname.startsWith('/js/')
    ) {
        // Cache CSS/JS with network-first strategy
        event.respondWith(
            caches.open(STATIC_CACHE_NAME).then((cache) => {
                return fetch(request)
                    .then((networkResponse) => {
                        if (networkResponse.ok) {
                            cache.put(request, networkResponse.clone());
                        }
                        return networkResponse;
                    })
                    .catch(() => {
                        return cache.match(request).then((cached) => {
                            return cached || Response.error();
                        });
                    });
            })
        );
    } else {
        // For other requests, use network-first strategy (do NOT cache Inertia XHR responses)
        event.respondWith(
            fetch(request)
                .catch(() => {
                    return caches.match(request).then((cached) => {
                        return cached || Response.error();
                    });
                })
        );
    }
});
