<script setup>
import { computed, reactive, ref } from 'vue';
import TransField from './TransField.vue';
import RichEditor from './RichEditor.vue';
import MediaPicker from './MediaPicker.vue';
import Icon from './Icon.vue';
import { blankRow } from '../lib/schema';
import { translate, hasText, KEEP } from '../lib/translate';

// `values` is the object that holds this field's value; `errors` is the flat Inertia error bag.
const props = defineProps({
    field: { type: Object, required: true },
    values: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    prefix: { type: String, default: '' },
});

const path = computed(() => props.prefix + props.field.key);
const err = computed(() => props.errors[path.value] || props.errors[path.value + '.en'] || '');
const type = computed(() => props.field.type);
const isTrans = computed(() => ['t_text', 't_textarea', 't_html', 't_rich'].includes(type.value));
const openRows = reactive({});

const val = computed({
    get: () => props.values[props.field.key],
    set: (v) => (props.values[props.field.key] = v),
});

const rows = computed(() => props.values[props.field.key] || []);
function addRow() {
    if (!Array.isArray(props.values[props.field.key])) props.values[props.field.key] = [];
    props.values[props.field.key].push(blankRow(props.field.fields));
    openRows[props.values[props.field.key].length - 1] = true;
}
const removeRow = (i) => props.values[props.field.key].splice(i, 1);
function moveRow(i, d) {
    const a = props.values[props.field.key];
    const j = i + d;
    if (j < 0 || j >= a.length) return;
    [a[i], a[j]] = [a[j], a[i]];
}
function rowTitle(row, i) {
    const k = props.field.item_label;
    let v = k ? row[k] : null;
    if (v && typeof v === 'object') v = v.id || v.en;
    return (v && String(v).replace(/<[^>]+>/g, '').slice(0, 70)) || `Item ${i + 1}`;
}

// "Terjemahkan dari Indonesia": an English field next to its Indonesian source (name_en ← name, text_en ← text_id)
const sourceKey = computed(() => {
    const k = props.field.key;
    if (k === 'text_en') return 'text_id';
    return k.endsWith('_en') ? k.slice(0, -3) : null;
});
const canTranslate = computed(() => sourceKey.value && sourceKey.value in props.values && ['text', 'textarea', 'rich', 'list', 'repeater'].includes(type.value));
const sourceFilled = computed(() => canTranslate.value && hasText(props.values[sourceKey.value]));
const translating = ref(false);
const translated = ref(false);
async function translateFromSource() {
    const src = props.values[sourceKey.value];
    if (hasText(val.value) && !window.confirm('Isi bahasa Inggris yang sekarang akan diganti hasil terjemahan otomatis. Lanjutkan?')) return;
    translating.value = true;
    try {
        if (type.value === 'list') {
            const items = (src || []).map((t) => ({ text: t || '', html: false }));
            val.value = items.length ? await translate(items) : [];
        } else if (type.value === 'repeater') {
            // translate the matching sub-fields of every row; names (hotel …) are copied
            const subs = props.field.fields;
            const jobs = [];
            (src || []).forEach((row, r) => subs.forEach((f) => {
                const v = row?.[f.key];
                if (typeof v === 'string' && !KEEP.includes(f.key) && hasText(v)) jobs.push({ r, key: f.key, text: v, html: f.type === 'rich' });
            }));
            const out = jobs.length ? await translate(jobs.map(({ text, html }) => ({ text, html }))) : [];
            const rows = (src || []).map((row) => Object.fromEntries(subs.map((f) => [f.key, KEEP.includes(f.key) ? (row?.[f.key] ?? '') : ''])));
            jobs.forEach((j, i) => (rows[j.r][j.key] = out[i]));
            val.value = rows;
        } else {
            [val.value] = await translate([{ text: src || '', html: type.value === 'rich' }]);
        }
        translated.value = true;
    } catch {
        // the message was already shown
    } finally {
        translating.value = false;
    }
}

// simple string list
const list = computed(() => props.values[props.field.key] || []);
const listAdd = () => {
    if (!Array.isArray(props.values[props.field.key])) props.values[props.field.key] = [];
    props.values[props.field.key].push('');
};

// multi select
const multi = computed({
    get: () => props.values[props.field.key] || [],
    set: (v) => (props.values[props.field.key] = v),
});
function toggleMulti(v) {
    multi.value = multi.value.includes(v) ? multi.value.filter((x) => x !== v) : [...multi.value, v];
}
</script>

<template>
    <div class="min-w-0">
        <!-- toggle -->
        <label v-if="type === 'toggle'" class="flex cursor-pointer items-center gap-3">
            <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition" :class="val ? 'bg-brand' : 'bg-stone-300'">
                <input v-model="val" type="checkbox" class="peer sr-only">
                <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition" :class="val ? 'translate-x-5' : ''" />
            </span>
            <span class="text-sm font-medium text-stone-700">{{ field.label }}</span>
        </label>

        <template v-else>
            <div v-if="canTranslate" class="-mb-1 flex justify-end">
                <button type="button" class="rounded-md px-2 py-0.5 text-xs font-medium text-brand hover:bg-brand/10 disabled:opacity-40" :disabled="translating || !sourceFilled"
                    :title="sourceFilled ? 'Isi dari versi Indonesia dengan terjemahan otomatis (periksa lagi hasilnya)' : 'Versi Indonesia masih kosong'" @click="translateFromSource">
                    {{ translating ? 'Menerjemahkan…' : 'Terjemahkan dari Indonesia' }}
                </button>
            </div>
            <label v-if="type !== 'repeater'" class="label" :for="'f-' + path">{{ field.label }}<span v-if="field.required" class="text-red-500"> *</span></label>

            <TransField v-if="isTrans && type !== 't_rich'" :model-value="val" :type="type" :error="err" />
            <TransField v-else-if="type === 't_rich'" :model-value="val" type="t_rich" :error="err" />

            <input v-else-if="['text', 'url', 'email'].includes(type)" :id="'f-' + path" v-model="val" :type="field.input === 'password' ? 'password' : type === 'text' ? 'text' : type" class="input" :autocomplete="field.input === 'password' ? 'new-password' : 'off'">
            <input v-else-if="type === 'number'" :id="'f-' + path" v-model="val" type="number" step="any" :min="field.min ?? 0" class="input">
            <textarea v-else-if="type === 'textarea'" :id="'f-' + path" v-model="val" rows="5" class="input" />
            <RichEditor v-else-if="type === 'rich'" v-model="val" />
            <input v-else-if="type === 'date'" :id="'f-' + path" v-model="val" type="date" class="input">
            <input v-else-if="type === 'datetime'" :id="'f-' + path" v-model="val" type="datetime-local" class="input">
            <div v-else-if="type === 'color'" class="flex items-center gap-2">
                <input v-model="val" type="color" class="h-9 w-12 cursor-pointer rounded border border-stone-300 bg-white p-0.5" :aria-label="field.label">
                <input v-model="val" class="input max-w-[140px] font-mono" maxlength="7">
            </div>
            <div v-else-if="type === 'slug'">
                <input :id="'f-' + path" v-model="val" class="input font-mono" placeholder="otomatis dari judul bila dikosongkan">
            </div>
            <select v-else-if="type === 'select'" :id="'f-' + path" v-model="val" class="input">
                <option :value="null">{{ field.required ? 'Pilih…' : '— kosong —' }}</option>
                <option v-for="o in field.options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <MediaPicker v-else-if="type === 'image'" v-model="val" kind="image" />
            <MediaPicker v-else-if="type === 'images'" v-model="val" multiple kind="image" />
            <MediaPicker v-else-if="type === 'file'" v-model="val" :kind="field.kind || 'file'" />

            <div v-else-if="type === 'list'" class="space-y-2">
                <div v-for="(item, i) in list" :key="i" class="flex gap-2">
                    <input v-model="list[i]" class="input">
                    <button type="button" class="btn btn-sm" aria-label="Hapus" @click="list.splice(i, 1)"><Icon name="x" class="h-4 w-4" /></button>
                </div>
                <button type="button" class="btn btn-sm" @click="listAdd"><Icon name="plus" class="h-4 w-4" /> Tambah</button>
            </div>

            <div v-else-if="type === 'multi'" class="max-h-56 overflow-y-auto rounded-lg border border-stone-300 bg-white p-2">
                <label v-for="o in field.options" :key="o.value" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-stone-50">
                    <input type="checkbox" :checked="multi.includes(o.value)" @change="toggleMulti(o.value)"> {{ o.label }}
                </label>
                <p v-if="!field.options.length" class="p-2 text-sm text-stone-500">Belum ada pilihan.</p>
            </div>

            <div v-else-if="type === 'repeater'">
                <div class="label">{{ field.label }}</div>
                <div class="space-y-2">
                    <div v-for="(row, i) in rows" :key="i" class="rounded-lg border border-stone-200 bg-stone-50">
                        <div class="flex items-center gap-1 px-2 py-1.5">
                            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left text-sm font-medium" @click="openRows[i] = !openRows[i]">
                                <Icon :name="openRows[i] ? 'down' : 'up'" class="h-4 w-4 shrink-0 rotate-90" :class="openRows[i] ? '!rotate-0' : ''" />
                                <span class="truncate">{{ rowTitle(row, i) }}</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" aria-label="Naik" @click="moveRow(i, -1)"><Icon name="up" class="h-4 w-4" /></button>
                            <button type="button" class="btn btn-ghost btn-sm" aria-label="Turun" @click="moveRow(i, 1)"><Icon name="down" class="h-4 w-4" /></button>
                            <button type="button" class="btn btn-ghost btn-sm text-red-600" aria-label="Hapus item" @click="removeRow(i)"><Icon name="trash" class="h-4 w-4" /></button>
                        </div>
                        <div v-show="openRows[i]" class="space-y-4 border-t border-stone-200 bg-white p-3">
                            <Field v-for="sub in field.fields" :key="sub.key" :field="sub" :values="row" :errors="errors" :prefix="path + '.' + i + '.'" />
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm mt-2" @click="addRow"><Icon name="plus" class="h-4 w-4" /> Tambah item</button>
            </div>
            <p v-if="translated" class="hint text-amber-700">Hasil terjemahan otomatis. Periksa dan rapikan sebelum disimpan.</p>
            <p v-if="field.hint" class="hint">{{ field.hint }}</p>
            <p v-if="err && !isTrans" class="err">{{ err }}</p>
        </template>
        <p v-if="type === 'toggle' && err" class="err">{{ err }}</p>
    </div>
</template>
