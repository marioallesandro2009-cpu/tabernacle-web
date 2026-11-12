<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';

const props = defineProps({ recipients: Array, max: Number, enabled: Boolean, mailer: String });

const form = useForm({ email: '', name: '' });
const left = computed(() => props.max - props.recipients.length);
const add = () => form.post('/admin/recipients', { preserveScroll: true, onSuccess: () => form.reset() });
const patch = (r, data) => router.put(`/admin/recipients/${r.id}`, { email: r.email, name: r.name, is_active: r.is_active, ...data }, { preserveScroll: true });
const test = (r) => router.post(`/admin/recipients/${r.id}/test`, {}, { preserveScroll: true });
const remove = (r) => confirm(`Hapus ${r.email} dari daftar penerima?`) && router.delete(`/admin/recipients/${r.id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Penerima notifikasi" />
    <h1 class="mb-1 text-2xl font-bold">Penerima notifikasi lead</h1>
    <p class="mb-5 text-sm text-stone-600">Setiap ada lead baru (form paket atau kontak), email pemberitahuan dikirim ke semua alamat aktif di bawah. Maksimal {{ max }} alamat.</p>

    <div v-if="!enabled" class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Notifikasi email sedang dimatikan di Pengaturan situs › SEO & fitur.</div>
    <div v-if="mailer === 'log'" class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">SMTP belum diatur (MAIL_MAILER=log): email hanya dicatat di log, belum benar-benar terkirim. Isi pengaturan SMTP di file <code>.env</code> server.</div>

    <div class="card mb-5">
        <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
            <h2 class="font-semibold">Daftar penerima</h2>
            <span class="badge" :class="left === 0 ? 'bg-amber-100 text-amber-800' : ''">{{ recipients.length }} / {{ max }}</span>
        </div>
        <ul class="divide-y divide-stone-100">
            <li v-for="r in recipients" :key="r.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
                <button type="button" role="switch" :aria-checked="r.is_active" :aria-label="'Aktif ' + r.email" class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition" :class="r.is_active ? 'bg-brand' : 'bg-stone-300'" @click="patch(r, { is_active: !r.is_active })">
                    <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition" :class="r.is_active ? 'translate-x-4' : 'translate-x-0.5'" />
                </button>
                <div class="min-w-0 flex-1" :class="r.is_active ? '' : 'opacity-50'">
                    <div class="truncate font-medium">{{ r.email }}</div>
                    <div v-if="r.name" class="text-xs text-stone-500">{{ r.name }}</div>
                </div>
                <button class="btn btn-sm" @click="test(r)"><Icon name="mail" class="h-4 w-4" /> Kirim tes</button>
                <button class="btn btn-sm text-red-600" aria-label="Hapus" @click="remove(r)"><Icon name="trash" class="h-4 w-4" /></button>
            </li>
            <li v-if="!recipients.length" class="px-4 py-8 text-center text-sm text-stone-500">Belum ada penerima. Tanpa penerima, tidak ada email yang dikirim (lead tetap tersimpan di admin).</li>
        </ul>
    </div>

    <form class="card p-5" @submit.prevent="add">
        <h2 class="mb-3 font-semibold">Tambah penerima</h2>
        <p v-if="left <= 0" class="text-sm text-amber-700">Daftar penuh ({{ max }} email). Hapus salah satu untuk menambah yang baru.</p>
        <div v-else class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
            <div><label class="label" for="re">Email</label><input id="re" v-model="form.email" type="email" class="input" placeholder="sales@perusahaan.com" required><p v-if="form.errors.email" class="err">{{ form.errors.email }}</p></div>
            <div><label class="label" for="rn">Nama (opsional)</label><input id="rn" v-model="form.name" class="input" placeholder="Tim sales"></div>
            <div class="self-end"><button class="btn btn-primary w-full" :disabled="form.processing"><Icon name="plus" class="h-4 w-4" /> Tambah</button></div>
        </div>
    </form>
</template>
