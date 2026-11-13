<script setup>
defineProps({ tone: { type: String, default: 'slate' }, dot: { type: Boolean, default: true } });
const tones = {
    slate: 'bg-slate-100 text-slate-600 ring-slate-200',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    red: 'bg-rose-50 text-rose-700 ring-rose-200',
    blue: 'bg-sky-50 text-sky-700 ring-sky-200',
    violet: 'bg-violet-50 text-violet-700 ring-violet-200',
};
const dots = { slate: 'bg-slate-400', green: 'bg-emerald-500', amber: 'bg-amber-500', red: 'bg-rose-500', blue: 'bg-sky-500', violet: 'bg-violet-500' };
</script>

<template>
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset" :class="tones[tone]">
        <span v-if="dot" class="h-1.5 w-1.5 rounded-full" :class="dots[tone]" /><slot />
    </span>
</template>
