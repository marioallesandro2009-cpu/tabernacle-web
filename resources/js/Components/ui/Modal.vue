<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps({ open: Boolean, title: String, subtitle: String, size: { type: String, default: 'md' } });
const emit = defineEmits(['close']);
const widths = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl' };

const onKey = (e) => e.key === 'Escape' && props.open && emit('close');
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
watch(() => props.open, (o) => (document.body.style.overflow = o ? 'hidden' : ''));
</script>

<template>
    <Teleport to="body">
        <Transition name="o-modal">
            <div v-if="open" class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" :aria-label="title">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]" @click="emit('close')" />
                <div class="o-modal-card relative flex max-h-[92vh] w-full flex-col rounded-t-3xl bg-white shadow-2xl ring-1 ring-slate-200 sm:rounded-3xl" :class="widths[size]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight text-slate-900">{{ title }}</h2>
                            <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
                        </div>
                        <button type="button" class="-mr-2 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup" @click="emit('close')">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto px-6 py-5"><slot /></div>
                    <div v-if="$slots.footer" class="flex flex-wrap justify-end gap-2 rounded-b-3xl border-t border-slate-100 bg-slate-50/70 px-6 py-3.5"><slot name="footer" /></div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
.o-modal-enter-active, .o-modal-leave-active { transition: opacity .15s ease; }
.o-modal-enter-active .o-modal-card, .o-modal-leave-active .o-modal-card { transition: transform .18s ease; }
.o-modal-enter-from, .o-modal-leave-to { opacity: 0; }
.o-modal-enter-from .o-modal-card, .o-modal-leave-to .o-modal-card { transform: translateY(12px); }
</style>
