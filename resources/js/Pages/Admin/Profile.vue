<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import MediaPicker from '../../Components/MediaPicker.vue';

const props = defineProps({ profile: Object, twoFactor: Object });

const info = useForm({ name: props.profile.name, email: props.profile.email, avatar_id: props.profile.avatar_id });
const pw = useForm({ current_password: '', password: '', password_confirmation: '' });
const code = useForm({ code: '' });
const off = useForm({ password: '' });

const saveInfo = () => info.put('/admin/profile', { preserveScroll: true });
const savePw = () => pw.put('/admin/profile/password', { preserveScroll: true, onSuccess: () => pw.reset() });
const start = () => router.post('/admin/profile/2fa', {}, { preserveScroll: true });
const confirm2fa = () => code.post('/admin/profile/2fa/confirm', { preserveScroll: true, onSuccess: () => code.reset() });
const disable2fa = () => off.delete('/admin/profile/2fa', { preserveScroll: true, onSuccess: () => off.reset() });
</script>

<template>
    <Head title="Profil & keamanan" />
    <h1 class="mb-5 text-2xl font-bold">Profil & keamanan</h1>

    <div class="grid max-w-5xl gap-5 lg:grid-cols-2">
        <form class="card space-y-4 p-5" @submit.prevent="saveInfo">
            <h2 class="font-semibold">Profil</h2>
            <div><label class="label">Foto</label><MediaPicker v-model="info.avatar_id" kind="image" /></div>
            <div><label class="label" for="pn">Nama</label><input id="pn" v-model="info.name" class="input" required><p v-if="info.errors.name" class="err">{{ info.errors.name }}</p></div>
            <div><label class="label" for="pe">Email</label><input id="pe" v-model="info.email" type="email" class="input" required><p v-if="info.errors.email" class="err">{{ info.errors.email }}</p></div>
            <button class="btn btn-primary" :disabled="info.processing">Simpan profil</button>
        </form>

        <form class="card space-y-4 p-5" @submit.prevent="savePw">
            <h2 class="font-semibold">Ganti password</h2>
            <div><label class="label" for="cp">Password saat ini</label><input id="cp" v-model="pw.current_password" type="password" class="input" autocomplete="current-password" required><p v-if="pw.errors.current_password" class="err">{{ pw.errors.current_password }}</p></div>
            <div><label class="label" for="np">Password baru (minimal 10 karakter)</label><input id="np" v-model="pw.password" type="password" class="input" autocomplete="new-password" required><p v-if="pw.errors.password" class="err">{{ pw.errors.password }}</p></div>
            <div><label class="label" for="np2">Ulangi password baru</label><input id="np2" v-model="pw.password_confirmation" type="password" class="input" autocomplete="new-password" required></div>
            <button class="btn btn-primary" :disabled="pw.processing">Ganti password</button>
        </form>

        <div class="card space-y-4 p-5 lg:col-span-2">
            <h2 class="font-semibold">Verifikasi dua langkah (2FA)</h2>
            <template v-if="twoFactor.enabled">
                <p class="text-sm text-emerald-700">Aktif. Setiap login meminta kode dari aplikasi autentikator.</p>
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="disable2fa">
                    <div><label class="label" for="op">Password untuk mematikan</label><input id="op" v-model="off.password" type="password" class="input" required><p v-if="off.errors.password" class="err">{{ off.errors.password }}</p></div>
                    <button class="btn text-red-600" :disabled="off.processing">Matikan 2FA</button>
                </form>
            </template>
            <template v-else-if="twoFactor.setup">
                <p class="text-sm text-stone-600">Pindai kode QR dengan Google Authenticator, Authy, atau sejenisnya, lalu masukkan kode 6 angka untuk mengonfirmasi.</p>
                <div class="flex flex-wrap items-start gap-5">
                    <div class="rounded-lg border border-stone-200 bg-white p-2" v-html="twoFactor.setup.qr" />
                    <div class="space-y-2 text-sm">
                        <div>Atau ketik kunci ini manual:<br><code class="break-all rounded bg-stone-100 px-2 py-1">{{ twoFactor.setup.secret }}</code></div>
                        <form class="flex items-end gap-2" @submit.prevent="confirm2fa">
                            <div><label class="label" for="c2">Kode 6 angka</label><input id="c2" v-model="code.code" inputmode="numeric" maxlength="8" class="input" required><p v-if="code.errors.code" class="err">{{ code.errors.code }}</p></div>
                            <button class="btn btn-primary" :disabled="code.processing">Aktifkan</button>
                        </form>
                    </div>
                </div>
            </template>
            <template v-else>
                <p class="text-sm text-stone-600">Belum aktif. Sangat disarankan untuk akun yang mengelola data lead.</p>
                <button class="btn btn-primary" @click="start">Siapkan 2FA</button>
            </template>
        </div>
    </div>
</template>
