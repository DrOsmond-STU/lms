// STU LMS — perilaku klien progresif. Tanpa skrip inline (CSP nonce + strict-dynamic);
// semua fitur tetap berfungsi tanpa JavaScript melalui formulir biasa.
import { initExam } from './exam';
import { initVideoProgress } from './video';
import { initConfirmations } from './confirm';
import { initSidebar } from './sidebar';
import { initThemeSwitch } from './theme';

document.addEventListener('DOMContentLoaded', () => {
    initConfirmations();
    initSidebar();
    initThemeSwitch();
    initExam();
    initVideoProgress();
});
