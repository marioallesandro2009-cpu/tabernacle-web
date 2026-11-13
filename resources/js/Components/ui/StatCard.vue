<script setup>
defineProps({ label: String, value: [Number, String], hint: String, tone: { type: String, default: 'slate' } });
const dot = { slate: 'bg-slate-300', green: 'bg-emerald-500', amber: 'bg-amber-500', red: 'bg-rose-500', blue: 'bg-sky-500', violet: 'bg-violet-500' };
</script>

<template>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/80">
        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500">
            <span class="h-2 w-2 rounded-full" :class="dot[tone]" />{{ label }}
        </div>
        <div class="mt-2 text-3xl font-semibold tabular-nums tracking-tight text-slate-900">{{ value }}</div>
        <div v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</div>
    </div>
</template>
