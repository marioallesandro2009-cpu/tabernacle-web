<script setup>
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps({ logs: Object, query: Object, actions: Array, stats: Object, accounts: Array });
const f = reactive({ q: '', user_id: '', action: '', from: '', to: '', ...props.query });
const open = ref(null);

const params = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v != null));
const apply = () => router.get('/admin/logins', params(), { preserveState: true, replace: true });
const reset = () => { Object.keys(f).forEach((k) => (f[k] = '')); apply(); };
const showUser = (id) => { f.user_id = String(id); apply(); };
const exportUrl = () => '/admin/logins/export?' + new URLSearchParams(params()).toString();
const tone = (r) => (r.warning ? 'bg-red-100 text-red-800' : r.action === 'login' ? 'bg-emerald-100 text-emerald-800' : r.action === 'logout' ? 'bg-stone-100 text-stone-700' : 'bg-blue-100 text-blue-800');
</script>

<template>
    <Head title="Riwayat login" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Riwayat login admin</h1>
            <p class="text-sm text-stone-600">Setiap kali admin masuk, keluar, gagal masuk, atau mengubah keamanan akunnya. Hanya bisa dibaca, tidak bisa diubah atau dihapus.</p>
        </div>
        <a :href="exportUrl()" class="btn"><Icon name="download" class="h-4 w-4" /> Ekspor CSV</a>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card p-4"><div class="text-2xl font-bold">{{ stats.logins_today }}</div><div class="text-xs text-stone-500">Login hari ini</div></div>
        <div class="card p-4"><div class="text-2xl font-bold">{{ stats.logins_7d }}</div><div class="text-xs text-stone-500">Login 7 hari terakhir</div></div>
        <div class="card p-4"><div class="text-2xl font-bold">{{ stats.active_admins_7d }}</div><div class="text-xs text-stone-500">Admin aktif 7 hari terakhir</div></div>
        <button type="button" class="card p-4 text-left hover:border-red-300" :class="stats.warnings_7d ? 'border-red-200' : ''" @click="f.action = 'warnings'; apply()">
            <div class="text-2xl font-bold" :class="stats.warnings_7d ? 'text-red-600' : ''">{{ stats.warnings_7d }}</div>
            <div class="text-xs text-stone-500">Peringatan 7 hari (gagal masuk, diblokir, 2FA salah)</div>
        </button>
    </div>

    <div class="card mb-5 overflow-x-auto">
        <div class="border-b border-stone-200 px-4 py-3 font-semibold">Akun admin</div>
        <table class="w-full min-w-[640px]">
            <thead class="bg-stone-50"><tr><th class="th">Nama</th><th class="th">Peran</th><th class="th">Status</th><th class="th">2FA</th><th class="th">Terakhir masuk</th><th class="th">Login 30 hari</th><th class="th" /></tr></thead>
            <tbody class="divide-y divide-stone-100">
                <tr v-for="a in accounts" :key="a.id">
                    <td class="td"><div class="font-medium">{{ a.name }}</div><div class="text-xs text-stone-500">{{ a.email }}</div></td>
                    <td class="td"><span class="badge">{{ a.role }}</span></td>
                    <td class="td"><span class="badge" :class="a.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-600'">{{ a.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="td text-sm" :class="a.two_factor ? 'text-emerald-700' : 'text-amber-700'">{{ a.two_factor ? 'Aktif' : 'Belum' }}</td>
                    <td class="td text-sm">{{ a.last_login || 'Belum pernah' }}</td>
                    <td class="td text-sm">{{ a.logins_30d }}</td>
                    <td class="td text-right"><button class="btn btn-sm" @click="showUser(a.id)">Lihat riwayat</button></td>
                </tr>
            </tbody>
        </table>
    </div>

    <form class="card mb-4 grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-6" @submit.prevent="apply">
        <input v-model="f.q" class="input lg:col-span-2" placeholder="Cari email atau IP…" aria-label="Cari">
        <select v-model="f.user_id" class="input" aria-label="Akun"><option value="">Semua akun</option><option v-for="a in accounts" :key="a.id" :value="String(a.id)">{{ a.name }}</option></select>
        <select v-model="f.action" class="input" aria-label="Peristiwa">
            <option value="">Semua peristiwa</option>
            <option value="warnings">Hanya peringatan</option>
            <option v-for="a in actions" :key="a.value" :value="a.value">{{ a.label }}</option>
        </select>
        <input v-model="f.from" type="date" class="input" aria-label="Dari tanggal">
        <input v-model="f.to" type="date" class="input" aria-label="Sampai tanggal">
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6"><button class="btn btn-primary">Terapkan</button><button type="button" class="btn" @click="reset">Reset</button></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[760px]">
            <thead class="border-b border-stone-200 bg-stone-50"><tr><th class="th">Waktu</th><th class="th">Peristiwa</th><th class="th">Akun</th><th class="th">IP</th><th class="th">Perangkat</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
                <template v-for="r in logs.data" :key="r.id">
                    <tr class="cursor-pointer hover:bg-stone-50" @click="open = open === r.id ? null : r.id">
                        <td class="td whitespace-nowrap text-xs text-stone-500">{{ r.at }}</td>
                        <td class="td"><span class="badge" :class="tone(r)">{{ r.label }}</span><div v-if="r.by" class="mt-0.5 text-xs text-stone-500">oleh {{ r.by }}</div></td>
                        <td class="td"><div class="font-medium">{{ r.account || '(akun tidak ditemukan atau sudah dihapus)' }}</div><div class="text-xs text-stone-500">{{ r.email }}</div></td>
                        <td class="td font-mono text-xs">{{ r.ip }}</td>
                        <td class="td text-sm">{{ [r.device, r.browser].filter(Boolean).join(' · ') || '–' }}</td>
                    </tr>
                    <tr v-if="open === r.id && r.user_agent"><td colspan="5" class="break-all bg-stone-50 px-4 py-2 font-mono text-xs text-stone-600">{{ r.user_agent }}</td></tr>
                </template>
                <tr v-if="!logs.data.length"><td colspan="5" class="td py-10 text-center text-stone-500">Belum ada catatan untuk filter ini.</td></tr>
            </tbody>
        </table>
        <Pagination :links="logs.links" />
    </div>
    <p class="hint mt-3">Klik baris untuk melihat detail browser. Catatan lama (sebelum fitur ini) tidak menyimpan informasi perangkat.</p>
</template>
