// Laci navigasi di layar kecil.
export function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const opener = document.querySelector('[data-sidebar-open]');
    if (!sidebar || !opener) {
        return;
    }
    const setOpen = (open) => {
        sidebar.classList.toggle('-translate-x-full', !open);
        backdrop?.classList.toggle('hidden', !open);
        opener.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('overflow-hidden', open);
        if (open) {
            sidebar.querySelector('[data-sidebar-close]')?.focus();
        }
    };
    opener.addEventListener('click', () => setOpen(true));
    backdrop?.addEventListener('click', () => setOpen(false));
    sidebar.querySelector('[data-sidebar-close]')?.addEventListener('click', () => {
        setOpen(false);
        opener.focus();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && opener.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            opener.focus();
        }
    });
}
