import { sendJson } from './http';

// Heartbeat posisi video tiap 15 detik selama diputar (FR-CNT-005). Server memvalidasi
// kewajaran kemajuan (SEC-EXAM-17), jadi klien hanya melaporkan posisi.
export function initVideoProgress() {
    const video = document.querySelector('video[data-heartbeat-url], audio[data-heartbeat-url]');
    if (!video) return;

    const url = video.dataset.heartbeatUrl;
    const statusEl = document.querySelector('[data-video-status]');
    let lastSent = 0;

    const send = async () => {
        lastSent = Date.now();
        try {
            const response = await sendJson(url, 'POST', { position: Math.floor(video.currentTime) });
            if (!response.ok) return;
            const data = await response.json();
            if (data.completed && statusEl) {
                statusEl.textContent = 'Selesai ✓';
                statusEl.classList.add('text-emerald-700');
            }
        } catch {
            // Abaikan; heartbeat berikutnya akan mencoba lagi.
        }
    };

    setInterval(() => {
        if (!video.paused && !video.ended && Date.now() - lastSent >= 15000) send();
    }, 5000);
    video.addEventListener('pause', send);
    video.addEventListener('ended', send);
}

// Ping aktivitas lesson non-media tiap 30 detik selama tab terlihat (durasi belajar).
export function initLessonPing() {
    const root = document.querySelector('[data-lesson-ping-url]');
    if (!root) return;
    const url = root.dataset.lessonPingUrl;
    setInterval(() => {
        if (document.visibilityState === 'visible') sendJson(url, 'POST', {}).catch(() => {});
    }, 30000);
}
