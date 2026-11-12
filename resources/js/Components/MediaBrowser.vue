<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import Icon from './Icon.vue';
import { remember } from '../lib/media';

const props = defineProps({
    select: { type: Boolean, default: false }, // picker mode
    multiple: { type: Boolean, default: false },
    kind: { type: String, default: '' }, // image | file | video | ''
    folders: { type: Array, default: () => [] },
});
const emit = defineEmits(['pick']);

const items = ref([]);
const next = ref(null);
const total = ref(0);
const loading = ref(false);
const q = ref('');
const onlyUnused = ref(false);
const folderFilter = ref('');
const active = ref(null); // item shown in the detail pane
const picked = ref([]);
const upload = reactive({ busy: false, alt: '', folder: 'umum', errors: [], over: false });
const detail = reactive({ alt: '', usage: null, saving: false, error: '' });
const fileInput = ref(null);
let timer;

const accept = computed(() => (props.kind === 'image' ? 'image/*' : props.kind === 'video' ? 'video/mp4,video/webm' : props.kind === 'file' ? '.pdf,.svg' : 'image/*,.pdf,.svg,video/mp4,video/webm'));

async function load(reset = true) {
    loading.value = true;
    try {
        const params = { q: q.value || undefined, kind: props.kind || undefined, unused: onlyUnused.value ? 1 : undefined, folder: folderFilter.value || undefined };
        const url = reset || !next.value ? '/admin/media/list' : next.value;
        const { data } = await axios.get(url, { params: reset || !next.value ? params : undefined });
        data.data.forEach(remember);
        items.value = reset ? data.data : [...items.value, ...data.data];
        next.value = data.next;
        total.value = data.total;
    } finally {
        loading.value = false;
    }
}
onMounted(() => load());
watch([q, onlyUnused, folderFilter], () => {
    clearTimeout(timer);
    timer = setTimeout(() => load(), 250);
});

function show(m) {
    active.value = m;
    detail.alt = m.alt || '';
    detail.usage = null;
    detail.error = '';
    if (!props.select) axios.get(`/admin/media/${m.id}/usage`).then((r) => (detail.usage = r.data.usage));
}

function toggle(m) {
    show(m);
    if (!props.select) return;
    if (props.multiple) {
        picked.value = picked.value.some((x) => x.id === m.id) ? picked.value.filter((x) => x.id !== m.id) : [...picked.value, m];
    } else {
        picked.value = [m];
    }
}
const isPicked = (m) => picked.value.some((x) => x.id === m.id);

async function send(files) {
    files = [...files];
    if (!files.length) return;
    const hasImage = files.some((f) => f.type.startsWith('image/') && f.type !== 'image/svg+xml');
    upload.errors = [];
    // same limits as the server, checked before a long upload starts
    const limitMb = (f) => (/^video\//.test(f.type) || /\.(mp4|webm)$/i.test(f.name) ? 50 : /\.pdf$/i.test(f.name) ? 30 : 12);
    const tooBig = files.filter((f) => f.size > limitMb(f) * 1024 * 1024);
    if (tooBig.length) {
        upload.errors = tooBig.map((f) => `${f.name}: ${(f.size / 1048576).toFixed(1)} MB, maksimal ${limitMb(f)} MB. ${limitMb(f) === 50 ? 'Untuk video panjang, unggah ke YouTube lalu sisipkan tautannya.' : ''}`);
        files = files.filter((f) => !tooBig.includes(f));
        if (!files.length) return;
    }
    if (hasImage && !upload.alt.trim()) {
        upload.errors = ['Isi teks alternatif (alt) dulu, wajib untuk gambar.'];
        return;
    }
    upload.busy = true;
    const fd = new FormData();
    files.forEach((f) => fd.append('files[]', f));
    if (upload.alt.trim()) fd.append('alt', upload.alt.trim());
    fd.append('folder', upload.folder || 'umum');
    try {
        const { data } = await axios.post('/admin/media', fd);
        data.data.forEach(remember);
        items.value = [...data.data, ...items.value];
        total.value += data.data.length;
        upload.errors = [...upload.errors, ...(data.errors || [])];
        if (data.data.length) {
            upload.alt = '';
            if (props.select) data.data.forEach((m) => (props.multiple ? picked.value.push(m) : (picked.value = [m])));
            show(data.data[0]);
        }
    } catch (e) {
        const d = e.response?.data;
        if (e.response?.status === 413) {
            upload.errors = ['File terlalu besar untuk server. Batas: gambar 12 MB, PDF 30 MB, video 50 MB.'];
        } else {
            upload.errors = d?.errors?.length && Array.isArray(d.errors) ? d.errors : [d?.message || 'Upload gagal.', ...Object.values(d?.errors || {}).flat()].filter((x) => typeof x === 'string');
        }
    } finally {
        upload.busy = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

function drop(e) {
    upload.over = false;
    send(e.dataTransfer.files);
}

async function saveAlt() {
    detail.saving = true;
    detail.error = '';
    try {
        const { data } = await axios.put(`/admin/media/${active.value.id}`, { alt: detail.alt });
        remember(data);
        Object.assign(active.value, data);
        const i = items.value.findIndex((x) => x.id === data.id);
        if (i >= 0) items.value[i] = data;
    } catch (e) {
        detail.error = e.response?.data?.errors?.alt?.[0] || 'Gagal menyimpan.';
    } finally {
        detail.saving = false;
    }
}

async function remove() {
    if (!confirm('Hapus file ini permanen?')) return;
    try {
        await axios.delete(`/admin/media/${active.value.id}`);
        items.value = items.value.filter((x) => x.id !== active.value.id);
        picked.value = picked.value.filter((x) => x.id !== active.value.id);
        active.value = null;
        total.value--;
    } catch (e) {
        detail.error = e.response?.data?.message || 'Gagal menghapus.';
    }
}

defineExpose({ picked, reload: load });
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div
            class="rounded-xl border-2 border-dashed p-4 text-center transition"
            :class="upload.over ? 'border-brand bg-brand/5' : 'border-stone-300 bg-stone-50'"
            @dragover.prevent="upload.over = true"
            @dragleave="upload.over = false"
            @drop.prevent="drop"
        >
            <div class="flex flex-wrap items-end justify-center gap-3">
                <div class="w-full max-w-xs text-left">
                    <label class="label">Teks alternatif (alt) untuk gambar yang diunggah</label>
                    <input v-model="upload.alt" class="input" placeholder="contoh: Wisatawan di Menara Eiffel, Paris">
                </div>
                <div class="w-40 text-left">
                    <label class="label">Folder</label>
                    <input v-model="upload.folder" class="input" list="media-folders" maxlength="60">
                    <datalist id="media-folders"><option v-for="f in folders" :key="f" :value="f" /></datalist>
                </div>
                <button class="btn btn-primary" :disabled="upload.busy" @click="fileInput.click()">
                    <Icon name="upload" class="h-4 w-4" /> {{ upload.busy ? 'Mengunggah…' : 'Pilih file' }}
                </button>
                <input ref="fileInput" type="file" multiple class="hidden" :accept="accept" @change="send($event.target.files)">
            </div>
            <p class="hint">Atau seret file ke sini. Gambar otomatis menjadi WebP dalam beberapa ukuran. Batas ukuran: gambar 12 MB, PDF 30 MB, video MP4/WebM 50 MB.</p>
            <ul v-if="upload.errors.length" class="mt-2 space-y-0.5 text-left">
                <li v-for="(er, i) in upload.errors" :key="i" class="err">{{ er }}</li>
            </ul>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[200px] flex-1">
                <Icon name="search" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-stone-400" />
                <input v-model="q" class="input pl-8" placeholder="Cari nama atau alt…">
            </div>
            <select v-if="folders.length" v-model="folderFilter" class="input w-auto">
                <option value="">Semua folder</option>
                <option v-for="f in folders" :key="f" :value="f">{{ f }}</option>
            </select>
            <label v-if="!select" class="flex items-center gap-2 text-sm"><input v-model="onlyUnused" type="checkbox"> Belum dipakai</label>
            <span class="text-xs text-stone-500">{{ total }} file</span>
        </div>

        <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[1fr_280px]">
            <div class="min-h-[240px] overflow-y-auto rounded-xl border border-stone-200 bg-white p-2">
                <div v-if="!items.length && !loading" class="p-10 text-center text-sm text-stone-500">Belum ada file.</div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                    <button
                        v-for="m in items"
                        :key="m.id"
                        type="button"
                        class="group relative aspect-square overflow-hidden rounded-lg border bg-stone-100 text-left"
                        :class="isPicked(m) ? 'border-brand ring-2 ring-brand' : active?.id === m.id ? 'border-accent ring-2 ring-accent' : 'border-stone-200'"
                        @click="toggle(m)"
                        @dblclick="select && !multiple && emit('pick', [m])"
                    >
                        <img v-if="m.kind === 'image'" :src="m.thumb" :alt="m.alt || m.name" loading="lazy" class="h-full w-full object-cover">
                        <div v-else class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-stone-600">
                            <Icon :name="m.kind === 'video' ? 'eye' : 'doc'" class="h-6 w-6" />
                            <span class="line-clamp-2 break-all">{{ m.name }}</span>
                        </div>
                        <span v-if="isPicked(m)" class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-brand text-white"><Icon name="check" class="h-3 w-3" /></span>
                        <span v-if="m.kind === 'image' && !m.alt" class="absolute bottom-1 left-1 rounded bg-amber-500 px-1 text-[10px] font-semibold text-white">tanpa alt</span>
                    </button>
                </div>
                <div v-if="next" class="p-3 text-center"><button class="btn btn-sm" :disabled="loading" @click="load(false)">Muat lebih banyak</button></div>
                <div v-if="loading" class="p-3 text-center text-xs text-stone-500">Memuat…</div>
            </div>

            <div class="card min-h-[160px] p-3 text-sm">
                <div v-if="!active" class="py-6 text-center text-stone-500">Pilih file untuk melihat detailnya.</div>
                <template v-else>
                    <img v-if="active.kind === 'image'" :src="active.thumb" :alt="active.alt" class="mb-2 max-h-40 w-full rounded-lg object-contain bg-stone-100">
                    <div class="break-all font-medium">{{ active.name }}</div>
                    <a :href="active.url" target="_blank" class="break-all text-xs text-brand underline">{{ active.url }}</a>
                    <label class="label mt-3">Teks alternatif (alt)</label>
                    <input v-model="detail.alt" class="input" maxlength="255">
                    <p v-if="detail.error" class="err">{{ detail.error }}</p>
                    <div class="mt-2 flex gap-2">
                        <button class="btn btn-sm btn-primary" :disabled="detail.saving || !detail.alt.trim()" @click="saveAlt">Simpan alt</button>
                        <button v-if="!select" class="btn btn-sm text-red-600" @click="remove"><Icon name="trash" class="h-4 w-4" /> Hapus</button>
                    </div>
                    <div v-if="!select" class="mt-3 text-xs">
                        <div class="font-semibold text-stone-600">Dipakai di:</div>
                        <div v-if="detail.usage === null" class="text-stone-400">memeriksa…</div>
                        <div v-else-if="!detail.usage.length" class="text-amber-700">Belum dipakai di mana pun.</div>
                        <ul v-else class="list-disc pl-4 text-stone-600"><li v-for="u in detail.usage" :key="u">{{ u }}</li></ul>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
