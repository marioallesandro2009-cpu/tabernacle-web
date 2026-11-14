<script setup>
import { ref } from 'vue';
import RichEditor from './RichEditor.vue';
import { translate } from '../lib/translate';

// modelValue = { en: '', id: '' }
const props = defineProps({
    modelValue: { type: Object, required: true },
    type: { type: String, default: 't_text' },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },
});
const lang = ref('id');
const langs = [{ k: 'id', label: 'Indonesia' }, { k: 'en', label: 'English' }];
const set = (k, v) => (props.modelValue[k] = v);
const filled = (k) => !!(props.modelValue?.[k] || '').replace(/<[^>]+>/g, '').trim();
const busy = ref(false);
const justTranslated = ref(false);
async function autoTranslate() {
    if (filled('en') && !window.confirm('Teks bahasa Inggris yang sekarang akan diganti hasil terjemahan otomatis. Lanjutkan?')) return;
    busy.value = true;
    try {
        const [en] = await translate([{ text: props.modelValue.id || '', html: props.type === 't_rich' || props.type === 't_html' }]);
        set('en', en);
        lang.value = 'en';
        justTranslated.value = true;
    } catch {
        // the message was already shown
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <div class="mb-1 flex gap-1" role="tablist">
            <button
                v-for="l in langs" :key="l.k" type="button" role="tab" :aria-selected="lang === l.k"
                class="rounded-md px-2.5 py-1 text-xs font-semibold transition"
                :class="lang === l.k ? 'bg-brand text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200'"
                @click="lang = l.k"
            >{{ l.label }}<span v-if="!filled(l.k)" class="ml-1 text-amber-400" title="Belum diisi">●</span></button>
            <button
                v-if="filled('id')" type="button" class="ml-auto rounded-md px-2 py-1 text-xs font-medium text-brand hover:bg-brand/10 disabled:opacity-50"
                :disabled="busy" title="Isi versi Inggris dari teks Indonesia (terjemahan otomatis, periksa lagi hasilnya)" @click="autoTranslate"
            >{{ busy ? 'Menerjemahkan…' : 'Terjemahkan ke Inggris' }}</button>
        </div>
        <p v-if="lang === 'en' && filled('en') && justTranslated" class="hint">Hasil terjemahan otomatis. Periksa dan rapikan sebelum disimpan.</p>
        <template v-for="l in langs" :key="l.k">
            <div v-show="lang === l.k">
                <input v-if="type === 't_text' || type === 't_html'" :value="modelValue[l.k]" class="input" :placeholder="placeholder" @input="set(l.k, $event.target.value)">
                <textarea v-else-if="type === 't_textarea'" :value="modelValue[l.k]" rows="4" class="input" :placeholder="placeholder" @input="set(l.k, $event.target.value)" />
                <RichEditor v-else :model-value="modelValue[l.k]" @update:model-value="set(l.k, $event)" />
            </div>
        </template>
        <p v-if="type === 't_html'" class="hint">Boleh pakai &lt;em&gt;miring&lt;/em&gt;, &lt;strong&gt;tebal&lt;/strong&gt;, &lt;br&gt;.</p>
        <p v-if="error" class="err">{{ error }}</p>
    </div>
</template>
