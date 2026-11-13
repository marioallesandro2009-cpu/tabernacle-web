<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import StatCard from '../../../Components/ui/StatCard.vue';
import Chip from '../../../Components/ui/Chip.vue';
import { importStatus } from '../../../lib/outreach';

const props = defineProps({ batch: Object });
const running = computed(() => props.batch.status === 'processing');

// while the queue is still reading the file, look again every few seconds
let timer;
onMounted(() => {
    timer = setInterval(() => { if (running.value) router.reload({ only: ['batch'], preserveScroll: true }); }, 2500);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head title="Hasil impor" />
    <PageHeader title="Hasil impor" :subtitle="batch.filename">
        <Chip :tone="importStatus[batch.status]?.tone">{{ importStatus[batch.status]?.label }}</Chip>
        <Link href="/admin/outreach/contacts" class="o-btn o-btn-primary">Lihat kontak</Link>
    </PageHeader>

    <div v-if="running" class="o-card mb-5 flex items-center gap-4 p-5">
        <svg class="h-6 w-6 shrink-0 animate-spin text-brand" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".2" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        <div><div class="font-medium text-slate-900">File sedang dibaca…</div><div class="text-sm text-slate-500">Proses berjalan di latar belakang. Halaman ini diperbarui sendiri, dan boleh ditinggalkan.</div></div>
    </div>
    <div v-else-if="batch.status === 'failed'" class="mb-5 rounded-2xl bg-rose-50 p-5 text-sm text-rose-800 ring-1 ring-rose-100">
        <div class="font-semibold">Impor gagal</div><div class="mt-1">{{ batch.error || 'File tidak bisa diproses.' }}</div>
        <Link href="/admin/outreach/import" class="o-btn mt-3">Coba file lain</Link>
    </div>
    <div v-else-if="batch.status === 'done'" class="mb-5 rounded-2xl bg-emerald-50 p-5 text-sm text-emerald-900 ring-1 ring-emerald-100">
        <div class="font-semibold">Impor selesai</div>
        <div class="mt-1">Alamat email sedang dicek di latar belakang (kosong, salah format, dan domain tak aktif akan ditandai). Hasilnya terlihat di halaman Kontak.</div>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <StatCard label="Baris di file" :value="batch.total" />
        <StatCard label="Kontak baru" :value="batch.created" tone="green" />
        <StatCard label="Dilewati (dobel)" :value="batch.duplicates" tone="amber" hint="Email sudah ada" />
        <StatCard label="Tanpa email" :value="batch.empty" tone="slate" />
        <StatCard label="Jangan dikirimi" :value="batch.suppressed" tone="violet" hint="Pernah berhenti" />
    </div>
</template>
