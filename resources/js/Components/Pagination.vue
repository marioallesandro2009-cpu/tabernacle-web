<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ links: { type: Array, default: () => [] } });
const label = (l) => l.label.replace('&laquo;', '‹').replace('&raquo;', '›').replace('Previous', '').replace('Next', '').trim() || '…';
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-1 py-3" aria-label="Halaman">
        <template v-for="(l, i) in links" :key="i">
            <Link v-if="l.url" :href="l.url" preserve-scroll class="min-w-8 rounded-md border px-2.5 py-1 text-center text-sm" :class="l.active ? 'border-brand bg-brand text-white' : 'border-stone-300 bg-white hover:bg-stone-50'">{{ label(l) }}</Link>
            <span v-else class="min-w-8 rounded-md border border-stone-200 px-2.5 py-1 text-center text-sm text-stone-300">{{ label(l) }}</span>
        </template>
    </nav>
</template>
