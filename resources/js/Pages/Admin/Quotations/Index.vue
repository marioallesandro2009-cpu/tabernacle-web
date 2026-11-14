<script setup>
import { au } from '../../../lib/admin';
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Empty from '../../../Components/ui/Empty.vue';

const props = defineProps({ rows: Object, filters: Object, statuses: Object, counts: Object });
const q = ref(props.filters.q || '');
const tone = { draft: 'slate', sent: 'blue', accepted: 'green', rejected: 'red' };
const go = (extra = {}) => router.get(au('/admin/quotations'), { ...props.filters, q: q.value || undefined, page: undefined, ...extra }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Penawaran harga" />
    <PageHeader title="Penawaran harga" subtitle="Buat penawaran resmi berkop perusahaan, ubah isinya kapan saja, lalu unduh sebagai PDF. Penawaran bisa dibuat langsung dari halaman lead.">
        <Link :href="au('/admin/quotations/create')" class="o-btn o-btn-primary"><Icon name="plus" class="h-4 w-4" /> Penawaran baru</Link>
    </PageHeader>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <button class="o-btn o-btn-sm" :class="{ 'o-btn-primary': !filters.status }" @click="go({ status: undefined })">Semua</button>
        <button v-for="(label, key) in statuses" :key="key" class="o-btn o-btn-sm" :class="{ 'o-btn-primary': filters.status === key }" @click="go({ status: key })">{{ label }} <span class="opacity-70">{{ counts[key] }}</span></button>
        <form class="ml-auto w-full sm:w-72" @submit.prevent="go()"><input v-model="q" class="o-input" placeholder="Cari nomor, klien, paket…"></form>
    </div>

    <div class="o-card overflow-hidden">
        <div v-if="rows.data.length" class="overflow-x-auto">
            <table class="w-full min-w-[760px]">
                <thead class="border-b border-slate-100 bg-slate-50/70"><tr><th class="o-th">Nomor</th><th class="o-th">Klien</th><th class="o-th">Perihal</th><th class="o-th">Tanggal</th><th class="o-th">Status</th><th class="o-th text-right">Total</th><th class="o-th"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows.data" :key="r.id" class="hover:bg-slate-50/60">
                        <td class="o-td"><Link :href="au(`/admin/quotations/${r.id}/edit`)" class="font-medium text-brand hover:underline">{{ r.number }}</Link></td>
                        <td class="o-td"><div class="font-medium text-slate-900">{{ r.client }}</div><div v-if="r.company" class="text-xs text-slate-500">{{ r.company }}</div></td>
                        <td class="o-td max-w-[260px] truncate text-slate-600">{{ r.title }}</td>
                        <td class="o-td whitespace-nowrap text-slate-500">{{ r.issued }}</td>
                        <td class="o-td"><Chip :tone="tone[r.status]">{{ statuses[r.status] }}</Chip><div v-if="r.expired" class="mt-1 text-xs text-amber-600">Sudah lewat masa berlaku</div></td>
                        <td class="o-td whitespace-nowrap text-right font-semibold tabular-nums text-slate-900">{{ r.total }}</td>
                        <td class="o-td text-right"><a :href="au(`/admin/quotations/${r.id}/pdf?download=1`)" class="o-btn o-btn-sm o-btn-ghost" aria-label="Unduh PDF"><Icon name="download" class="h-4 w-4" /></a></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Empty v-else icon="doc" :title="filters.q || filters.status ? 'Tidak ada penawaran yang cocok' : 'Belum ada penawaran'" text="Buka salah satu lead lalu klik Buat penawaran, atau buat penawaran baru dari awal.">
            <Link :href="au('/admin/quotations/create')" class="o-btn o-btn-primary"><Icon name="plus" class="h-4 w-4" /> Penawaran baru</Link>
        </Empty>
        <div v-if="rows.data.length" class="flex items-center justify-between border-t border-slate-100 px-4 py-2 text-xs text-slate-500"><span>{{ rows.from }}–{{ rows.to }} dari {{ rows.total }}</span><Pagination :links="rows.links" /></div>
    </div>
</template>
