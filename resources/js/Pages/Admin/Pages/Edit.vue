<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Sortable from 'sortablejs';
import FormRenderer from '../../../Components/FormRenderer.vue';
import Icon from '../../../Components/Icon.vue';
import { rememberAll } from '../../../lib/media';

const props = defineProps({ page: Object, fields: Array, values: Object, media: Object, sections: Array, types: Array });
rememberAll(props.media);

const form = useForm(JSON.parse(JSON.stringify(props.values)));
const save = () => form.put(`/admin/pages/${props.page.id}`, { preserveScroll: true });

const list = ref(null);
const items = ref([]);
const newType = ref('');
watch(() => props.sections, (s) => (items.value = [...s]), { immediate: true });

onMounted(() =>
    nextTick(() =>
        list.value && Sortable.create(list.value, {
            handle: '.drag', animation: 150,
            onEnd: async (e) => {
                const m = items.value.splice(e.oldIndex, 1)[0];
                items.value.splice(e.newIndex, 0, m);
                await axios.post(`/admin/pages/${props.page.id}/sections/reorder`, { ids: items.value.map((x) => x.id) });
            },
        }),
    ),
);

const add = () => newType.value && router.post(`/admin/pages/${props.page.id}/sections`, { type: newType.value });
const toggle = (s) => router.post(`/admin/sections/${s.id}/toggle`, {}, { preserveScroll: true });
const removePage = () => confirm('Hapus halaman ini beserta semua section-nya? Tidak bisa dibatalkan.') && router.delete(`/admin/pages/${props.page.id}`);
const remove = (s) => confirm('Hapus section ini beserta isinya?') && router.delete(`/admin/sections/${s.id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Ubah halaman" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <Link href="/admin/pages" class="text-sm text-stone-500 hover:text-brand">← Halaman</Link>
            <h1 class="text-2xl font-bold">Halaman: {{ page.title }}</h1>
            <p v-if="!page.is_active" class="text-sm text-amber-700">Belum aktif: pengunjung belum bisa membuka halaman ini.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a v-if="page.preview_url" :href="page.preview_url" target="_blank" class="btn"><Icon name="eye" class="h-4 w-4" /> Pratinjau</a>
            <a v-if="page.is_active" :href="page.url" target="_blank" class="btn"><Icon name="external" class="h-4 w-4" /> Lihat di situs</a>
            <button v-if="!page.is_system" type="button" class="btn text-red-600" @click="removePage">Hapus halaman</button>
        </div>
    </div>

    <div class="card mb-6">
        <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
            <h2 class="font-semibold">Section di halaman ini</h2>
        </div>
        <div ref="list" class="divide-y divide-stone-100">
            <div v-for="s in items" :key="s.id" class="flex items-center gap-2 px-3 py-2.5" :class="s.is_visible ? '' : 'bg-stone-50 opacity-70'">
                <span class="drag cursor-grab p-1 text-stone-400"><Icon name="drag" class="h-4 w-4" /></span>
                <div class="min-w-0 flex-1">
                    <Link :href="`/admin/sections/${s.id}/edit`" class="block truncate font-medium text-brand hover:underline">{{ s.name || s.label }}</Link>
                    <div class="text-xs text-stone-500">{{ s.label }}</div>
                </div>
                <button class="btn btn-sm" @click="toggle(s)">{{ s.is_visible ? 'Sembunyikan' : 'Tampilkan' }}</button>
                <Link :href="`/admin/sections/${s.id}/edit`" class="btn btn-sm"><Icon name="edit" class="h-4 w-4" /></Link>
                <button class="btn btn-sm text-red-600" aria-label="Hapus" @click="remove(s)"><Icon name="trash" class="h-4 w-4" /></button>
            </div>
            <div v-if="!items.length" class="p-6 text-center text-sm text-stone-500">Belum ada section.</div>
        </div>
        <div class="flex flex-wrap items-center gap-2 border-t border-stone-200 p-3">
            <select v-model="newType" class="input max-w-xs"><option value="">Tambah section baru…</option><option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option></select>
            <button class="btn btn-primary" :disabled="!newType" @click="add"><Icon name="plus" class="h-4 w-4" /> Tambah</button>
        </div>
    </div>

    <form class="card p-5" @submit.prevent="save">
        <h2 class="mb-4 font-semibold">Judul & SEO halaman</h2>
        <FormRenderer :fields="fields" :values="form" :errors="form.errors" cols />
        <div class="mt-6 flex justify-end border-t border-stone-100 pt-4"><button class="btn btn-primary" :disabled="form.processing">Simpan halaman</button></div>
    </form>
</template>
