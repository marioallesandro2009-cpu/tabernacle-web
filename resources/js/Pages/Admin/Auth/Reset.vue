<script setup>
import { au } from '../../../lib/admin';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ token: String, email: String, min: { type: Number, default: 12 } });
const form = useForm({ token: props.token, email: props.email || '', password: '', password_confirmation: '', end_everything: false });
const submit = () => form.post(au('/admin/reset-password'), { onFinish: () => form.reset('password', 'password_confirmation') });
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
            <label class="label" for="pw">Password baru (minimal {{ min }} karakter)</label>
            <input id="pw" v-model="form.password" type="password" class="input" autocomplete="new-password" required>
            <p v-if="form.errors.password" class="err">{{ form.errors.password }}</p>
        </div>
        <div>
            <label class="label" for="pw2">Ulangi password</label>
            <input id="pw2" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required>
        </div>
        <label class="flex items-start gap-2 text-sm"><input v-model="form.end_everything" type="checkbox" class="mt-0.5"> <span>Cabut semua sesi dan perangkat tepercaya (pilih ini kalau Anda curiga akun dipakai orang lain)</span></label>
        <p class="text-xs text-stone-500">Mengganti password lewat email juga membuka semua blokir login untuk akun ini.</p>
        <button class="btn btn-primary w-full" :disabled="form.processing">Simpan password</button>
    </form>
</template>
