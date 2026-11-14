<script setup>
import { au } from '../../../lib/admin';
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';

defineProps({ pages: Array });

const adding = ref(false);
const form = useForm({ title: { id: '', en: '' }, slug: '' });
const slugify = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const create = () => form.post(au('/admin/pages'), { preserveScroll: true });
const remove = (p) => confirm(`Hapus halaman "${p.title}" beserta isinya? Tidak bisa dibatalkan.`) && router.delete(au(`/admin/pages/${p.id}`), { preserveScroll: true });
</script>

<template>
    <Head title="Halaman & section" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Halaman & section</h1>
            <p class="text-sm text-stone-600">Setiap halaman tersusun dari section. Ubah isi, urutan, atau sembunyikan section tanpa menyentuh kode.</p>
        </div>
        <button class="btn btn-primary" @click="adding = !adding"><Icon name="plus" class="h-4 w-4" /> Halaman baru</button>
    </div>

    <form v-if="adding" class="card mb-5 grid gap-3 p-5 md:grid-cols-3" @submit.prevent="create">
        <div>
            <label class="label" for="np-id">Judul (Indonesia)</label>
            <input id="np-id" v-model="form.title.id" class="input" placeholder="Syarat & Ketentuan">
        </div>
        <div>
            <label class="label" for="np-en">Judul (English) <span class="text-red-500">*</span></label>
            <input id="np-en" v-model="form.title.en" class="input" placeholder="Terms & Conditions" required>
            <p v-if="form.errors['title.en']" class="err">{{ form.errors['title.en'] }}</p>
        </div>
        <div>
            <label class="label" for="np-slug">Alamat</label>
            <div class="flex items-center gap-1"><span class="text-sm text-stone-500">/</span><input id="np-slug" v-model="form.slug" class="input font-mono" :placeholder="slugify(form.title.id || form.title.en) || 'syarat-ketentuan'"></div>
            <p class="hint">Kosongkan untuk otomatis dari judul.</p>
            <p v-if="form.errors.slug" class="err">{{ form.errors.slug }}</p>
        </div>
        <div class="flex gap-2 md:col-span-3">
            <button class="btn btn-primary" :disabled="form.processing">Buat halaman</button>
            <button type="button" class="btn" @click="adding = false">Batal</button>
            <p class="hint self-center">Halaman baru belum aktif sampai Anda mengaktifkannya, jadi aman untuk diisi dulu.</p>
        </div>
    </form>

    <div class="card divide-y divide-stone-100">
        <div v-for="p in pages" :key="p.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <Link :href="au(`/admin/pages/${p.id}/edit`)" class="font-medium text-brand hover:underline">{{ p.title }}</Link>
                <div class="text-xs text-stone-500">{{ p.sections }} section · {{ p.url }}</div>
            </div>
            <span v-if="p.is_system" class="badge">Bawaan</span>
            <span v-if="!p.is_active" class="badge bg-amber-100 text-amber-800">Nonaktif</span>
            <a v-if="p.is_active" :href="p.url" target="_blank" class="btn btn-ghost btn-sm" aria-label="Lihat"><Icon name="external" class="h-4 w-4" /></a>
            <a v-else-if="!p.is_system" :href="au(`/admin/pages/${p.id}/preview`)" target="_blank" class="btn btn-ghost btn-sm" aria-label="Pratinjau"><Icon name="eye" class="h-4 w-4" /></a>
            <Link :href="au(`/admin/pages/${p.id}/edit`)" class="btn btn-sm"><Icon name="edit" class="h-4 w-4" /> Ubah</Link>
            <button v-if="!p.is_system" class="btn btn-sm text-red-600" aria-label="Hapus" @click="remove(p)"><Icon name="trash" class="h-4 w-4" /></button>
        </div>
    </div>
</template>
