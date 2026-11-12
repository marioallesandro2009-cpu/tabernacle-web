<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Sortable from 'sortablejs';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({ resource: Object, columns: Array, filters: Array, rows: [Array, Object], query: Object });

const search = ref(props.query?.q || '');
const filterVals = reactive(Object.fromEntries((props.filters || []).map((f) => [f.key, props.query?.[f.key] || ''])));
const list = ref(null);
const items = ref([]);
const paginated = computed(() => !Array.isArray(props.rows));

watch(() => props.rows, (r) => (items.value = [...(Array.isArray(r) ? r : r.data)]), { immediate: true });

let timer;
function go() {
    router.get(props.resource.url, { q: search.value || undefined, ...Object.fromEntries(Object.entries(filterVals).filter(([, v]) => v !== '')) }, { preserveState: true, preserveScroll: true, replace: true });
}
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(go, 350);
});
watch(filterVals, go);

onMounted(() => {
    if (!props.resource.sortable) return;
    nextTick(() => {
        if (!list.value) return;
        Sortable.create(list.value, {
            handle: '.drag', animation: 150, ghostClass: 'sortable-ghost',
            onEnd: async (e) => {
                const moved = items.value.splice(e.oldIndex, 1)[0];
                items.value.splice(e.newIndex, 0, moved);
                await axios.post(`${props.resource.url}/reorder`, { ids: items.value.map((r) => r.id) });
            },
        });
    });
});

const remove = (r) => confirm('Hapus data ini? Tindakan ini tidak bisa dibatalkan.') && router.delete(`${props.resource.url}/${r.id}`, { preserveScroll: true });
const toggle = (r) => router.post(`${props.resource.url}/${r.id}/toggle`, {}, { preserveScroll: true });
// warning colours for package expiry ("Kedaluwarsa ...", "Belum diisi")
const badgeTone = (v) => (typeof v !== 'string' ? '' : v.startsWith('Kedaluwarsa') ? 'bg-red-100 text-red-800' : v === 'Belum diisi' ? 'bg-amber-100 text-amber-800' : '');
const scoreClass =(s) => (s >= 80 ? 'bg-emerald-100 text-emerald-800' : s >= 50 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800');
</script>

<template>
    <Head :title="resource.title" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">{{ resource.title }}</h1>
        <Link :href="`${resource.url}/create`" class="btn btn-primary"><Icon name="plus" class="h-4 w-4" /> Tambah {{ resource.singular.toLowerCase() }}</Link>
    </div>

    <div class="card">
        <div class="flex flex-wrap items-center gap-2 border-b border-stone-200 p-3">
            <div class="relative min-w-[200px] flex-1">
                <Icon name="search" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-stone-400" />
                <input v-model="search" class="input pl-8" placeholder="Cari…" aria-label="Cari">
            </div>
            <select v-for="f in filters" :key="f.key" v-model="filterVals[f.key]" class="input w-auto" :aria-label="f.label">
                <option value="">{{ f.label }}: semua</option>
                <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px]">
                <thead class="border-b border-stone-200 bg-stone-50">
                    <tr>
                        <th v-if="resource.sortable" class="th w-8" />
                        <th v-for="c in columns" :key="c.key" class="th">{{ c.label }}</th>
                        <th v-if="resource.hasActive" class="th w-20">Aktif</th>
                        <th class="th w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody ref="list" class="divide-y divide-stone-100">
                    <tr v-for="r in items" :key="r.id" class="hover:bg-stone-50">
                        <td v-if="resource.sortable" class="td drag cursor-grab text-stone-400"><Icon name="drag" class="h-4 w-4" /></td>
                        <td v-for="c in columns" :key="c.key" class="td">
                            <img v-if="c.type === 'image'" :src="r[c.key]" alt="" class="h-10 w-14 rounded object-cover bg-stone-100" :class="r[c.key] ? '' : 'invisible'">
                            <span v-else-if="c.type === 'badge'" class="badge" :class="badgeTone(r[c.key])">{{ r[c.key] || '–' }}</span>
                            <span v-else-if="c.type === 'bool'" :class="r[c.key] ? 'text-emerald-600' : 'text-stone-400'">{{ r[c.key] ? '✓' : '–' }}</span>
                            <span v-else-if="c.type === 'score'" class="badge" :class="scoreClass(r[c.key])">{{ r[c.key] }}</span>
                            <Link v-else-if="c === columns.find((x) => !['image', 'badge', 'bool'].includes(x.type))" :href="`${resource.url}/${r.id}/edit`" class="font-medium text-brand hover:underline">{{ r[c.key] || '(tanpa judul)' }}</Link>
                            <span v-else class="line-clamp-2">{{ r[c.key] }}</span>
                        </td>
                        <td v-if="resource.hasActive" class="td">
                            <button type="button" role="switch" :aria-checked="r.is_active" class="relative inline-flex h-5 w-9 items-center rounded-full transition" :class="r.is_active ? 'bg-brand' : 'bg-stone-300'" @click="toggle(r)">
                                <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition" :class="r.is_active ? 'translate-x-4' : 'translate-x-0.5'" />
                            </button>
                        </td>
                        <td class="td text-right">
                            <Link :href="`${resource.url}/${r.id}/edit`" class="btn btn-ghost btn-sm" aria-label="Ubah"><Icon name="edit" class="h-4 w-4" /></Link>
                            <button class="btn btn-ghost btn-sm text-red-600" aria-label="Hapus" @click="remove(r)"><Icon name="trash" class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!items.length"><td :colspan="columns.length + 3" class="td py-10 text-center text-stone-500">Belum ada data.</td></tr>
                </tbody>
            </table>
        </div>
        <Pagination v-if="paginated" :links="rows.links" />
    </div>
    <p v-if="resource.sortable" class="hint mt-2">Seret ikon titik untuk mengubah urutan. Urutan langsung tersimpan.</p>
</template>
