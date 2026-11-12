<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({ cfg: { type: Object, required: true } });
const L = props.cfg.labels || {};

const form = reactive({
    name: '', phone: '', email: '', tour_id: props.cfg.tourId || '', pax: '', plan_date: '', message: '', website: '',
});
const errors = ref({});
const state = ref('idle'); // idle | sending | ok | bad
const note = ref('');

function phoneOk(v) {
    let d = String(v || '').replace(/\D/g, '');
    if (d.startsWith('0')) d = '62' + d.slice(1);
    else if (d.startsWith('8')) d = '62' + d;
    return /^628[1-9][0-9]{7,11}$/.test(d);
}

function validate() {
    const e = {};
    if (form.name.trim().length < 2) e.name = L.error;
    if (!phoneOk(form.phone)) e.phone = L.phoneInvalid;
    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = L.error;
    errors.value = e;
    return Object.keys(e).length === 0;
}

async function submit() {
    note.value = '';
    if (!validate()) {
        state.value = 'bad';
        note.value = L.error;
        return;
    }
    state.value = 'sending';
    try {
        const res = await fetch(props.cfg.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': props.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                ...form,
                tour_id: form.tour_id || null,
                pax: form.pax || null,
                plan_date: form.plan_date || null,
                page_url: location.href,
                referrer: document.referrer || '',
                ...(window.__twUtm || {}),
            }),
        });
        const data = await res.json().catch(() => ({}));
        if (res.status === 429) {
            state.value = 'bad';
            note.value = L.throttle;
        } else if (res.status === 422) {
            state.value = 'bad';
            const e = {};
            Object.entries(data.errors || {}).forEach(([k, v]) => (e[k] = v[0]));
            errors.value = e;
            note.value = data.message || L.error;
        } else if (!res.ok) {
            throw new Error('http');
        } else {
            state.value = 'ok';
            note.value = data.message || L.success;
            Object.assign(form, { name: '', phone: '', email: '', pax: '', plan_date: '', message: '' });
            errors.value = {};
        }
    } catch (err) {
        state.value = 'bad';
        note.value = L.error;
    }
}
</script>

<template>
    <form class="lead-form" novalidate @submit.prevent="submit">
        <p v-if="note" class="lead-note" :class="state === 'ok' ? 'ok' : 'bad'" role="status">{{ note }}</p>

        <label class="fld" :class="{ invalid: errors.name }">
            <span class="mono">{{ L.name }} *</span>
            <input v-model="form.name" type="text" autocomplete="name" maxlength="120" required>
            <small v-if="errors.name" class="err">{{ errors.name }}</small>
        </label>
        <label class="fld" :class="{ invalid: errors.phone }">
            <span class="mono">{{ L.phone }} *</span>
            <input v-model="form.phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="08xx xxxx xxxx" maxlength="30" required>
            <small v-if="errors.phone" class="err">{{ errors.phone }}</small>
        </label>
        <label class="fld" :class="{ invalid: errors.email }">
            <span class="mono">{{ L.email }}</span>
            <input v-model="form.email" type="email" autocomplete="email" maxlength="190">
            <small v-if="errors.email" class="err">{{ errors.email }}</small>
        </label>
        <label class="fld">
            <span class="mono">{{ L.tour }}</span>
            <select v-model="form.tour_id">
                <option value="">{{ L.tourAny }}</option>
                <option v-for="t in cfg.tours" :key="t.id" :value="t.id">{{ t.label }}</option>
            </select>
        </label>
        <label class="fld third">
            <span class="mono">{{ L.pax }}</span>
            <input v-model="form.pax" type="number" min="1" max="500" inputmode="numeric">
        </label>
        <label class="fld third">
            <span class="mono">{{ L.date }}</span>
            <input v-model="form.plan_date" type="date" :min="cfg.today">
        </label>
        <label class="fld full">
            <span class="mono">{{ L.message }}</span>
            <textarea v-model="form.message" maxlength="2000" rows="4"></textarea>
        </label>
        <!-- honeypot: real visitors never see or fill this -->
        <div class="hp" aria-hidden="true"><label>Website <input v-model="form.website" type="text" tabindex="-1" autocomplete="off"></label></div>

        <div class="actions">
            <button class="btn btn-navy" type="submit" :disabled="state === 'sending'">
                {{ state === 'sending' ? L.sending : L.send }}
                <svg aria-hidden="true" width="20" height="10" viewBox="0 0 22 10"><path d="M0 5h20M16 1l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.2" /></svg>
            </button>
        </div>
    </form>
</template>
