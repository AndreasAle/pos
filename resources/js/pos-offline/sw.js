/*
 * Yoka POS — service worker for the offline register.
 * Served by PosServiceWorkerController (route /pos-sw.js); the two quoted
 * placeholders below are replaced with this build's version and asset list.
 *
 * What it does, and nothing more:
 *  - keeps the last good copy of /pos, so the register opens without internet;
 *  - keeps the build assets, Alpine, fonts and product images it needs;
 *  - never caches API calls, form posts, or any back-office page.
 * Sales made offline live in IndexedDB (resources/js/pos-offline), not here.
 */
const VERSION = '__SW_VERSION__';
const PRECACHE = '__PRECACHE__';

const SHELL = 'yoka-pos-shell';      // the /pos page; survives deploys on purpose
const BUILD = 'yoka-pos-build';      // hashed Vite files; names never collide
const RUNTIME = 'yoka-pos-runtime';  // Alpine CDN, fonts, product images
const SHELL_KEY = '/pos';
const NAV_TIMEOUT_MS = 4000;
const RUNTIME_MAX = 150;

const CDN_HOSTS = ['cdn.jsdelivr.net', 'fonts.bunny.net'];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(BUILD);
        // One missing file must not abort the whole install.
        await Promise.all(PRECACHE.map((url) => cache.add(url).catch(() => null)));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = [SHELL, BUILD, RUNTIME];
        for (const key of await caches.keys()) {
            if (key.startsWith('yoka-pos-') && !keep.includes(key)) await caches.delete(key);
        }
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    const msg = event.data || {};
    if (msg.type === 'version') {
        event.source?.postMessage({ type: 'version', version: VERSION });
    }
    // Sent when a cashier signs out: the cached register shows their name.
    if (msg.type === 'forget-shell') {
        event.waitUntil(caches.open(SHELL).then((c) => c.delete(SHELL_KEY)));
    }
    // Sent by the register once the worker is active: cache it and its CDN
    // files now instead of waiting for the next visit.
    if (msg.type === 'warm') {
        event.waitUntil(warm(Array.isArray(msg.urls) ? msg.urls : []));
    }
});

async function warm(urls) {
    try {
        const res = await fetch(SHELL_KEY, { credentials: 'include', redirect: 'follow' });
        if (res.ok && !res.redirected && new URL(res.url).pathname === SHELL_KEY) {
            await (await caches.open(SHELL)).put(SHELL_KEY, res);
        }
    } catch (e) { /* offline already; nothing to warm */ }

    const runtime = await caches.open(RUNTIME);
    for (const url of urls) {
        try {
            if (!CDN_HOSTS.includes(new URL(url).hostname) || await runtime.match(url)) continue;
            const res = await fetch(url, { mode: 'no-cors' });
            if (res.ok || res.type === 'opaque') await runtime.put(url, res);
        } catch (e) { /* skip */ }
    }
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    if (url.origin === self.location.origin) {
        if (req.mode === 'navigate') {
            if (url.pathname === '/pos' || url.pathname === '/kasir') {
                event.respondWith(navigate(req, url));
            }
            return;
        }
        if (url.pathname.startsWith('/build/')) {
            event.respondWith(cacheFirst(req, BUILD));
            return;
        }
        if (url.pathname.startsWith('/storage/') || url.pathname.startsWith('/images/')) {
            event.respondWith(cacheFirst(req, RUNTIME, true));
        }
        return;
    }

    if (CDN_HOSTS.includes(url.hostname)) {
        event.respondWith(cacheFirst(req, RUNTIME, true));
    }
});

/**
 * Network first for the register, with a short timeout: a weak signal that
 * never answers must not leave the cashier staring at a spinner.
 */
async function navigate(req, url) {
    const cache = await caches.open(SHELL);
    try {
        const res = await withTimeout(fetch(req), NAV_TIMEOUT_MS);
        const finalPath = new URL(res.url || req.url).pathname;

        if (res.ok && !res.redirected && finalPath === '/pos') {
            await cache.put(SHELL_KEY, res.clone());
        } else if (res.ok && finalPath === '/kasir') {
            // Signed out (or session expired): the PIN screen is showing, so the
            // cached register of whoever was here before must go.
            await cache.delete(SHELL_KEY);
        }
        return res;
    } catch (e) {
        const cached = await cache.match(SHELL_KEY);
        if (cached) return cached;
        return offlinePage(url.pathname === '/kasir');
    }
}

async function cacheFirst(req, name, trim = false) {
    const cache = await caches.open(name);
    const hit = await cache.match(req);
    if (hit) return hit;
    try {
        const res = await fetch(req);
        // Opaque (no-cors CDN script) responses have status 0 but are usable.
        if (res.ok || res.type === 'opaque') {
            await cache.put(req, res.clone());
            if (trim) trimCache(cache);
        }
        return res;
    } catch (e) {
        return new Response('', { status: 504, statusText: 'offline' });
    }
}

async function trimCache(cache) {
    const keys = await cache.keys();
    for (let i = 0; i < keys.length - RUNTIME_MAX; i++) await cache.delete(keys[i]);
}

function withTimeout(promise, ms) {
    return new Promise((resolve, reject) => {
        const t = setTimeout(() => reject(new Error('timeout')), ms);
        promise.then((v) => { clearTimeout(t); resolve(v); }, (e) => { clearTimeout(t); reject(e); });
    });
}

function offlinePage(signedOut) {
    const body = signedOut
        ? 'Login kasir butuh internet. Sambungkan Wi-Fi atau data, lalu coba lagi.'
        : 'Kasir belum pernah dibuka di perangkat ini saat online, jadi belum bisa dipakai offline. Sambungkan internet sekali, buka kasir, lalu mode offline siap.';
    const html = `<!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline — Yoka POS</title>
<style>body{margin:0;font-family:system-ui,sans-serif;background:#F8FAFC;color:#0B1B3F;display:flex;min-height:100vh;align-items:center;justify-content:center}
.c{background:#fff;border-radius:12px;padding:32px;max-width:420px;box-shadow:0 1px 3px rgba(0,0,0,.08);text-align:center}
h1{font-size:20px;margin:0 0 8px}p{color:#475569;line-height:1.5}button{margin-top:16px;background:#16A34A;color:#fff;border:0;border-radius:10px;padding:12px 20px;font-size:16px;font-weight:700}</style>
</head><body><div class="c"><h1>Tidak ada koneksi internet</h1><p>${body}</p>
<button onclick="location.reload()">Coba lagi</button></div>
<script>addEventListener('online',()=>location.reload())</script></body></html>`;
    return new Response(html, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
