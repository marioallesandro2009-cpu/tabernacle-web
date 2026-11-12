<script setup>
import Field from './Field.vue';

defineProps({
    fields: { type: Array, required: true },
    values: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    cols: { type: Boolean, default: false },
});

// short inputs sit side by side on wide screens
const half = (f) => ['number', 'date', 'datetime', 'color', 'select', 'slug', 'toggle'].includes(f.type);
</script>

<template>
    <div class="grid gap-x-5 gap-y-5" :class="cols ? 'md:grid-cols-2' : ''">
        <div v-for="f in fields" :key="f.key" :class="cols && !half(f) ? 'md:col-span-2' : ''">
            <Field :field="f" :values="values" :errors="errors" />
        </div>
    </div>
</template>
