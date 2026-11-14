<script setup>
import { au } from '../../lib/admin';
import { Head, router } from '@inertiajs/vue3';
import MediaBrowser from '../../Components/MediaBrowser.vue';

defineProps({ folders: Array });
const purge = () => confirm('Hapus SEMUA media yang belum dipakai di mana pun? Tidak bisa dibatalkan.') && router.post(au('/admin/media/purge-unused'));
</script>

<template>
    <Head title="Media library" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Media library</h1>
            <p class="text-sm text-stone-600">Semua gambar, PDF, dan video situs. Alt text wajib untuk aksesibilitas dan SEO.</p>
        </div>
        <button class="btn text-red-600" @click="purge">Hapus yang tidak terpakai</button>
    </div>
    <MediaBrowser :folders="folders" />
</template>
