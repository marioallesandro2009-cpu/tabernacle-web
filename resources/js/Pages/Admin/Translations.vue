<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/ui/PageHeader.vue';
import Chip from '../../Components/ui/Chip.vue';
import Empty from '../../Components/ui/Empty.vue';

const props = defineProps({ rows: Array, counts: Object });
const type = ref('');
const onlyBlocking = ref(false);
const types = computed(() => [...new Set(props.rows.map((r) => r.type))]);
const shown = computed(() => props.rows.filter((r) => (!type.value || r.type === type.value) && (!onlyBlocking.value || r.blocking)));
</script>

<template>
    <Head title="Terjemahan belum lengkap" />
    <PageHeader title="Terjemahan belum lengkap" subtitle="Konten yang punya teks Indonesia tapi belum ada versi Inggrisnya. Klik Ubah, lalu pakai tombol Terjemahkan ke Inggris di form." />

    <div class="mb-4 grid gap-3 sm:grid-cols-2 md:max-w-xl">
        <div class="o-card p-4"><div class="text-2xl font-semibold tabular-nums">{{ counts.total }}</div><div class="text-xs text-slate-500">konten dengan bagian Inggris yang kosong</div></div>
        <div class="o-card p-4"><div class="text-2xl font-semibold tabular-nums text-amber-600">{{ counts.blocking }}</div><div class="text-xs text-slate-500">belum tampil sebagai halaman Inggris (canonical ke versi Indonesia, tidak masuk sitemap Inggris)</div></div>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <button class="o-btn o-btn-sm" :class="{ 'o-btn-primary': !type }" @click="type = ''">Semua</button>
        <button v-for="t in types" :key="t" class="o-btn o-btn-sm" :class="{ 'o-btn-primary': type === t }" @click="type = t">{{ t }}</button>
        <label class="ml-2 flex items-center gap-2 text-sm text-slate-600"><input v-model="onlyBlocking" type="checkbox" class="h-4 w-4 rounded border-slate-300"> Hanya yang belum tampil di situs Inggris</label>
    </div>

    <div class="o-card overflow-hidden">
        <table v-if="shown.length" class="w-full">
            <thead class="border-b border-slate-100 bg-slate-50/70"><tr><th class="o-th">Konten</th><th class="o-th">Yang belum ada versi Inggrisnya</th><th class="o-th"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="(r, i) in shown" :key="i">
                    <td class="o-td"><div class="font-medium text-slate-900">{{ r.name }}</div><div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">{{ r.type }}<Chip v-if="r.blocking" tone="amber" :dot="false">Belum di situs Inggris</Chip></div></td>
                    <td class="o-td text-sm text-slate-600">{{ r.missing.join(' · ') }}</td>
                    <td class="o-td whitespace-nowrap text-right"><a v-if="r.public" :href="r.public" target="_blank" rel="noopener" class="o-btn o-btn-sm o-btn-ghost">Lihat /en</a><Link :href="r.edit" class="o-btn o-btn-sm">Ubah</Link></td>
                </tr>
            </tbody>
        </table>
        <Empty v-else icon="check" title="Semua sudah ada versi Inggrisnya" text="Tidak ada konten yang terjemahannya kosong." />
    </div>
</template>
