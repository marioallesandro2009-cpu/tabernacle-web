<script setup>
import { computed, ref } from 'vue';
import Icon from './Icon.vue';
import MediaDialog from './MediaDialog.vue';
import { mediaStore, remember } from '../lib/media';

// single: modelValue = id | null ; multiple: modelValue = [ids]
const props = defineProps({
    modelValue: { default: null },
    multiple: { type: Boolean, default: false },
    kind: { type: String, default: 'image' },
});
const emit = defineEmits(['update:modelValue']);
const open = ref(false);

const ids = computed(() => (props.multiple ? props.modelValue || [] : props.modelValue ? [props.modelValue] : []));
const items = computed(() => ids.value.map((id) => mediaStore[id] || { id, name: '#' + id, kind: 'image', thumb: '', url: '' }));

function pick(list) {
    list.forEach(remember);
    if (props.multiple) emit('update:modelValue', [...new Set([...(props.modelValue || []), ...list.map((m) => m.id)])]);
    else emit('update:modelValue', list[0]?.id ?? null);
    open.value = false;
}
const removeAt = (id) => emit('update:modelValue', props.multiple ? ids.value.filter((x) => x !== id) : null);
const move = (i, d) => {
    const a = [...ids.value];
    const j = i + d;
    if (j < 0 || j >= a.length) return;
    [a[i], a[j]] = [a[j], a[i]];
    emit('update:modelValue', a);
};
</script>

<template>
    <div>
        <div class="flex flex-wrap gap-2">
            <div v-for="(m, i) in items" :key="m.id" class="group relative h-24 w-24 overflow-hidden rounded-lg border border-stone-300 bg-stone-100">
                <img v-if="m.kind === 'image' && m.thumb" :src="m.thumb" :alt="m.alt || m.name" class="h-full w-full object-cover">
                <div v-else class="flex h-full flex-col items-center justify-center gap-1 p-1 text-center text-[11px] text-stone-600">
                    <Icon name="doc" class="h-5 w-5" /><span class="line-clamp-2 break-all">{{ m.name }}</span>
                </div>
                <div class="absolute inset-x-0 bottom-0 flex justify-between bg-black/60 px-1 py-0.5 text-white opacity-0 transition group-hover:opacity-100">
                    <template v-if="multiple">
                        <button type="button" class="px-1" aria-label="Geser ke kiri" @click="move(i, -1)">‹</button>
                        <button type="button" class="px-1" aria-label="Geser ke kanan" @click="move(i, 1)">›</button>
                    </template>
                    <button type="button" class="px-1" aria-label="Hapus" @click="removeAt(m.id)">✕</button>
                </div>
            </div>
            <button type="button" class="flex h-24 w-24 flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-stone-300 text-xs text-stone-500 hover:border-brand hover:text-brand" @click="open = true">
                <Icon name="plus" class="h-5 w-5" />{{ multiple ? 'Tambah' : items.length ? 'Ganti' : 'Pilih' }}
            </button>
        </div>
        <MediaDialog v-if="open" :kind="kind" :multiple="multiple" @pick="pick" @close="open = false" />
    </div>
</template>
