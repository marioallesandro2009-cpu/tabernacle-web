<script setup>
import { au } from '../../lib/admin';
import { Head, router } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';

defineProps({ files: Array });
const make = () => router.post(au('/admin/backup'), {}, { preserveScroll: true });
const remove = (f) => confirm(`Hapus ${f.name}?`) && router.delete(au(`/admin/backup/${f.name}`), { preserveScroll: true });
</script>

<template>
    <Head title="Backup database" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Backup database</h1>
            <p class="text-sm text-stone-600">Backup otomatis tiap Minggu pukul 02:00 (butuh cron aktif). 8 backup terbaru disimpan di server di luar folder publik.</p>
        </div>
        <button class="btn btn-primary" @click="make"><Icon name="db" class="h-4 w-4" /> Buat backup sekarang</button>
    </div>
    <div class="card divide-y divide-stone-100">
        <div v-for="f in files" :key="f.name" class="flex flex-wrap items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1"><div class="truncate font-medium">{{ f.name }}</div><div class="text-xs text-stone-500">{{ f.at }} · {{ f.size }}</div></div>
            <a :href="au(`/admin/backup/${f.name}/download`)" class="btn btn-sm"><Icon name="download" class="h-4 w-4" /> Unduh</a>
            <button class="btn btn-sm text-red-600" aria-label="Hapus" @click="remove(f)"><Icon name="trash" class="h-4 w-4" /></button>
        </div>
        <div v-if="!files.length" class="px-4 py-10 text-center text-sm text-stone-500">Belum ada backup.</div>
    </div>
    <p class="hint mt-3">Simpan salinan file backup di tempat lain (komputer atau cloud) secara berkala. Backup ini hanya berisi database; gambar ada di folder <code>public/uploads</code>.</p>
</template>
