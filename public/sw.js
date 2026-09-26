// Service worker STU LMS: menampilkan Web Push dan membuka tautan saat notifikasi diklik.
self.addEventListener('push', (event) => {
    let data = { title: 'STU LMS', body: '', url: '/notifikasi' };
    try { data = Object.assign(data, event.data ? event.data.json() : {}); } catch { /* payload bukan JSON */ }
    event.waitUntil(self.registration.showNotification(data.title, { body: data.body, data: { url: data.url }, icon: '/favicon.ico', badge: '/favicon.ico' }));
});
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/notifikasi';
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        for (const client of clients) { if ('focus' in client) { client.navigate(url); return client.focus(); } }
        return self.clients.openWindow(url);
    }));
});
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
