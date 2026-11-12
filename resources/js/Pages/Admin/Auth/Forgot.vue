<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '' });
const submit = () => form.post('/admin/forgot-password');
</script>

<template>
    <Head title="Lupa password" />
    <form class="space-y-4" @submit.prevent="submit">
        <p class="text-sm text-stone-600">Masukkan email akun admin Anda. Kami akan mengirim tautan untuk mengatur ulang password.</p>
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" v-model="form.email" type="email" class="input" required autofocus>
            <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>
        <button class="btn btn-primary w-full" :disabled="form.processing">Kirim tautan</button>
        <div class="text-center text-sm"><Link href="/admin/login" class="text-brand hover:underline">Kembali ke halaman masuk</Link></div>
    </form>
</template>
