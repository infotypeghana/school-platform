/**
 * SchoolMS Ghana — Service Worker
 *
 * Strategy:
 *  - Static assets (JS/CSS/fonts): Cache-First (immutable, versioned by Vite)
 *  - API calls:                    Network-Only  (always fresh)
 *  - HTML pages:                   Network-First with offline fallback
 */

const CACHE_NAME   = 'schoolms-v1';
const OFFLINE_PAGE = '/offline.html';

const PRECACHE_ASSETS = [
    OFFLINE_PAGE,
];

// ── Install: pre-cache offline fallback ──────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// ── Activate: clean up old caches ─────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch: routing strategy ───────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET and cross-origin requests
    if (request.method !== 'GET' || url.origin !== location.origin) return;

    // API calls — always network-only
    if (url.pathname.startsWith('/api/')) return;

    // Static assets (Vite build output) — cache-first
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // HTML pages — network-first with offline fallback
    if (request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithFallback(request));
        return;
    }
});

// ── Strategies ────────────────────────────────────────────────────────────────
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, response.clone());
    }
    return response;
}

async function networkFirstWithFallback(request) {
    try {
        const response = await fetch(request);
        return response;
    } catch {
        const offline = await caches.match(OFFLINE_PAGE);
        return offline || new Response('You are offline.', { status: 503 });
    }
}
