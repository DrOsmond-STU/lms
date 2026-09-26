import { sendJson } from './http';

// Pengerjaan ujian: timer dari sisa detik yang dihitung SERVER (bukan jam klien), autosave per
// jawaban, auto-kumpul saat waktu habis, dan indikator integritas (hanya dicatat, bukan vonis).
export function initExam() {
    const root = document.querySelector('[data-exam]');
    if (!root) return;

    const form = root.querySelector('form[data-exam-form]');
    const answerUrl = root.dataset.answerUrl;
    const integrityUrl = root.dataset.integrityUrl;
    const timerEl = root.querySelector('[data-exam-timer]');
    const statusEl = root.querySelector('[data-exam-status]');
    let secondsLeft = parseInt(root.dataset.secondsLeft ?? '0', 10);
    const startedAt = Date.now();
    const initial = secondsLeft;
    let submitting = false;

    const setStatus = (text) => {
        if (statusEl) statusEl.textContent = text;
    };

    const render = () => {
        const left = Math.max(0, initial - Math.floor((Date.now() - startedAt) / 1000));
        secondsLeft = left;
        const m = String(Math.floor(left / 60)).padStart(2, '0');
        const s = String(left % 60).padStart(2, '0');
        if (timerEl) {
            timerEl.textContent = `${m}:${s}`;
            timerEl.classList.toggle('text-rose-700', left <= 60);
        }
        if (left <= 0 && !submitting) {
            submitting = true;
            setStatus('Waktu habis — mengumpulkan jawaban…');
            form?.submit();
        }
    };
    render();
    setInterval(render, 1000);

    const save = async (question) => {
        const inputs = root.querySelectorAll(`[data-question="${question}"]`);
        const options = [];
        const pairs = {};
        let text = null;
        inputs.forEach((input) => {
            if ((input.type === 'radio' || input.type === 'checkbox') && input.checked) options.push(input.value);
            if (input.tagName === 'TEXTAREA' || input.type === 'text') text = input.value;
            if (input.tagName === 'SELECT' && input.dataset.pairLeft) pairs[input.dataset.pairLeft] = input.value;
        });
        setStatus('Menyimpan…');
        try {
            const response = await sendJson(answerUrl, 'PUT', { question, options, text, pairs });
            if (response.status === 409) {
                setStatus('Waktu habis. Jawaban tidak dapat diubah.');
                return;
            }
            if (!response.ok) throw new Error(String(response.status));
            const data = await response.json();
            setStatus(`Tersimpan ${new Date().toLocaleTimeString('id-ID')}`);
            const marker = root.querySelector(`[data-answered="${question}"]`);
            marker?.classList.add('bg-accent-500', 'text-white');
            if (typeof data.seconds_left === 'number' && Math.abs(data.seconds_left - secondsLeft) > 5) {
                window.location.reload();
            }
        } catch {
            setStatus('Gagal menyimpan — periksa koneksi. Jawaban tetap terkirim saat Anda menekan Kumpulkan.');
        }
    };

    const timers = {};
    root.querySelectorAll('[data-question]').forEach((input) => {
        const question = input.dataset.question;
        const handler = () => {
            clearTimeout(timers[question]);
            timers[question] = setTimeout(() => save(question), input.tagName === 'TEXTAREA' || input.type === 'text' ? 800 : 0);
        };
        input.addEventListener(input.tagName === 'TEXTAREA' || input.type === 'text' ? 'input' : 'change', handler);
    });

    const flag = (event) => {
        if (!submitting) sendJson(integrityUrl, 'POST', { event }).catch(() => {});
    };
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flag('blur');
    });
    root.addEventListener('copy', () => flag('copy'));
    root.addEventListener('paste', () => flag('paste'));

    form?.addEventListener('submit', () => {
        submitting = true;
    });
}
