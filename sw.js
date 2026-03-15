self.addEventListener('fetch', function(event) {
    // Esto permite que la app funcione online
    event.respondWith(fetch(event.request));
});