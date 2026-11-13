<script setup>
defineProps({ title: String, subtitle: String });
</script>

<template>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-[26px]">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500">{{ subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2"><slot /></div>
    </header>
</template>
