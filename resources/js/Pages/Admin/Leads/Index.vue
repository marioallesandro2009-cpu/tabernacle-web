<script setup>
import { au } from '../../../lib/admin';
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({ leads: Object, query: Object, statuses: Array, counts: Object, sources: Array, tours: Array, staff: Array, canExport: Boolean });

const f = reactive({ q: '', status: '', type: '', source: '', tour_id: '', assigned_to: '', from: '', to: '', ...props.query });
const selected = ref([]);
const bulkStatus = ref('');

const params = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v != null));
const apply = () => router.get(au('/admin/leads'), params(), { preserveState: true, replace: true });
const reset = () => { Object.keys(f).forEach((k) => (f[k] = '')); apply(); };
const exportUrl = (fmt) => au(`/admin/leads/export/${fmt}?`) + new URLSearchParams(params()).toString();

const allOnPage = computed(() => props.leads.data.length > 0 && props.leads.data.every((l) => selected.value.includes(l.id)));
const toggleAll = () => (selected.value = allOnPage.value ? [] : props.leads.data.map((l) => l.id));
const bulk = (action) => {
    if (!selected.value.length) return;
    if (action === 'delete' && !confirm(`Hapus ${selected.value.length} lead? Tidak bisa dibatalkan.`)) return;
    router.post(au('/admin/leads/bulk'), { ids: selected.value, action, status: bulkStatus.value }, { preserveScroll: true, onSuccess: () => (selected.value = []) });
};
const label = (s) => props.statuses.find((x) => x.value === s)?.label || s;
const tone = { baru: 'bg-blue-100 text-blue-800', dihubungi: 'bg-sky-100 text-sky-800', negosiasi: 'bg-amber-100 text-amber-800', dp: 'bg-violet-100 text-violet-800', lunas: 'bg-emerald-100 text-emerald-800', batal: 'bg-stone-200 text-stone-600' };
</script>

<template>
    <Head title="Leads" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Leads</h1>
        <div v-if="canExport" class="flex gap-1">
            <a :href="exportUrl('xlsx')" class="btn btn-sm"><Icon name="download" class="h-4 w-4" /> Excel</a>
            <a :href="exportUrl('csv')" class="btn btn-sm"><Icon name="download" class="h-4 w-4" /> CSV</a>
            <a :href="exportUrl('pdf')" class="btn btn-sm"><Icon name="download" class="h-4 w-4" /> PDF</a>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <button class="btn btn-sm" :class="!f.status ? 'btn-primary' : ''" @click="f.status = ''; apply()">Semua</button>
        <button v-for="s in statuses" :key="s.value" class="btn btn-sm" :class="f.status === s.value ? 'btn-primary' : ''" @click="f.status = s.value; apply()">{{ s.label }} <span class="ml-1 opacity-70">{{ counts[s.value] || 0 }}</span></button>
    </div>

    <form class="card mb-4 grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="apply">
        <input v-model="f.q" class="input lg:col-span-2" placeholder="Cari nama, telepon, email, paket, pesan…" aria-label="Cari">
        <select v-model="f.type" class="input" aria-label="Jenis"><option value="">Semua jenis</option><option value="form">Dari form</option><option value="wa_click">Klik WhatsApp</option></select>
        <select v-model="f.source" class="input" aria-label="Sumber"><option value="">Semua sumber</option><option v-for="s in sources" :key="s" :value="s">{{ s }}</option></select>
        <select v-model="f.tour_id" class="input" aria-label="Paket"><option value="">Semua paket</option><option v-for="t in tours" :key="t.id" :value="t.id">{{ t.name }}</option></select>
        <select v-model="f.assigned_to" class="input" aria-label="Ditangani"><option value="">Semua staf</option><option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option></select>
        <input v-model="f.from" type="date" class="input" aria-label="Dari tanggal">
        <input v-model="f.to" type="date" class="input" aria-label="Sampai tanggal">
        <div class="flex gap-2 sm:col-span-2 lg:col-span-4"><button class="btn btn-primary">Terapkan filter</button><button type="button" class="btn" @click="reset">Reset</button></div>
    </form>

    <div v-if="selected.length" class="mb-3 flex flex-wrap items-center gap-2 rounded-lg bg-brand/5 p-2 text-sm">
        <span class="font-medium">{{ selected.length }} dipilih</span>
        <select v-model="bulkStatus" class="input w-auto" aria-label="Ubah status"><option value="">Ubah status ke…</option><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select>
        <button class="btn btn-sm" :disabled="!bulkStatus" @click="bulk('status')">Terapkan</button>
        <button class="btn btn-sm text-red-600" @click="bulk('delete')">Hapus</button>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[720px]">
            <thead class="border-b border-stone-200 bg-stone-50">
                <tr><th class="th w-8"><input type="checkbox" :checked="allOnPage" aria-label="Pilih semua" @change="toggleAll"></th><th class="th">Tanggal</th><th class="th">Kontak</th><th class="th">Paket</th><th class="th">Sumber</th><th class="th">Status</th><th class="th">Ditangani</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                <tr v-for="l in leads.data" :key="l.id" class="hover:bg-stone-50">
                    <td class="td"><input v-model="selected" type="checkbox" :value="l.id" :aria-label="'Pilih ' + l.name"></td>
                    <td class="td whitespace-nowrap text-xs text-stone-500">{{ l.created_at }}</td>
                    <td class="td">
                        <Link :href="au(`/admin/leads/${l.id}`)" class="font-medium text-brand hover:underline">{{ l.name || '(klik WhatsApp)' }}</Link>
                        <div class="text-xs text-stone-500">{{ l.phone }}<span v-if="l.email"> · {{ l.email }}</span></div>
                    </td>
                    <td class="td"><span class="line-clamp-2">{{ l.tour || '–' }}</span><span v-if="l.pax" class="text-xs text-stone-500">{{ l.pax }} orang</span></td>
                    <td class="td"><span class="badge">{{ l.type === 'wa_click' ? 'WA' : l.source || 'direct' }}</span></td>
                    <td class="td"><span class="badge" :class="tone[l.status]">{{ label(l.status) }}</span></td>
                    <td class="td text-sm">{{ l.assignee || '–' }}</td>
                </tr>
                <tr v-if="!leads.data.length"><td colspan="7" class="td py-10 text-center text-stone-500">Belum ada lead yang cocok.</td></tr>
            </tbody>
        </table>
        <Pagination :links="leads.links" />
    </div>
</template>
