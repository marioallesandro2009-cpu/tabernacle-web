<script setup>
import { ref } from 'vue';
import MediaBrowser from './MediaBrowser.vue';

defineProps({ kind: { type: String, default: '' }, multiple: { type: Boolean, default: false } });
const emit = defineEmits(['pick', 'close']);
const browser = ref(null);
</script>

<template>
    <div class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-3" @keydown.esc="emit('close')">
        <div class="flex max-h-[92vh] w-full max-w-5xl flex-col rounded-2xl bg-stone-100 p-4 shadow-2xl" role="dialog" aria-modal="true" aria-label="Pilih media">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Pilih media</h2>
                <button class="btn btn-ghost btn-sm" aria-label="Tutup" @click="emit('close')">✕</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <MediaBrowser ref="browser" select :multiple="multiple" :kind="kind" @pick="emit('pick', $event)" />
            </div>
            <div class="mt-3 flex justify-end gap-2">
                <button class="btn" @click="emit('close')">Batal</button>
                <button class="btn btn-primary" :disabled="!browser?.picked?.length" @click="emit('pick', browser.picked)">Gunakan yang dipilih</button>
            </div>
        </div>
    </div>
</template>
