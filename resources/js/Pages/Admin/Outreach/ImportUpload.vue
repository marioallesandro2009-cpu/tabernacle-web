<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';
import { importStatus } from '../../../lib/outreach';

defineProps({ recent: Array });

const form = useForm({ file: null });
const over = ref(false);
const input = ref(null);

function pick(file) {
    if (!file) return;
    form.file = file;
    form.post('/admin/outreach/import', { forceFormData: true });
}
function drop(e) {
    over.value = false;
    pick(e.dataTransfer.files[0]);
}
</script>

<template>
    <Head title="Impor kontak" />
    <PageHeader title="Impor kontak" subtitle="Unggah file Excel atau CSV. Di langkah berikutnya Anda mencocokkan kolom file dengan data kontak, jadi urutan kolom boleh berbeda-beda.">
        <Link href="/admin/outreach/contacts" class="o-btn">Kembali ke kontak</Link>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
        <div>
            <div
                class="o-card flex cursor-pointer flex-col items-center border-2 border-dashed px-6 py-14 text-center transition"
                :class="over ? 'border-brand bg-brand/[0.04]' : 'border-slate-200 hover:border-slate-300'"
                role="button" tabindex="0" aria-label="Pilih file untuk diunggah"
                @click="input.click()" @keydown.enter="input.click()" @dragover.prevent="over = true" @dragleave="over = false" @drop.prevent="drop"
            >
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand"><Icon name="upload" class="h-6 w-6" /></div>
                <h2 class="text-base font-semibold text-slate-900">{{ form.processing ? 'Mengunggah…' : 'Tarik file ke sini, atau klik untuk memilih' }}</h2>
                <p class="mt-1 text-sm text-slate-500">.xlsx, .xls, atau .csv · maksimal 10 MB</p>
                <div v-if="form.progress" class="mt-5 h-2 w-56 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand transition-all" :style="{ width: form.progress.percentage + '%' }" /></div>
                <input ref="input" type="file" class="hidden" accept=".xlsx,.xls,.csv,.txt" @change="pick($event.target.files[0])">
            </div>
            <p v-if="form.errors.file" class="o-err mt-2">{{ form.errors.file }}</p>
        </div>

        <div class="space-y-4">
            <div class="o-card p-5">
                <h3 class="text-sm font-semibold text-slate-900">Format file</h3>
                <ul class="mt-3 space-y-2 text-sm leading-relaxed text-slate-600">
                    <li class="flex gap-2"><Icon name="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-500" /> Baris pertama berisi judul kolom.</li>
                    <li class="flex gap-2"><Icon name="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-500" /> Kolom yang dikenali: nama, jabatan, organisasi, email, telepon, segmen, catatan.</li>
                    <li class="flex gap-2"><Icon name="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-500" /> Kalau ada kolom segmen, segmennya dibuat otomatis.</li>
                    <li class="flex gap-2"><Icon name="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-500" /> Email yang sudah ada dilewati, tidak dobel.</li>
                    <li class="flex gap-2"><Icon name="check" class="mt-1 h-3.5 w-3.5 shrink-0 text-emerald-500" /> Orang yang pernah berhenti berlangganan tetap ditandai, tidak akan dikirimi.</li>
                </ul>
            </div>
            <div v-if="recent.length" class="o-card p-5">
                <h3 class="mb-3 text-sm font-semibold text-slate-900">Impor terakhir</h3>
                <Link v-for="i in recent" :key="i.id" :href="`/admin/outreach/import/${i.id}`" class="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2 text-sm hover:bg-slate-50">
                    <span class="min-w-0"><span class="block truncate font-medium text-slate-700">{{ i.filename }}</span><span class="text-xs text-slate-400">{{ i.at }}</span></span>
                    <Chip :tone="importStatus[i.status]?.tone">{{ importStatus[i.status]?.label }}</Chip>
                </Link>
            </div>
        </div>
    </div>
</template>
