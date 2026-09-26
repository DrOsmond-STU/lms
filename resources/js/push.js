import { sendJson } from './http';

// Langganan Web Push (RFC 8030): daftarkan service worker, minta izin, kirim langganan ke server.
function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export function initPush() {
    const panel = document.querySelector('[data-push-panel]');
    if (!panel) return;
    const button = panel.querySelector('[data-push-enable]');
    const status = panel.querySelector('[data-push-status]');
    const say = (text) => { if (status) status.textContent = text; };
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        say('Peramban ini tidak mendukung notifikasi push.');
        if (button) button.disabled = true;
        return;
    }
    button?.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') { say('Izin notifikasi ditolak. Ubah di pengaturan situs peramban.'); button.disabled = false; return; }
            const registration = await navigator.serviceWorker.register(panel.dataset.swUrl, { scope: '/' });
            await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(panel.dataset.vapidKey) });
            const response = await sendJson(panel.dataset.subscribeUrl, 'POST', subscription.toJSON());
            if (!response.ok) throw new Error('HTTP ' + response.status);
            say('Perangkat ini terdaftar. Memuat ulang…');
            window.location.reload();
        } catch (error) {
            say('Gagal mengaktifkan: ' + (error && error.message ? error.message : error));
            button.disabled = false;
        }
    });
}
