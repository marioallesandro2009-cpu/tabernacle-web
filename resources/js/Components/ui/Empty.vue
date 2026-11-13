<script setup>
import Icon from '../Icon.vue';
defineProps({ icon: { type: String, default: 'inbox' }, title: String, text: String });
</script>

<template>
    <div class="flex flex-col items-center px-6 py-14 text-center">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><Icon :name="icon" class="h-6 w-6" /></div>
        <h3 class="text-base font-semibold text-slate-900">{{ title }}</h3>
        <p v-if="text" class="mt-1 max-w-sm text-sm text-slate-500">{{ text }}</p>
        <div class="mt-5 flex flex-wrap justify-center gap-2"><slot /></div>
    </div>
</template>
