<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';

defineProps({ pages: Array });
</script>

<template>
    <Head title="Halaman & section" />
    <h1 class="mb-1 text-2xl font-bold">Halaman & section</h1>
    <p class="mb-5 text-sm text-stone-600">Setiap halaman tersusun dari section. Ubah isi, urutan, atau sembunyikan section tanpa menyentuh kode.</p>
    <div class="card divide-y divide-stone-100">
        <div v-for="p in pages" :key="p.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <Link :href="`/admin/pages/${p.id}/edit`" class="font-medium text-brand hover:underline">{{ p.title }}</Link>
                <div class="text-xs text-stone-500">{{ p.sections }} section · {{ p.url }}</div>
            </div>
            <span v-if="!p.is_active" class="badge">Nonaktif</span>
            <a :href="p.url" target="_blank" class="btn btn-ghost btn-sm" aria-label="Lihat"><Icon name="external" class="h-4 w-4" /></a>
            <Link :href="`/admin/pages/${p.id}/edit`" class="btn btn-sm"><Icon name="edit" class="h-4 w-4" /> Ubah</Link>
        </div>
    </div>
</template>
