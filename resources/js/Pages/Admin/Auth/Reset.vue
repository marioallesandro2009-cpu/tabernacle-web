<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ token: String, email: String });
const form = useForm({ token: props.token, email: props.email || '', password: '', password_confirmation: '' });
const submit = () => form.post('/admin/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Atur ulang password" />
    <form class="space-y-4" @submit.prevent="submit">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" v-model="form.email" type="email" class="input" required>
            <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>
        <div>
            <label class="label" for="pw">Password baru (minimal 10 karakter)</label>
            <input id="pw" v-model="form.password" type="password" class="input" autocomplete="new-password" required>
            <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
        </div>
        <div>
            <label class="label" for="pw2">Ulangi password</label>
            <input id="pw2" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary w-full" :disabled="form.processing">Simpan password</button>
    </form>
</template>
