<script setup>
import { ref } from 'vue';
import { au } from '../../../lib/admin';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Turnstile from '../../../Components/Turnstile.vue';

defineProps({ turnstile: { type: String, default: null } });
const page = usePage();
const ts = ref(null);
const form = useForm({ email: '', password: '', remember: false, 'cf-turnstile-response': '' });
const submit = () => form.post(au('/admin/login'), { onFinish: () => { form.reset('password'); ts.value?.reset(); } });
</script>

<template>
    <Head title="Masuk" />
    <form class="space-y-4" @submit.prevent="submit">
        <p v-if="page.props.flash?.success" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ page.props.flash.success }}</p>
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
        <Turnstile v-if="turnstile" ref="ts" :site-key="turnstile" @token="(t) => (form['cf-turnstile-response'] = t)" />
        <button class="btn btn-primary w-full" :disabled="form.processing || (turnstile && !form['cf-turnstile-response'])">{{ form.processing ? 'Memeriksa…' : 'Masuk' }}</button>
        <div class="text-center text-sm"><Link :href="au('/admin/forgot-password')" class="text-brand hover:underline">Lupa password?</Link></div>
    </form>
</template>
