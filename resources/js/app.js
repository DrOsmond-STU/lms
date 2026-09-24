// STU LMS — perilaku klien progresif. Tanpa skrip inline (CSP nonce + strict-dynamic);
// semua fitur tetap berfungsi tanpa JavaScript melalui formulir biasa.
import { initExam } from './exam';
import { initVideoProgress } from './video';
import { initConfirmations } from './confirm';

document.addEventListener('DOMContentLoaded', () => {
    initConfirmations();
    initExam();
    initVideoProgress();
});
