<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({ lead: Object, events: Array, statuses: Array, staff: Array });

const form = useForm({ status: props.lead.status, assigned_to: props.lead.assigned_to, notes: props.lead.notes || '' });
const noteForm = useForm({ note: '' });
const save = () => form.put(`/admin/leads/${props.lead.id}`, { preserveScroll: true });
const addNote = () => noteForm.post(`/admin/leads/${props.lead.id}/note`, { preserveScroll: true, onSuccess: () => noteForm.reset() });
const remove = () => confirm('Hapus lead ini?') && router.delete(`/admin/leads/${props.lead.id}`);
const label = (s) => props.statuses.find((x) => x.value === s)?.label || s;
const eventText = (e) => ({
    status: `Status: ${label(e.from)} → ${label(e.to)}`,
    assign: `Ditangani: ${e.from || '–'} → ${e.to || '–'}`,
    note: e.note,
    created: 'Lead masuk',
})[e.event] || e.event;
const rows = [
    ['Telepon', props.lead.phone], ['Email', props.lead.email], ['Paket', props.lead.tour_name], ['Jumlah orang', props.lead.pax],
    ['Rencana berangkat', props.lead.plan_date], ['Masuk', props.lead.created_at], ['Sumber', props.lead.source],
    ['UTM', [props.lead.utm_source, props.lead.utm_medium, props.lead.utm_campaign].filter(Boolean).join(' / ')], ['Halaman', props.lead.page_url], ['Referrer', props.lead.referrer],
];
</script>

<template>
    <Head :title="'Lead: ' + (lead.name || 'WhatsApp')" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <Link href="/admin/leads" class="text-sm text-stone-500 hover:text-brand">← Leads</Link>
            <h1 class="text-2xl font-bold">{{ lead.name || 'Klik WhatsApp' }}</h1>
        </div>
        <div class="flex gap-2">
            <a v-if="lead.wa_url" :href="lead.wa_url" target="_blank" rel="noopener" class="btn btn-primary"><Icon name="phone" class="h-4 w-4" /> Chat WhatsApp</a>
            <button class="btn text-red-600" @click="remove">Hapus</button>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_340px]">
        <div class="space-y-5">
            <div class="card p-5">
                <h2 class="mb-3 font-semibold">Detail</h2>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <template v-for="r in rows" :key="r[0]">
                        <div v-if="r[1]"><dt class="text-xs text-stone-500">{{ r[0] }}</dt><dd class="break-words font-medium">{{ r[1] }}</dd></div>
                    </template>
                </dl>
                <div v-if="lead.tour" class="mt-3 text-sm">Paket di situs: <a :href="lead.tour.url" target="_blank" class="text-brand underline">{{ lead.tour.name }}</a></div>
                <div v-if="lead.message" class="mt-4 rounded-lg bg-stone-50 p-3 text-sm"><div class="mb-1 text-xs text-stone-500">Pesan</div><p class="whitespace-pre-line">{{ lead.message }}</p></div>
            </div>

            <div class="card p-5">
                <h2 class="mb-3 font-semibold">Riwayat</h2>
                <form class="mb-4 flex gap-2" @submit.prevent="addNote">
                    <input v-model="noteForm.note" class="input" placeholder="Tambah catatan (mis. sudah ditelepon, minta penawaran)…" maxlength="3000">
                    <button class="btn btn-primary" :disabled="!noteForm.note.trim() || noteForm.processing">Tambah</button>
                </form>
                <ol class="space-y-3 border-l-2 border-stone-200 pl-4">
                    <li v-for="e in events" :key="e.id" class="relative text-sm">
                        <span class="absolute -left-[22px] top-1.5 h-2.5 w-2.5 rounded-full bg-brand" />
                        <div class="whitespace-pre-line">{{ eventText(e) }}</div>
                        <div class="text-xs text-stone-500">{{ e.user }} · {{ e.at }}</div>
                    </li>
                    <li v-if="!events.length" class="text-sm text-stone-500">Belum ada aktivitas.</li>
                </ol>
            </div>
        </div>

        <form class="card h-fit space-y-4 p-5" @submit.prevent="save">
            <h2 class="font-semibold">Penanganan</h2>
            <div>
                <label class="label" for="st">Status</label>
                <select id="st" v-model="form.status" class="input"><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select>
            </div>
            <div>
                <label class="label" for="as">Ditangani oleh</label>
                <select id="as" v-model="form.assigned_to" class="input"><option :value="null">— belum ada —</option><option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option></select>
            </div>
            <div><label class="label" for="nt">Catatan internal</label><textarea id="nt" v-model="form.notes" rows="5" class="input" /></div>
            <button class="btn btn-primary w-full" :disabled="form.processing">Simpan</button>
        </form>
    </div>
</template>
