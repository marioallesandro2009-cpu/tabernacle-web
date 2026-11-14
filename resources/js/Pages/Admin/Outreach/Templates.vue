<script setup>
import { au } from '../../../lib/admin';
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Modal from '../../../Components/ui/Modal.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Empty from '../../../Components/ui/Empty.vue';
import { xsrf } from '../../../lib/outreach';

const props = defineProps({ templates: Array, segments: Array, variables: Array, sampleContacts: Array, senders: Array });

const open = ref(false);
const editing = ref(null);
const filter = ref('');
const body = ref(null);
const blank = () => ({ name: '', segment_id: '', subject: '', body: '', is_active: true });
const form = useForm(blank());

const shown = computed(() => props.templates.filter((t) => filter.value === '' || (filter.value === 'none' ? !t.segment_id : String(t.segment_id) === String(filter.value))));

function add() { editing.value = null; form.defaults({ ...blank(), body: 'Selamat pagi {nama},\n\n\n\nSalam hormat,\n{nama_pengirim}' }).reset(); form.clearErrors(); open.value = true; refresh(); }
function edit(t) { editing.value = t.id; form.defaults({ name: t.name, segment_id: t.segment_id || '', subject: t.subject, body: t.body, is_active: t.is_active }).reset(); form.clearErrors(); open.value = true; refresh(); }
function copy(t) { editing.value = null; form.defaults({ name: `${t.name} (salinan)`, segment_id: t.segment_id || '', subject: t.subject, body: t.body, is_active: true }).reset(); form.clearErrors(); open.value = true; refresh(); }
function save() {
    form.transform((d) => ({ ...d, segment_id: d.segment_id || null }));
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) form.put(au(`/admin/outreach/templates/${editing.value}`), opts);
    else form.post(au('/admin/outreach/templates'), opts);
}
function remove(t) {
    if (confirm(`Hapus template "${t.name}"?${t.used_in ? ` Template ini dipakai di ${t.used_in} langkah kampanye, langkah itu akan kosong.` : ''}`)) router.delete(au(`/admin/outreach/templates/${t.id}`), { preserveScroll: true });
}

function insert(key) {
    const el = body.value;
    const token = `{${key}}`;
    if (!el) { form.body += token; return; }
    const s = el.selectionStart, e = el.selectionEnd;
    form.body = form.body.slice(0, s) + token + form.body.slice(e);
    requestAnimationFrame(() => { el.focus(); el.selectionStart = el.selectionEnd = s + token.length; });
}

// preview of the email as a real contact would receive it
const sample = ref('');
const sender = ref('');
const preview = ref({ subject: '', body: '', to: '' });
let timer;
async function refresh() {
    try {
        const r = await fetch(au('/admin/outreach/templates/preview'), {
            method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            body: JSON.stringify({ subject: form.subject, body: form.body, contact_id: sample.value || null, account_id: sender.value || null }),
        });
        if (r.ok) preview.value = await r.json();
    } catch { /* the preview is a convenience, so a failed request is ignored */ }
}
watch([() => form.subject, () => form.body, sample, sender], () => { clearTimeout(timer); timer = setTimeout(refresh, 350); });
</script>

<template>
    <Head title="Template email" />
    <PageHeader title="Template email" subtitle="Isi email teks sederhana. Satu segmen bisa punya beberapa template, misalnya template gereja yang memuat ayat. Tautan berhenti berlangganan dan data perusahaan ditambahkan otomatis di akhir.">
        <button class="o-btn o-btn-primary" @click="add"><Icon name="plus" class="h-4 w-4" /> Template baru</button>
    </PageHeader>

    <div v-if="templates.length" class="mb-4 flex flex-wrap gap-2">
        <button class="o-btn o-btn-sm" :class="filter === '' ? 'o-btn-primary' : ''" @click="filter = ''">Semua</button>
        <button v-for="s in segments" :key="s.id" class="o-btn o-btn-sm" :class="String(filter) === String(s.id) ? 'o-btn-primary' : ''" @click="filter = s.id">{{ s.name }}</button>
        <button class="o-btn o-btn-sm" :class="filter === 'none' ? 'o-btn-primary' : ''" @click="filter = 'none'">Umum</button>
    </div>

    <div v-if="shown.length" class="grid gap-4 lg:grid-cols-2">
        <div v-for="t in shown" :key="t.id" class="o-card flex flex-col p-5" :class="{ 'opacity-60': !t.is_active }">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2"><h3 class="truncate text-base font-semibold text-slate-900">{{ t.name }}</h3><Chip v-if="!t.is_active" tone="slate" :dot="false">Nonaktif</Chip></div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <Chip tone="blue" :dot="false">{{ t.segment || 'Umum (semua segmen)' }}</Chip>
                        <span v-if="t.used_in">Dipakai di {{ t.used_in }} langkah</span>
                    </div>
                </div>
                <div class="flex shrink-0 gap-1">
                    <button class="o-btn o-btn-sm o-btn-ghost" aria-label="Salin" title="Salin" @click="copy(t)"><Icon name="doc" class="h-4 w-4" /></button>
                    <button class="o-btn o-btn-sm o-btn-ghost" aria-label="Ubah" @click="edit(t)"><Icon name="edit" class="h-4 w-4" /></button>
                    <button class="o-btn o-btn-sm o-btn-ghost o-btn-danger" aria-label="Hapus" @click="remove(t)"><Icon name="trash" class="h-4 w-4" /></button>
                </div>
            </div>
            <div class="mt-3 rounded-xl bg-slate-50 p-3">
                <div class="text-xs font-semibold text-slate-500">Subjek</div>
                <div class="truncate text-sm text-slate-800">{{ t.subject }}</div>
                <div class="mt-2 line-clamp-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ t.body }}</div>
            </div>
        </div>
    </div>
    <div v-else class="o-card">
        <Empty icon="text" title="Belum ada template" text="Buat template email pertama. Pakai variabel seperti {nama} dan {organisasi} supaya tiap email terasa personal.">
            <button class="o-btn o-btn-primary" @click="add"><Icon name="plus" class="h-4 w-4" /> Buat template</button>
        </Empty>
    </div>

    <Modal :open="open" :title="editing ? 'Ubah template' : 'Template baru'" size="xl" @close="open = false">
        <div class="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
            <form id="template-form" class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="o-label" for="t-name">Nama template</label><input id="t-name" v-model="form.name" class="o-input" placeholder="Gereja, email pertama"><p v-if="form.errors.name" class="o-err">{{ form.errors.name }}</p></div>
                    <div><label class="o-label" for="t-seg">Untuk segmen</label><select id="t-seg" v-model="form.segment_id" class="o-input"><option value="">Umum (semua segmen)</option><option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                </div>
                <div><label class="o-label" for="t-sub">Subjek</label><input id="t-sub" v-model="form.subject" class="o-input" placeholder="Tur ziarah untuk {organisasi}"><p v-if="form.errors.subject" class="o-err">{{ form.errors.subject }}</p></div>
                <div>
                    <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                        <label class="o-label !mb-0" for="t-body">Isi email</label>
                        <div class="flex flex-wrap gap-1"><button v-for="v in variables" :key="v.key" type="button" :title="v.hint" class="rounded-lg bg-brand/10 px-2 py-1 text-xs font-medium text-brand hover:bg-brand/20" @click="insert(v.key)">{{ '{' + v.key + '}' }}</button></div>
                    </div>
                    <textarea id="t-body" ref="body" v-model="form.body" rows="12" class="o-input font-[inherit] leading-relaxed" />
                    <p v-if="form.errors.body" class="o-err">{{ form.errors.body }}</p>
                    <p class="hint">Teks biasa saja, tanpa gambar atau format, supaya tidak masuk spam. Klik variabel untuk menyisipkannya.</p>
                </div>
                <label class="flex items-center gap-2.5 text-sm text-slate-700"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand"> Template aktif</label>
            </form>

            <div class="lg:sticky lg:top-0 lg:self-start">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Pratinjau</div>
                <div class="mb-3 grid gap-2 sm:grid-cols-2">
                    <select v-model="sample" class="o-input" aria-label="Contoh kontak"><option value="">Contoh kontak</option><option v-for="c in sampleContacts" :key="c.id" :value="c.id">{{ c.label }}</option></select>
                    <select v-model="sender" class="o-input" aria-label="Contoh pengirim"><option value="">Pengirim contoh</option><option v-for="s in senders" :key="s.id" :value="s.id">{{ s.name }}</option></select>
                </div>
                <div class="overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200">
                    <div class="border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500"><div>Kepada: <b class="text-slate-700">{{ preview.to || '—' }}</b></div><div class="mt-0.5">Subjek: <b class="text-slate-800">{{ preview.subject || '—' }}</b></div></div>
                    <pre class="max-h-[420px] overflow-auto whitespace-pre-wrap px-4 py-3 font-[inherit] text-[13px] leading-relaxed text-slate-700">{{ preview.body }}</pre>
                </div>
            </div>
        </div>
        <template #footer>
            <button type="button" class="o-btn" @click="open = false">Batal</button>
            <button type="submit" form="template-form" class="o-btn o-btn-primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan template' }}</button>
        </template>
    </Modal>
</template>
