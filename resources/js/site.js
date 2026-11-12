// Public website: small Vue "islands" mounted into server-rendered pages.
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
