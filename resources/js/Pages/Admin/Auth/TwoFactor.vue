<script setup>
import { au } from '../../../lib/admin';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ code: '', trust: false });
const submit = () => form.post(au('/admin/two-factor'));
</script>

<template>
    <Head title="Verifikasi dua langkah" />
    <form class="space-y-4" @submit.prevent="submit">
        <p class="text-sm text-stone-600">Buka aplikasi autentikator di ponsel Anda, lalu masukkan kode 6 angka.</p>
        <div>
            <label class="label" for="code">Kode verifikasi</label>
            <input id="code" v-model="form.code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" class="input text-center text-lg tracking-widest" required autofocus>
            <p v-if="form.errors.code" class="err">{{ form.errors.code }}</p>
        </div>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.trust" type="checkbox"> Ingat perangkat ini 30 hari (tidak perlu kode di perangkat ini)</label>
        <button class="btn btn-primary w-full" :disabled="form.processing">Verifikasi</button>
        <div class="text-center text-sm"><Link :href="au('/admin/login')" class="text-brand hover:underline">Kembali ke halaman masuk</Link></div>
    </form>
</template>
