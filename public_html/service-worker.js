self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    event.waitUntil((async () => {
        let payload = {};

        if (event.data) {
            try {
                payload = event.data.json();
            } catch {
                payload = { body: event.data.text() };
            }
        }

        let title = String(payload.title || '').trim();
        let body = String(payload.body || '').trim();
        let link = String(payload.link || '').trim();
        let notificationType = String(payload.type || '').trim().toLowerCase();

        if (!title || !body) {
            try {
                const response = await fetch('/notifications', {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (response.ok) {
                    const data = await response.json();
                    const notifications = Array.isArray(data?.notifications) ? data.notifications : [];
                    const firstUnread = notifications.find((item) => Number(item?.read) === 0) || notifications[0] || null;
                    if (firstUnread) {
                        title = String(firstUnread.title || title || 'New message').trim();
                        body = String(firstUnread.message || body || 'You have a new notification in Prologue.').trim();
                        link = String(firstUnread.link || link || '/').trim();
                        notificationType = String(firstUnread.type || notificationType || '').trim().toLowerCase();
                    }
                }
            } catch {
                // Fallback title/body below.
            }
        }

        if (!title) {
            title = 'New message';
        }
        if (!body) {
            body = 'You have a new notification in Prologue.';
        }
        if (!link) {
            link = '/';
        }

        await self.registration.showNotification(title, {
            body,
            icon: '/assets/img/favicon.png',
            badge: '/assets/img/favicon.png',
            data: {
                link,
                type: notificationType
            }
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const title = String(event.notification?.title || '').trim().toLowerCase();
    const notificationType = String(event.notification?.data?.type || '').trim().toLowerCase();
    const shouldJoinCall = notificationType === 'poke' || title === 'call poke';

    const target = new URL(String(event.notification?.data?.link || '/'), self.location.origin);
    if (shouldJoinCall) {
        if (target.searchParams.get('join_call') !== '1') {
            target.searchParams.set('join_call', '1');
        }
        if (!target.searchParams.get('join_source')) {
            target.searchParams.set('join_source', 'poke');
        }
    }

    const targetPath = target.pathname + target.search + target.hash;
    const targetUrl = new URL(targetPath, self.location.origin).href;

    event.waitUntil((async () => {
        const clientList = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        });

        for (const client of clientList) {
            if (!client || !('url' in client)) {
                continue;
            }

            const clientUrl = String(client.url || '');
            if (clientUrl.startsWith(self.location.origin)) {
                await client.focus();
                client.navigate(targetUrl);
                return;
            }
        }

        await self.clients.openWindow(targetUrl);
    })());
});
