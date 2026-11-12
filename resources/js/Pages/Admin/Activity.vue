<script setup>
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps({ logs: Object, query: Object, users: Array, actions: Array, types: Array });
const f = reactive({ q: '', user_id: '', action: '', subject_type: '', ...props.query });
const open = ref(null);

const apply = () => router.get('/admin/activity', Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v != null)), { preserveState: true, replace: true });
const actionLabel = { created: 'Membuat', updated: 'Mengubah', deleted: 'Menghapus', login: 'Masuk', login_failed: 'Gagal masuk', login_blocked: 'Ditolak (nonaktif)', login_throttled: 'Diblokir sementara', two_factor_failed: 'Kode 2FA salah', forced_logout: 'Dikeluarkan', logout: 'Keluar', password_reset_requested: 'Minta reset password', password_reset: 'Reset password', password_changed: 'Ganti password', two_factor_enabled: '2FA aktif', two_factor_disabled: '2FA mati', reset_link_sent: 'Kirim tautan reset', backup: 'Backup', download: 'Unduh', export: 'Ekspor', update: 'Mengubah', reset: 'Reset statistik' };
const pretty = (v) => (typeof v === 'object' ? JSON.stringify(v) : String(v));
</script>

<template>
    <Head title="Log aktivitas" />
    <h1 class="mb-1 text-2xl font-bold">Log aktivitas admin</h1>
    <p class="mb-4 text-sm text-stone-600">Siapa mengubah apa dan kapan. Catatan hanya bisa dibaca.</p>

    <form class="card mb-4 grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="apply">
        <input v-model="f.q" class="input lg:col-span-2" placeholder="Cari objek…" aria-label="Cari">
        <select v-model="f.user_id" class="input" aria-label="Pengguna"><option value="">Semua pengguna</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option></select>
        <select v-model="f.action" class="input" aria-label="Aksi"><option value="">Semua aksi</option><option v-for="a in actions" :key="a" :value="a">{{ actionLabel[a] || a }}</option></select>
        <select v-model="f.subject_type" class="input" aria-label="Jenis data"><option value="">Semua jenis</option><option v-for="t in types" :key="t" :value="t">{{ t }}</option></select>
        <div class="sm:col-span-2 lg:col-span-5"><button class="btn btn-primary">Terapkan</button></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[640px]">
            <thead class="border-b border-stone-200 bg-stone-50"><tr><th class="th">Waktu</th><th class="th">Pengguna</th><th class="th">Aksi</th><th class="th">Objek</th><th class="th">IP</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
                <template v-for="l in logs.data" :key="l.id">
                    <tr class="hover:bg-stone-50" :class="l.changes ? 'cursor-pointer' : ''" @click="l.changes && (open = open === l.id ? null : l.id)">
                        <td class="td whitespace-nowrap text-xs text-stone-500">{{ l.at }}</td>
                        <td class="td">{{ l.user }}</td>
                        <td class="td"><span class="badge">{{ actionLabel[l.action] || l.action }}</span></td>
                        <td class="td"><span class="text-xs text-stone-500">{{ l.type }}</span> {{ l.label }}<span v-if="l.changes" class="ml-1 text-xs text-brand">rincian</span></td>
                        <td class="td text-xs text-stone-500">{{ l.ip }}</td>
                    </tr>
                    <tr v-if="open === l.id"><td colspan="5" class="bg-stone-50 px-4 py-3">
                        <dl class="grid gap-1 text-xs sm:grid-cols-[160px_1fr]">
                            <template v-for="(v, k) in l.changes" :key="k"><dt class="font-medium text-stone-600">{{ k }}</dt><dd class="break-all font-mono text-stone-700">{{ pretty(v) }}</dd></template>
                        </dl>
                    </td></tr>
                </template>
                <tr v-if="!logs.data.length"><td colspan="5" class="td py-10 text-center text-stone-500">Belum ada catatan.</td></tr>
            </tbody>
        </table>
        <Pagination :links="logs.links" />
    </div>
</template>
