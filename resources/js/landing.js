// Beranda: slider & filter program. Tanpa JavaScript, slide pertama tampil statis dan
// filter bekerja lewat formulir GET biasa (disaring di server dengan aturan yang sama).

export function initSlider() {
    const root = document.querySelector('[data-slider]');
    const slides = root ? [...root.querySelectorAll('[data-slide]')] : [];
    if (slides.length < 2) {
        return;
    }
    const dots = [...root.querySelectorAll('[data-slide-to]')];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let index = 0;
    let timer = null;

    const show = (next) => {
        index = (next + slides.length) % slides.length;
        slides.forEach((slide, n) => {
            const active = n === index;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', String(!active));
            slide.inert = !active;
        });
        dots.forEach((dot, n) => dot.setAttribute('aria-current', String(n === index)));
    };
    const stop = () => {
        window.clearInterval(timer);
        timer = null;
    };
    const start = () => {
        stop();
        if (!reduceMotion && !document.hidden) {
            timer = window.setInterval(() => show(index + 1), 7000);
        }
    };

    root.querySelector('[data-slide-prev]')?.addEventListener('click', () => { show(index - 1); start(); });
    root.querySelector('[data-slide-next]')?.addEventListener('click', () => { show(index + 1); start(); });
    dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.slideTo)); start(); }));
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    start();
}

const FILTER_KEYS = ['jenis', 'topik', 'harga', 'jadwal', 'durasi'];

function matches(card, filters) {
    const data = card.dataset;
    const month = data.jadwal === '' ? null : Number(data.jadwal);
    return Object.entries(filters).every(([key, value]) => {
        switch (key) {
            case 'jenis': return data.jenis === value;
            case 'topik': return data.topik.split('|').includes(value);
            case 'harga': return data.harga === value;
            case 'durasi': return data.durasi === value;
            case 'jadwal':
                if (month === null) return false;
                if (value === 'bulan-ini') return month === 0;
                if (value === 'bulan-depan') return month === 1;
                return month <= 2;
            default: return true;
        }
    });
}

export function initProgramFilter() {
    const form = document.querySelector('[data-program-filter]');
    if (!form) {
        return;
    }
    const cards = [...document.querySelectorAll('[data-program-card]')];
    const counter = document.querySelector('[data-program-count]');
    const empty = document.querySelector('[data-program-empty]');
    const pills = [...form.querySelectorAll('[data-jenis-pill]')];
    form.querySelector('[data-filter-submit]')?.classList.add('hidden');

    const apply = () => {
        const filters = {};
        FILTER_KEYS.forEach((key) => {
            const value = form.elements[key]?.value ?? '';
            if (value !== '') filters[key] = value;
        });
        let shown = 0;
        cards.forEach((card) => {
            const ok = matches(card, filters);
            card.hidden = !ok;
            if (ok) shown += 1;
        });
        if (counter) counter.textContent = String(shown);
        if (empty) empty.hidden = shown > 0;
        pills.forEach((pill) => pill.setAttribute('aria-pressed', String(pill.dataset.jenisPill === (filters.jenis ?? ''))));

        const url = new URL(window.location.href);
        FILTER_KEYS.forEach((key) => (filters[key] ? url.searchParams.set(key, filters[key]) : url.searchParams.delete(key)));
        url.hash = 'pelatihan';
        window.history.replaceState(null, '', url);
    };

    form.addEventListener('change', apply);
    form.addEventListener('submit', (event) => { event.preventDefault(); apply(); });
    pills.forEach((pill) => pill.addEventListener('click', (event) => {
        event.preventDefault();
        form.elements.jenis.value = pill.dataset.jenisPill;
        apply();
    }));
    form.querySelector('[data-filter-reset]')?.addEventListener('click', (event) => {
        event.preventDefault();
        FILTER_KEYS.forEach((key) => { if (form.elements[key]) form.elements[key].value = ''; });
        apply();
    });
}
