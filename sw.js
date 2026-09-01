/* SignalMasterAi service worker: shows push notifications with the site closed. */

self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) {}
  e.waitUntil(self.registration.showNotification(d.title || 'SignalMasterAi', {
    body: d.body || '',
    icon: 'assets/brand/icon-192.png',
    badge: 'assets/brand/icon-192.png',
    tag: d.tag || 'sma-signal',
    data: { url: d.url || './' }
  }));
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) {
      if ('focus' in list[i]) return list[i].focus();
    }
    return clients.openWindow(e.notification.data && e.notification.data.url ? e.notification.data.url : './');
  }));
});
