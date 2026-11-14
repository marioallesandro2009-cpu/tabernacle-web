<script setup>
import { au } from '../../../lib/admin';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';

const props = defineProps({ batch: Object, headers: Array, rows: Array, guess: Object, fields: Array, segments: Array });

const form = useForm({ mapping: { ...props.guess }, default_segment_id: '' });

const columnOf = computed(() => {
    const m = {};
    props.fields.forEach((fl) => {
        const i = form.mapping[fl.key];
        if (i !== null && i !== '' && i !== undefined) m[i] = fl.label;
    });
    return m;
});
const emailMapped = computed(() => form.mapping.email !== null && form.mapping.email !== '' && form.mapping.email !== undefined);
const segmentMapped = computed(() => form.mapping.segment !== null && form.mapping.segment !== '' && form.mapping.segment !== undefined);

function run() {
    form.transform((d) => ({
        mapping: Object.fromEntries(Object.entries(d.mapping).map(([k, v]) => [k, v === '' || v === undefined ? null : Number(v)])),
        default_segment_id: d.default_segment_id || null,
    })).post(au(`/admin/outreach/import/${props.batch.id}/run`));
}
</script>

<template>
    <Head title="Cocokkan kolom" />
    <PageHeader title="Cocokkan kolom" :subtitle="`File ${batch.filename}. Pilih kolom mana yang berisi nama, email, dan seterusnya. Tebakan otomatis sudah diisi, silakan periksa.`">
        <Link :href="au('/admin/outreach/import')" class="o-btn">Ganti file</Link>
    </PageHeader>

    <div class="grid gap-5 xl:grid-cols-[360px_1fr]">
        <form class="o-card space-y-4 p-5 xl:self-start" @submit.prevent="run">
            <div v-for="fl in fields" :key="fl.key">
                <label class="o-label" :for="`m-${fl.key}`">{{ fl.label }} <span v-if="fl.key === 'email'" class="text-rose-500">*</span></label>
                <select :id="`m-${fl.key}`" v-model="form.mapping[fl.key]" class="o-input">
                    <option :value="null">— tidak ada di file —</option>
                    <option v-for="(h, i) in headers" :key="i" :value="i">{{ h }}</option>
                </select>
            </div>
            <p v-if="form.errors.mapping" class="o-err">{{ form.errors.mapping }}</p>

            <div v-if="!segmentMapped">
                <label class="o-label" for="m-seg">Masukkan semua ke segmen</label>
                <select id="m-seg" v-model="form.default_segment_id" class="o-input"><option value="">Tanpa segmen (atur nanti)</option><option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }}</option></select>
                <p class="mt-1 text-xs text-slate-500">File ini tidak punya kolom segmen. Anda juga bisa memindahkan kontak ke segmen nanti.</p>
            </div>
            <p v-else class="rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800">Segmen dibaca dari kolom file. Segmen yang belum ada dibuat otomatis.</p>

            <button class="o-btn o-btn-primary w-full" :disabled="form.processing || !emailMapped">{{ form.processing ? 'Memulai…' : 'Mulai impor' }}</button>
            <p v-if="!emailMapped" class="text-center text-xs text-rose-600">Pilih kolom email dulu.</p>
        </form>

        <div class="o-card overflow-hidden xl:self-start">
            <div class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-900">Pratinjau {{ rows.length }} baris pertama</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px]">
                    <thead class="bg-slate-50/70">
                        <tr>
                            <th v-for="(h, i) in headers" :key="i" class="o-th align-bottom">
                                <div class="mb-1 min-h-[22px]"><Chip v-if="columnOf[i]" tone="blue" :dot="false">{{ columnOf[i] }}</Chip></div>
                                {{ h }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(r, ri) in rows" :key="ri">
                            <td v-for="(h, i) in headers" :key="i" class="o-td max-w-[220px] truncate" :class="columnOf[i] ? 'bg-sky-50/40 text-slate-900' : 'text-slate-400'">{{ r[i] || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
