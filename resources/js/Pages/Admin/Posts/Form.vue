<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Field from '../../../Components/Field.vue';
import Icon from '../../../Components/Icon.vue';
import { rememberAll } from '../../../lib/media';

const props = defineProps({ resource: Object, fields: Array, values: Object, id: [Number, String], media: Object, site_url: String, all_tags: Array, preview_url: String, draft_preview_url: String });
rememberAll(props.media);

const form = useForm(JSON.parse(JSON.stringify(props.values)));
const isEdit = computed(() => !!props.id);
const f = (key) => props.fields.find((x) => x.key === key);
const errors = computed(() => form.errors);

const save = () => {
    const o = { preserveScroll: true };
    isEdit.value ? form.put(`${props.resource.url}/${props.id}`, o) : form.post(props.resource.url, o);
};
const remove = () => confirm('Hapus artikel ini permanen?') && router.delete(`${props.resource.url}/${props.id}`);

// ---- live SEO analysis ----
const seo = ref(null);
const busy = ref(false);
let timer;
async function analyze() {
    busy.value = true;
    try {
        const { data } = await axios.post(`${props.resource.url}/analyze`, {
            id: props.id, title: form.title, slug: form.slug, focus_keyword: form.focus_keyword, meta_title: form.meta_title,
            meta_description: form.meta_description, content: form.content, cover_id: form.cover_id, og_image_id: form.og_image_id,
        });
        seo.value = data;
    } catch { /* keep last result */ } finally { busy.value = false; }
}
const schedule = () => { clearTimeout(timer); timer = setTimeout(analyze, 700); };
onMounted(analyze);
watch(() => [form.title, form.slug, form.focus_keyword, form.meta_title, form.meta_description, form.content, form.cover_id, form.og_image_id], schedule);

const scoreTone = computed(() => (!seo.value ? 'text-stone-400' : seo.value.score >= 80 ? 'text-emerald-600' : seo.value.score >= 50 ? 'text-amber-600' : 'text-red-600'));
const icon = { pass: ['✓', 'text-emerald-600'], warn: ['!', 'text-amber-600'], fail: ['✕', 'text-red-600'] };
const mtLen = computed(() => (form.meta_title || form.title || '').length);
const mdLen = computed(() => (form.meta_description || '').length);
const counter = (n, lo, hi) => (n >= lo && n <= hi ? 'text-emerald-600' : n === 0 ? 'text-stone-400' : 'text-amber-600');
const slugPreview = computed(() => form.slug || (form.title || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));

// ---- tags ----
const tagInput = ref('');
function addTag() {
    const t = tagInput.value.trim().replace(/,$/, '');
    if (t && !form.tags.includes(t) && form.tags.length < 15) form.tags.push(t);
    tagInput.value = '';
}
</script>

<template>
    <Head :title="(isEdit ? 'Ubah ' : 'Tulis ') + 'artikel'" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <Link :href="resource.url" class="text-sm text-stone-500 hover:text-brand">← Artikel</Link>
            <h1 class="text-2xl font-bold">{{ isEdit ? 'Ubah artikel' : 'Tulis artikel' }}</h1>
        </div>
        <div class="flex gap-2">
            <a v-if="draft_preview_url" :href="draft_preview_url" target="_blank" class="btn" :title="form.isDirty ? 'Pratinjau menampilkan versi yang sudah disimpan. Simpan dulu untuk melihat perubahan terbaru.' : 'Lihat artikel seperti yang akan dilihat pengunjung'"><Icon name="eye" class="h-4 w-4" /> Pratinjau<span v-if="form.isDirty" class="text-amber-600">*</span></a>
            <a v-if="preview_url" :href="preview_url" target="_blank" class="btn"><Icon name="external" class="h-4 w-4" /> Lihat</a>
            <button v-if="isEdit" class="btn text-red-600" @click="remove">Hapus</button>
            <button class="btn btn-primary" :disabled="form.processing" @click="save">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
    </div>

    <div v-if="Object.keys(errors).length" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Ada isian yang perlu diperbaiki, lihat pesan merah di bawah.</div>

    <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-5">
            <div class="card space-y-5 p-5">
                <Field :field="f('title')" :values="form" :errors="errors" />
                <Field :field="f('slug')" :values="form" :errors="errors" />
                <Field :field="f('excerpt')" :values="form" :errors="errors" />
                <Field :field="f('content')" :values="form" :errors="errors" />
                <Field :field="f('faq')" :values="form" :errors="errors" />
            </div>

            <details class="card p-5" :open="!!(form.title_en || form.content_en)">
                <summary class="cursor-pointer font-semibold">Versi Inggris (English) <span class="text-sm font-normal text-stone-500">opsional</span></summary>
                <p class="hint mt-2">Tampil saat pengunjung memilih bahasa English. Kalau Judul (English) kosong, situs Inggris menampilkan versi Indonesia.</p>
                <div class="mt-4 space-y-5">
                    <Field :field="f('title_en')" :values="form" :errors="errors" />
                    <Field :field="f('excerpt_en')" :values="form" :errors="errors" />
                    <Field :field="f('content_en')" :values="form" :errors="errors" />
                    <Field :field="f('faq_en')" :values="form" :errors="errors" />
                    <Field :field="f('meta_title_en')" :values="form" :errors="errors" />
                    <Field :field="f('meta_description_en')" :values="form" :errors="errors" />
                </div>
            </details>

            <div class="card space-y-5 p-5">
                <h2 class="font-semibold">SEO</h2>
                <Field :field="f('focus_keyword')" :values="form" :errors="errors" />
                <div>
                    <Field :field="f('meta_title')" :values="form" :errors="errors" />
                    <p class="hint" :class="counter(mtLen, 50, 60)">{{ mtLen }} karakter (ideal 50–60)</p>
                </div>
                <div>
                    <Field :field="f('meta_description')" :values="form" :errors="errors" />
                    <p class="hint" :class="counter(mdLen, 120, 160)">{{ mdLen }} karakter (ideal 120–160)</p>
                </div>
                <Field :field="f('og_image_id')" :values="form" :errors="errors" />
                <div class="rounded-lg border border-stone-200 p-3">
                    <div class="mb-1 text-xs text-stone-500">Pratinjau di Google</div>
                    <div class="truncate text-lg text-blue-700">{{ form.meta_title || form.title || 'Judul artikel' }}</div>
                    <div class="truncate text-xs text-emerald-700">{{ site_url }}/{{ slugPreview }}</div>
                    <div class="line-clamp-2 text-sm text-stone-600">{{ form.meta_description || form.excerpt || 'Deskripsi artikel akan tampil di sini.' }}</div>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card space-y-4 p-5">
                <h2 class="font-semibold">Penerbitan</h2>
                <Field :field="f('status')" :values="form" :errors="errors" />
                <Field v-if="form.status !== 'draft' || form.published_at" :field="f('published_at')" :values="form" :errors="errors" />
                <p v-if="form.status === 'scheduled'" class="hint">Artikel terbit otomatis pada waktu ini (dicek tiap 5 menit).</p>
                <Field :field="f('cover_id')" :values="form" :errors="errors" />
                <Field :field="f('category_id')" :values="form" :errors="errors" />
                <Field :field="f('author_id')" :values="form" :errors="errors" />
                <div>
                    <label class="label" for="taginput">Tag</label>
                    <div class="mb-2 flex flex-wrap gap-1">
                        <span v-for="(t, i) in form.tags" :key="t" class="badge gap-1">{{ t }}<button type="button" aria-label="Hapus tag" @click="form.tags.splice(i, 1)">✕</button></span>
                    </div>
                    <input id="taginput" v-model="tagInput" list="all-tags" class="input" placeholder="Ketik lalu Enter" @keydown.enter.prevent="addTag" @keydown.188.prevent="addTag" @blur="addTag">
                    <datalist id="all-tags"><option v-for="t in all_tags" :key="t" :value="t" /></datalist>
                </div>
            </div>

            <div class="card p-5 xl:sticky xl:top-4">
                <div class="mb-3 flex items-center gap-3">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full border-4 text-xl font-bold" :class="scoreTone" :style="{ borderColor: 'currentColor' }">{{ seo ? seo.score : '…' }}</div>
                    <div>
                        <div class="font-semibold">Skor SEO</div>
                        <div v-if="seo" class="text-xs text-stone-500">{{ seo.stats.words }} kata · {{ seo.stats.reading_time }} menit baca</div>
                        <div v-if="busy" class="text-xs text-stone-400">menganalisis…</div>
                    </div>
                </div>
                <ul v-if="seo" class="max-h-[60vh] space-y-2 overflow-y-auto pr-1 text-sm">
                    <li v-for="c in seo.checks" :key="c.id" class="flex gap-2">
                        <span class="mt-0.5 w-4 shrink-0 text-center font-bold" :class="icon[c.status][1]">{{ icon[c.status][0] }}</span>
                        <span><span class="font-medium">{{ c.label }}</span><br><span class="text-xs text-stone-500">{{ c.tip }}</span></span>
                    </li>
                </ul>
                <div v-if="seo?.suggestions?.length" class="mt-4 border-t border-stone-100 pt-3">
                    <div class="mb-1 text-xs font-semibold text-stone-600">Ide link internal (salin alamatnya ke tautan)</div>
                    <ul class="space-y-1 text-xs"><li v-for="s in seo.suggestions" :key="s.url"><span class="text-stone-700">{{ s.label }}</span> <code class="text-brand">{{ s.url }}</code></li></ul>
                </div>
            </div>
        </div>
    </div>
</template>
