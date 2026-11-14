<script setup>
import { au } from '../../../lib/admin';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(au('/admin/login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Masuk" />
    <form class="space-y-4" @submit.prevent="submit">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" v-model="form.email" type="email" class="input" autocomplete="username" required autofocus>
            <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input id="password" v-model="form.password" type="password" class="input" autocomplete="current-password" required>
            <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
        </div>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.remember" type="checkbox"> Ingat saya di perangkat ini</label>
        <button class="btn btn-primary w-full" :disabled="form.processing">{{ form.processing ? 'Memeriksa…' : 'Masuk' }}</button>
        <div class="text-center text-sm"><Link :href="au('/admin/forgot-password')" class="text-brand hover:underline">Lupa password?</Link></div>
    </form>
</template>
