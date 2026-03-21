/* global self */

self.addEventListener('push', (event) => {
    let payload = { title: 'Benachrichtigung', body: '', url: '/', icon: null };
    try {
        if (event.data) {
            const parsed = event.data.json();
            if (parsed && typeof parsed === 'object') {
                payload = { ...payload, ...parsed };
            }
        }
    } catch {
        if (event.data) {
            payload.body = event.data.text();
        }
    }

    const title = payload.title || 'Benachrichtigung';
    const options = {
        body: payload.body || '',
        icon: payload.icon || undefined,
        badge: payload.badge || undefined,
        tag: payload.tag || 'default',
        data: { url: payload.data?.url || payload.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        }),
    );
});
