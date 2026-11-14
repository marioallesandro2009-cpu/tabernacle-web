<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Modal from '../../../Components/ui/Modal.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Empty from '../../../Components/ui/Empty.vue';
import { encryptionOptions } from '../../../lib/outreach';

const props = defineProps({ accounts: Array, presets: Object });

const open = ref(false);
const editing = ref(null);
const testing = ref(null);
const blank = () => ({
    name: '', email: '', smtp_host: '', smtp_port: 465, smtp_encryption: 'ssl', imap_host: '', imap_port: 993, imap_encryption: 'ssl',
    username: '', password: '', daily_limit: 40, is_active: true, warmup_enabled: true, warmup_start: 10, warmup_step: 5,
});
const form = useForm(blank());

const isEdit = computed(() => editing.value !== null);

function add() {
    editing.value = null;
    form.defaults(blank()).reset();
    form.clearErrors();
    open.value = true;
}
function edit(a) {
    editing.value = a.id;
    form.defaults({ ...blank(), ...a, password: '' }).reset();
    form.clearErrors();
    open.value = true;
}
function preset(key) {
    Object.assign(form, props.presets[key]);
    if (form.email && !form.username) form.username = form.email;
}
function syncUsername() {
    // most mailboxes log in with the full address
    if (!isEdit.value && (!form.username || form.username === lastEmail)) form.username = form.email;
    lastEmail = form.email;
}
let lastEmail = '';

function save() {
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (isEdit.value) form.put(`/admin/outreach/accounts/${editing.value}`, opts);
    else form.post('/admin/outreach/accounts', opts);
}
function test(a) {
    testing.value = a.id;
    router.post(`/admin/outreach/accounts/${a.id}/test`, {}, { preserveScroll: true, onFinish: () => (testing.value = null) });
}
function remove(a) {
    if (confirm(`Hapus akun ${a.email}? Kampanye yang memakainya tidak bisa lagi mengirim dari akun ini.`)) router.delete(`/admin/outreach/accounts/${a.id}`, { preserveScroll: true });
}
const tone = (ok) => (ok === null || ok === undefined ? 'slate' : ok ? 'green' : 'red');
// the warm-up plan for the next weeks, e.g. 10 → 15 → 20 … up to the maximum
const plan = computed(() => {
    const start = Number(form.warmup_start) || 1, step = Number(form.warmup_step) || 0, max = Number(form.daily_limit) || 1;
    const weeks = [];
    for (let w = 1, n = Math.min(start, max); w <= 8; w++, n = Math.min(max, n + step)) { weeks.push(n); if (n >= max || !step) break; }
    return weeks;
});
const word = (ok) => (ok === null || ok === undefined ? 'Belum dites' : ok ? 'Terhubung' : 'Gagal');
</script>

<template>
    <Head title="Akun pengirim" />
    <PageHeader title="Akun pengirim" subtitle="Kotak email di domain perusahaan yang dipakai untuk mengirim kampanye dan membaca balasan. Password disimpan terenkripsi.">
        <button class="o-btn o-btn-primary" @click="add"><Icon name="plus" class="h-4 w-4" /> Tambah akun</button>
    </PageHeader>

    <div class="mb-5 flex gap-3 rounded-2xl bg-sky-50 p-4 text-sm leading-relaxed text-sky-900 ring-1 ring-sky-100">
        <Icon name="help" class="mt-0.5 h-4 w-4 shrink-0" />
        <p>
            <b>Warm-up otomatis.</b> Akun baru mulai dari sedikit email per hari dan batasnya naik sendiri setiap minggu sampai batas maksimal, supaya email tidak dianggap spam. Minggu pertama dihitung sejak email pertama terkirim.
            Gunakan alamat khusus outreach, bukan <i>contact@</i> yang dipakai untuk notifikasi leads.
        </p>
    </div>

    <div v-if="accounts.length" class="grid gap-4 lg:grid-cols-2">
        <div v-for="a in accounts" :key="a.id" class="o-card flex flex-col p-5" :class="{ 'opacity-70': !a.is_active }">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-base font-semibold text-brand">{{ a.name.slice(0, 1).toUpperCase() }}</div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="truncate text-base font-semibold text-slate-900">{{ a.name }}</h3>
                        <Chip v-if="!a.is_active" tone="slate">Nonaktif</Chip>
                    </div>
                    <p class="truncate text-sm text-slate-500">{{ a.email }}</p>
                </div>
                <div class="text-right">
                    <div class="text-xl font-semibold tabular-nums text-slate-900">{{ a.limit_today }}<span v-if="a.warming_up" class="text-sm font-normal text-slate-400"> / {{ a.daily_limit }}</span></div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-400">hari ini</div>
                </div>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="mb-1 flex items-center justify-between"><span class="text-xs font-semibold text-slate-600">SMTP · kirim</span><Chip :tone="tone(a.smtp_ok)">{{ word(a.smtp_ok) }}</Chip></div>
                    <div class="truncate text-xs text-slate-500">{{ a.smtp_host }}:{{ a.smtp_port }}</div>
                    <div v-if="a.smtp_error" class="mt-1 text-xs text-rose-600">{{ a.smtp_error }}</div>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <div class="mb-1 flex items-center justify-between"><span class="text-xs font-semibold text-slate-600">IMAP · baca balasan</span><Chip :tone="tone(a.imap_ok)">{{ word(a.imap_ok) }}</Chip></div>
                    <div class="truncate text-xs text-slate-500">{{ a.imap_host || 'belum diisi' }}:{{ a.imap_port }}</div>
                    <div v-if="a.imap_error" class="mt-1 text-xs text-rose-600">{{ a.imap_error }}</div>
                </div>
            </div>
            <p v-if="a.warming_up" class="mt-3 flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-100">
                <Icon name="clock" class="h-3.5 w-3.5 shrink-0" />
                <span v-if="a.warmup_started">Warm-up minggu ke-{{ a.warmup_week }}: boleh kirim {{ a.limit_today }} email hari ini, naik {{ a.warmup_step }} per minggu sampai {{ a.daily_limit }}.</span>
                <span v-else>Warm-up dimulai saat email pertama terkirim: {{ a.limit_today }} email per hari, naik {{ a.warmup_step }} per minggu sampai {{ a.daily_limit }}.</span>
            </p>
            <p v-if="a.smtp_tested_at || a.imap_polled_at" class="mt-2 text-xs text-slate-400"><span v-if="a.smtp_tested_at">Dites {{ a.smtp_tested_at }}</span><span v-if="a.smtp_tested_at && a.imap_polled_at"> · </span><span v-if="a.imap_polled_at">Balasan terakhir dicek {{ a.imap_polled_at }}</span></p>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                <button class="o-btn o-btn-sm" :disabled="testing === a.id" @click="test(a)">
                    <svg v-if="testing === a.id" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                    <Icon v-else name="check" class="h-3.5 w-3.5" />
                    {{ testing === a.id ? 'Menguji…' : 'Tes koneksi' }}
                </button>
                <button class="o-btn o-btn-sm" @click="edit(a)"><Icon name="edit" class="h-3.5 w-3.5" /> Ubah</button>
                <button class="o-btn o-btn-sm o-btn-ghost o-btn-danger ml-auto" @click="remove(a)"><Icon name="trash" class="h-3.5 w-3.5" /> Hapus</button>
            </div>
        </div>
    </div>

    <div v-else class="o-card">
        <Empty icon="mail" title="Belum ada akun pengirim" text="Tambahkan kotak email domain Anda (misalnya dari Hostinger). Setelah itu klik Tes koneksi untuk memastikan login SMTP dan IMAP berhasil.">
            <button class="o-btn o-btn-primary" @click="add"><Icon name="plus" class="h-4 w-4" /> Tambah akun pertama</button>
        </Empty>
    </div>

    <Modal :open="open" :title="isEdit ? 'Ubah akun pengirim' : 'Tambah akun pengirim'" subtitle="Isi alamat dan password. Server bisa diisi otomatis lewat tombol penyedia." size="lg" @close="open = false">
        <form id="account-form" class="space-y-5" @submit.prevent="save">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Isi server otomatis</span>
                <button v-for="(p, key) in presets" :key="key" type="button" class="o-btn o-btn-sm" @click="preset(key)">{{ p.label }}</button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="o-label" for="a-name">Nama pengirim</label><input id="a-name" v-model="form.name" class="o-input" placeholder="Tim Tabernacle Ways"><p v-if="form.errors.name" class="o-err">{{ form.errors.name }}</p></div>
                <div><label class="o-label" for="a-email">Alamat email</label><input id="a-email" v-model="form.email" type="email" class="o-input" placeholder="outreach@namadomain.com" @input="syncUsername"><p v-if="form.errors.email" class="o-err">{{ form.errors.email }}</p></div>
                <div><label class="o-label" for="a-user">Username login</label><input id="a-user" v-model="form.username" class="o-input" autocomplete="off"><p v-if="form.errors.username" class="o-err">{{ form.errors.username }}</p></div>
                <div>
                    <label class="o-label" for="a-pass">Password email</label>
                    <input id="a-pass" v-model="form.password" type="password" class="o-input" autocomplete="new-password" :placeholder="isEdit ? 'Kosongkan jika tidak diubah' : ''">
                    <p v-if="form.errors.password" class="o-err">{{ form.errors.password }}</p>
                </div>
            </div>

            <div class="rounded-2xl bg-slate-50 p-4">
                <div class="mb-3 text-sm font-semibold text-slate-700">Kirim email (SMTP)</div>
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-3"><label class="o-label" for="a-sh">Server</label><input id="a-sh" v-model="form.smtp_host" class="o-input" placeholder="smtp.hostinger.com"><p v-if="form.errors.smtp_host" class="o-err">{{ form.errors.smtp_host }}</p></div>
                    <div><label class="o-label" for="a-sp">Port</label><input id="a-sp" v-model="form.smtp_port" type="number" class="o-input"><p v-if="form.errors.smtp_port" class="o-err">{{ form.errors.smtp_port }}</p></div>
                    <div class="sm:col-span-2"><label class="o-label" for="a-se">Enkripsi</label><select id="a-se" v-model="form.smtp_encryption" class="o-input"><option v-for="o in encryptionOptions" :key="o.value" :value="o.value">{{ o.label }}</option></select></div>
                </div>
            </div>

            <div class="rounded-2xl bg-slate-50 p-4">
                <div class="mb-3 text-sm font-semibold text-slate-700">Baca balasan (IMAP)</div>
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-3"><label class="o-label" for="a-ih">Server</label><input id="a-ih" v-model="form.imap_host" class="o-input" placeholder="imap.hostinger.com"><p v-if="form.errors.imap_host" class="o-err">{{ form.errors.imap_host }}</p></div>
                    <div><label class="o-label" for="a-ip">Port</label><input id="a-ip" v-model="form.imap_port" type="number" class="o-input"></div>
                    <div class="sm:col-span-2"><label class="o-label" for="a-ie">Enkripsi</label><select id="a-ie" v-model="form.imap_encryption" class="o-input"><option v-for="o in encryptionOptions" :key="o.value" :value="o.value">{{ o.label }}</option></select></div>
                </div>
            </div>

            <div class="rounded-2xl bg-slate-50 p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-slate-700">Batas kirim &amp; warm-up</span>
                    <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="form.warmup_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand"> Warm-up otomatis</label>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="o-label" for="a-lim">{{ form.warmup_enabled ? 'Batas maksimal per hari' : 'Batas kirim per hari' }}</label>
                        <input id="a-lim" v-model="form.daily_limit" type="number" min="1" max="500" class="o-input">
                        <p v-if="form.errors.daily_limit" class="o-err">{{ form.errors.daily_limit }}</p>
                    </div>
                    <template v-if="form.warmup_enabled">
                        <div>
                            <label class="o-label" for="a-ws">Mulai dari (minggu 1)</label>
                            <input id="a-ws" v-model="form.warmup_start" type="number" min="1" max="500" class="o-input">
                            <p v-if="form.errors.warmup_start" class="o-err">{{ form.errors.warmup_start }}</p>
                        </div>
                        <div>
                            <label class="o-label" for="a-wst">Naik per minggu</label>
                            <input id="a-wst" v-model="form.warmup_step" type="number" min="0" max="100" class="o-input">
                            <p v-if="form.errors.warmup_step" class="o-err">{{ form.errors.warmup_step }}</p>
                        </div>
                    </template>
                </div>
                <p v-if="form.warmup_enabled" class="mt-3 text-xs text-slate-500">
                    Rencana: <span v-for="(n, i) in plan" :key="i"><span v-if="i"> → </span><b class="text-slate-700">{{ n }}</b></span> email per hari (minggu 1, 2, 3, …).
                </p>
                <p v-else class="mt-3 text-xs text-slate-500">Tanpa warm-up, akun langsung memakai batas ini. Cocok untuk akun lama yang sudah lama dipakai mengirim email.</p>
            </div>

            <label class="flex items-center gap-2.5 text-sm text-slate-700"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand"> Akun aktif (boleh dipakai kampanye)</label>
        </form>
        <template #footer>
            <button type="button" class="o-btn" @click="open = false">Batal</button>
            <button type="submit" form="account-form" class="o-btn o-btn-primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan akun' }}</button>
        </template>
    </Modal>
</template>
