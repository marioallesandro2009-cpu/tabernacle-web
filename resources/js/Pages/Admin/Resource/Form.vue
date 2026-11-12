<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FormRenderer from '../../../Components/FormRenderer.vue';
import { rememberAll } from '../../../lib/media';

const props = defineProps({ resource: Object, fields: Array, values: Object, id: [Number, String], media: Object, extras: Object });
rememberAll(props.media);

const form = useForm(JSON.parse(JSON.stringify(props.values)));
const isEdit = computed(() => !!props.id);

function save() {
    const opts = { preserveScroll: true };
    isEdit.value ? form.put(`${props.resource.url}/${props.id}`, opts) : form.post(props.resource.url, opts);
}
const remove = () => confirm('Hapus data ini? Tindakan ini tidak bisa dibatalkan.') && router.delete(`${props.resource.url}/${props.id}`);
</script>

<template>
    <Head :title="(isEdit ? 'Ubah ' : 'Tambah ') + resource.singular" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <Link :href="resource.url" class="text-sm text-stone-500 hover:text-brand">← {{ resource.title }}</Link>
            <h1 class="text-2xl font-bold">{{ isEdit ? 'Ubah' : 'Tambah' }} {{ resource.singular.toLowerCase() }}</h1>
        </div>
        <div class="flex gap-2">
            <button v-if="isEdit" type="button" class="btn text-red-600" @click="remove">Hapus</button>
            <button type="button" class="btn btn-primary" :disabled="form.processing" @click="save">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
    </div>

    <form class="card p-5" @submit.prevent="save">
        <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Ada isian yang perlu diperbaiki, lihat pesan merah di bawah.</div>
        <FormRenderer :fields="fields" :values="form" :errors="form.errors" cols />
        <div class="mt-6 flex justify-end gap-2 border-t border-stone-100 pt-4">
            <Link :href="resource.url" class="btn">Batal</Link>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Simpan</button>
        </div>
    </form>
</template>
