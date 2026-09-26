// STU LMS — perilaku klien progresif. Tanpa skrip inline (CSP nonce + strict-dynamic);
// semua fitur tetap berfungsi tanpa JavaScript melalui formulir biasa.
import { initExam } from './exam';
import { initLessonPing, initVideoProgress } from './video';
import { initConfirmations } from './confirm';
import { initProgramFilter, initSlider } from './landing';
import { initSidebar } from './sidebar';
import { initThemeSwitch } from './theme';
import { initPush } from './push';

document.addEventListener('DOMContentLoaded', () => {
    initConfirmations();
    initSidebar();
    initThemeSwitch();
    initSlider();
    initProgramFilter();
    initExam();
    initVideoProgress();
    initLessonPing();
    initPush();
});
