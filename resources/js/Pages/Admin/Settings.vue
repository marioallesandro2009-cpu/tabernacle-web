<script setup>
import { au } from '../../lib/admin';
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import FormRenderer from '../../Components/FormRenderer.vue';
import { rememberAll } from '../../lib/media';

const props = defineProps({ groups: Array, values: Object, media: Object });
rememberAll(props.media);

const form = useForm(JSON.parse(JSON.stringify(props.values)));
const tab = ref(props.groups[0].key);
const save = () => form.put(au('/admin/settings'), { preserveScroll: true });
const hasError = (g) => g.fields.some((f) => Object.keys(form.errors).some((k) => k === f.key || k.startsWith(f.key + '.')));
</script>

<template>
    <Head title="Pengaturan situs" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Pengaturan situs</h1>
        <button class="btn btn-primary" :disabled="form.processing" @click="save">{{ form.processing ? 'Menyimpan…' : 'Simpan semua' }}</button>
    </div>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-stone-200">
        <button
            v-for="g in groups" :key="g.key" type="button"
            class="-mb-px rounded-t-lg border border-b-0 px-4 py-2 text-sm font-medium"
            :class="tab === g.key ? 'border-stone-200 bg-white text-brand' : 'border-transparent text-stone-600 hover:text-brand'"
            @click="tab = g.key"
        >{{ g.label }}<span v-if="hasError(g)" class="ml-1 text-red-500">●</span></button>
    </div>

    <form class="card p-5" @submit.prevent="save">
        <div v-for="g in groups" v-show="tab === g.key" :key="g.key">
            <FormRenderer :fields="g.fields" :values="form" :errors="form.errors" cols />
        </div>
        <div class="mt-6 flex justify-end border-t border-stone-100 pt-4"><button class="btn btn-primary" :disabled="form.processing">Simpan semua</button></div>
    </form>
</template>
