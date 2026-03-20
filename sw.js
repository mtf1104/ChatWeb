/**
 * ChatWeb - Service Worker
 * Optimización de carga y persistencia para PWA
 */

const CACHE_NAME = 'chatweb-cache-v1';
const ASSETS_TO_CACHE = [
    'index.html',
    'style.css',
    'index.php?action=manifest',
    'https://cdn-icons-png.flaticon.com/512/4712/4712035.png'
];

// 1. Instalación: Guardar archivos estáticos en caché
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('ChatWeb: Archivos cacheados con éxito');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// 2. Activación: Limpiar versiones antiguas de caché
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            );
        })
    );
});

// 3. Estrategia: Network First (Priorizar red para datos frescos, usar caché si falla)
self.addEventListener('fetch', (event) => {
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});