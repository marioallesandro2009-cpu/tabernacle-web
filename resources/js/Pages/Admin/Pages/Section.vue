<script setup>
import { au } from '../../../lib/admin';
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormRenderer from '../../../Components/FormRenderer.vue';
import Icon from '../../../Components/Icon.vue';
import { rememberAll } from '../../../lib/media';

const props = defineProps({ section: Object, page: Object, fields: Array, values: Object, media: Object });
rememberAll(props.media);

const form = useForm({ ...JSON.parse(JSON.stringify(props.values)), name: props.section.name || '', is_visible: props.section.is_visible });
const save = () => form.put(au(`/admin/sections/${props.section.id}`), { preserveScroll: true });
</script>

<template>
    <Head :title="section.label" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <Link :href="au(`/admin/pages/${page.id}/edit`)" class="text-sm text-stone-500 hover:text-brand">← Halaman {{ page.key }}</Link>
            <h1 class="text-2xl font-bold">{{ section.label }}</h1>
        </div>
        <div class="flex gap-2">
            <a :href="page.url" target="_blank" class="btn"><Icon name="external" class="h-4 w-4" /> Lihat di situs</a>
            <button class="btn btn-primary" :disabled="form.processing" @click="save">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
    </div>

    <form class="card space-y-5 p-5" @submit.prevent="save">
        <div v-if="Object.keys(form.errors).length" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Ada isian yang perlu diperbaiki, lihat pesan merah di bawah.</div>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="label">Nama section (hanya terlihat di admin)</label><input v-model="form.name" class="input" maxlength="120"></div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium"><input v-model="form.is_visible" type="checkbox"> Tampil di situs</label>
        </div>
        <p v-if="!fields.length" class="rounded-lg bg-stone-50 p-4 text-sm text-stone-600">Section ini otomatis dari modul lain (paket, kredit foto, dll), jadi tidak ada isian di sini.</p>
        <FormRenderer :fields="fields" :values="form" :errors="form.errors" />
        <div class="flex justify-end border-t border-stone-100 pt-4"><button class="btn btn-primary" :disabled="form.processing">Simpan</button></div>
    </form>
</template>
