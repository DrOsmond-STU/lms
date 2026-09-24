// Sakelar tema (terang/gelap/ikuti sistem). Preferensi disimpan di cookie non-rahasia agar
// server merender atribut data-theme sejak awal (tanpa skrip inline, tanpa kedipan).
const COOKIE = 'stu_theme';

function store(mode) {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = mode === 'system'
        ? `${COOKIE}=; Path=/; Max-Age=0; SameSite=Lax${secure}`
        : `${COOKIE}=${mode}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
}

export function initThemeSwitch() {
    document.querySelectorAll('[data-theme-set]').forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.dataset.themeSet;
            if (!['light', 'dark', 'system'].includes(mode)) {
                return;
            }
            store(mode);
            if (mode === 'system') {
                document.documentElement.removeAttribute('data-theme');
            } else {
                document.documentElement.setAttribute('data-theme', mode);
            }
            document.querySelectorAll('[data-theme-set]').forEach((other) => {
                other.setAttribute('aria-pressed', String(other.dataset.themeSet === mode));
            });
        });
    });
}
