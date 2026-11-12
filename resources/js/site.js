// Public website: small Vue "islands" mounted into server-rendered pages, plus a few progressive enhancements.
import { createApp } from 'vue';
import LeadForm from './site/LeadForm.vue';

document.querySelectorAll('[data-island="lead-form"]').forEach((el) => {
    let cfg = {};
    try {
        cfg = JSON.parse(el.dataset.props || '{}');
    } catch (e) {
        return;
    }
    createApp(LeadForm, { cfg }).mount(el);
});

// Home finder: drop empty fields so the packages page gets a clean URL.
document.querySelectorAll('.hero-find').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('select, input').forEach((f) => {
            if (!f.value.trim()) f.disabled = true;
        });
        setTimeout(() => form.querySelectorAll('select, input').forEach((f) => (f.disabled = false)), 0);
    });
});

// Package page: open/close all itinerary days.
document.querySelectorAll('.tr-toggle-days').forEach((btn) => {
    const days = () => [...document.querySelectorAll('#itinerary details')];
    const sync = () => {
        const allOpen = days().every((d) => d.open);
        btn.textContent = allOpen ? btn.dataset.close : btn.dataset.open;
        btn.setAttribute('aria-expanded', String(allOpen));
    };
    btn.addEventListener('click', () => {
        const open = !days().every((d) => d.open);
        days().forEach((d) => (d.open = open));
        sync();
    });
    days().forEach((d) => d.addEventListener('toggle', sync));
    sync();
});

// Package page: highlight the section being read in the sticky navigation.
const navLinks = [...document.querySelectorAll('.tr-nav a[href^="#"]')];
if (navLinks.length && 'IntersectionObserver' in window) {
    const byId = new Map(navLinks.map((a) => [a.getAttribute('href').slice(1), a]));
    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((e) => {
                if (!e.isIntersecting) return;
                navLinks.forEach((a) => a.removeAttribute('aria-current'));
                const a = byId.get(e.target.id);
                if (a) {
                    a.setAttribute('aria-current', 'true');
                    // keep the active tab in view on narrow screens (horizontal only, never moves the page)
                    const ul = a.closest('ul');
                    if (ul) ul.scrollTo({ left: Math.max(0, a.parentElement.offsetLeft - 16), behavior: 'smooth' });
                }
            });
        },
        { rootMargin: '-35% 0px -60% 0px' },
    );
    byId.forEach((_, id) => {
        const el = document.getElementById(id);
        if (el) io.observe(el);
    });
}

// Package page (phones): booking bar appears once the price card is off screen,
// and hides again near the inquiry form so it never covers it.
const bar = document.querySelector('.tr-bar');
const card = document.getElementById('price-card');
const inquiry = document.getElementById('inquiry');
if (bar && card && 'IntersectionObserver' in window) {
    bar.hidden = false;
    document.body.classList.add('has-bar');
    const link = bar.querySelector('a');
    let cardVisible = true;
    let formVisible = false;
    const update = () => {
        const show = !cardVisible && !formVisible;
        bar.classList.toggle('on', show);
        bar.setAttribute('aria-hidden', String(!show));
        if (link) link.tabIndex = show ? 0 : -1;
    };
    new IntersectionObserver(([e]) => {
        cardVisible = e.isIntersecting || e.boundingClientRect.top > 0;
        update();
    }).observe(card);
    if (inquiry) {
        new IntersectionObserver(([e]) => {
            formVisible = e.isIntersecting;
            update();
        }).observe(inquiry);
    }
}
