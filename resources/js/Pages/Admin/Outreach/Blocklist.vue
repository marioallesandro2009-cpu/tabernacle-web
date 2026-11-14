<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Modal from '../../../Components/ui/Modal.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Empty from '../../../Components/ui/Empty.vue';

const props = defineProps({ domains: Array, emails: Object, filters: Object, reasons: Object, counts: Object });

const domainForm = useForm({ domain: '', note: '' });
const emailForm = useForm({ emails: '', note: '' });
const emailOpen = ref(false);
const q = ref(props.filters.q || '');
const reasonTone = { unsubscribed: 'violet', bounced: 'red', manual: 'slate' };

function addDomain() {
    domainForm.post('/admin/outreach/blocklist/domains', { preserveScroll: true, onSuccess: () => domainForm.reset() });
}
function removeDomain(d) {
    if (confirm(`Buka blokir ${d.domain}? Kontak dari domain ini bisa dimasukkan ke kampanye baru lagi.`)) router.delete(`/admin/outreach/blocklist/domains/${d.id}`, { preserveScroll: true });
}
function addEmails() {
    emailForm.post('/admin/outreach/blocklist/emails', { preserveScroll: true, onSuccess: () => { emailForm.reset(); emailOpen.value = false; } });
}
function removeEmail(e) {
    if (confirm(`Keluarkan ${e.email} dari daftar blokir?`)) router.delete(`/admin/outreach/blocklist/emails/${e.id}`, { preserveScroll: true });
}
function filter(extra = {}) {
    router.get('/admin/outreach/blocklist', { ...props.filters, q: q.value || undefined, ...extra }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Daftar blokir" />
    <PageHeader title="Daftar blokir" subtitle="Domain dan alamat email yang tidak akan pernah dikirimi kampanye apa pun. Dicek saat impor, saat penjadwalan, dan sekali lagi tepat sebelum email terkirim." />

    <div class="grid gap-6 xl:grid-cols-5">
        <!-- domains -->
        <section class="o-card p-5 xl:col-span-2">
            <h2 class="text-base font-semibold text-slate-900">Domain diblokir</h2>
            <p class="mt-1 text-sm text-slate-500">Misalnya kompetitor atau klien yang sudah ada. Memblokir <b>kompetitor.com</b> juga mencakup <b>mail.kompetitor.com</b>.</p>

            <form class="mt-4 space-y-3" @submit.prevent="addDomain">
                <div>
                    <label class="o-label" for="b-domain">Domain</label>
                    <input id="b-domain" v-model="domainForm.domain" class="o-input" placeholder="kompetitor.com atau nama@kompetitor.com">
                    <p v-if="domainForm.errors.domain" class="o-err">{{ domainForm.errors.domain }}</p>
                </div>
                <div>
                    <label class="o-label" for="b-note">Catatan (opsional)</label>
                    <input id="b-note" v-model="domainForm.note" class="o-input" placeholder="Kompetitor / sudah jadi klien">
                </div>
                <button class="o-btn o-btn-primary" :disabled="domainForm.processing"><Icon name="plus" class="h-4 w-4" /> Blokir domain</button>
            </form>

            <ul v-if="domains.length" class="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                <li v-for="d in domains" :key="d.id" class="flex items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-slate-900">{{ d.domain }}</div>
                        <div class="truncate text-xs text-slate-500">{{ d.contacts }} kontak<span v-if="d.note"> · {{ d.note }}</span> · sejak {{ d.at }}</div>
                    </div>
                    <button class="o-btn o-btn-sm o-btn-ghost o-btn-danger" aria-label="Buka blokir" @click="removeDomain(d)"><Icon name="x" class="h-4 w-4" /></button>
                </li>
            </ul>
            <p v-else class="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-400">Belum ada domain yang diblokir.</p>
        </section>

        <!-- single addresses -->
        <section class="o-card p-5 xl:col-span-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Email jangan dikirimi</h2>
                    <p class="mt-1 text-sm text-slate-500">Terisi otomatis dari tautan berhenti berlangganan dan email bounce, atau Anda tambahkan sendiri.</p>
                </div>
                <button class="o-btn o-btn-sm" @click="emailOpen = true"><Icon name="plus" class="h-3.5 w-3.5" /> Tambah email</button>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button class="o-btn o-btn-sm" :class="{ 'o-btn-primary': !filters.reason }" @click="filter({ reason: undefined, page: undefined })">Semua</button>
                <button v-for="(label, key) in reasons" :key="key" class="o-btn o-btn-sm" :class="{ 'o-btn-primary': filters.reason === key }" @click="filter({ reason: key, page: undefined })">{{ label }} <span class="opacity-70">{{ counts[key] }}</span></button>
            </div>
            <form class="mt-3" @submit.prevent="filter({ page: undefined })">
                <input v-model="q" class="o-input" placeholder="Cari email…" @keyup.enter="filter({ page: undefined })">
            </form>

            <div v-if="emails.data.length" class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr><th class="o-th">Email</th><th class="o-th">Alasan</th><th class="o-th">Sejak</th><th class="o-th"></th></tr></thead>
                    <tbody>
                        <tr v-for="e in emails.data" :key="e.id" class="border-t border-slate-100">
                            <td class="o-td"><div class="font-medium text-slate-900">{{ e.email }}</div><div v-if="e.note" class="max-w-xs truncate text-xs text-slate-500">{{ e.note }}</div></td>
                            <td class="o-td"><Chip :tone="reasonTone[e.reason] || 'slate'">{{ e.reason_label }}</Chip></td>
                            <td class="o-td whitespace-nowrap text-slate-500">{{ e.at }}</td>
                            <td class="o-td text-right"><button v-if="e.reason === 'manual'" class="o-btn o-btn-sm o-btn-ghost" @click="removeEmail(e)">Buka</button></td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-4"><Pagination :links="emails.links" /></div>
            </div>
            <Empty v-else icon="mail" title="Daftar masih kosong" text="Orang yang berhenti berlangganan dan email yang bounce akan muncul di sini secara otomatis." />
        </section>
    </div>

    <Modal :open="emailOpen" title="Tambah email ke daftar blokir" subtitle="Satu email per baris, atau dipisah koma." size="md" @close="emailOpen = false">
        <form id="block-emails" class="space-y-4" @submit.prevent="addEmails">
            <div>
                <label class="o-label" for="b-emails">Alamat email</label>
                <textarea id="b-emails" v-model="emailForm.emails" rows="6" class="o-input font-mono text-xs" placeholder="nama@contoh.com&#10;orang@lain.co.id" />
                <p v-if="emailForm.errors.emails" class="o-err">{{ emailForm.errors.emails }}</p>
            </div>
            <div><label class="o-label" for="b-enote">Catatan (opsional)</label><input id="b-enote" v-model="emailForm.note" class="o-input" placeholder="Minta tidak dihubungi lewat telepon"></div>
        </form>
        <template #footer>
            <button type="button" class="o-btn" @click="emailOpen = false">Batal</button>
            <button type="submit" form="block-emails" class="o-btn o-btn-primary" :disabled="emailForm.processing">Simpan</button>
        </template>
    </Modal>
</template>
