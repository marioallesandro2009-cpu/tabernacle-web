<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import LineChart from '../../Components/LineChart.vue';

const props = defineProps({ range: String, stats: Object, recent_leads: Array, counts: Object, analytics_enabled: Boolean, can_reset: Boolean });
const resetOpen = ref(false);
const resetForm = useForm({ confirm: '' });
const doReset = () => resetForm.post('/admin/stats/reset', { preserveScroll: true, onSuccess: () => { resetOpen.value = false; resetForm.reset(); } });

const ranges = [['today', 'Hari ini'], ['yesterday', 'Kemarin'], ['7d', '7 hari'], ['30d', '30 hari'], ['90d', '90 hari'], ['custom', 'Kustom']];
const custom = reactive({ from: props.stats?.from || '', to: props.stats?.to || '' });
const setRange = (r) => (r !== 'custom' ? router.get('/admin', { range: r }, { preserveScroll: true }) : router.get('/admin', { range: 'custom', ...custom }, { preserveScroll: true }));

const num = (n) => new Intl.NumberFormat('id-ID').format(n || 0);
const s = computed(() => props.stats);
const statusLabel = { baru: 'Baru', dihubungi: 'Dihubungi', negosiasi: 'Negosiasi', dp: 'DP', lunas: 'Lunas', batal: 'Batal' };
const sourceLabel = (k) => ({ direct: 'Langsung', google: 'Mesin pencari', instagram: 'Instagram', tiktok: 'TikTok', whatsapp: 'WhatsApp', facebook: 'Facebook', youtube: 'YouTube', other: 'Lainnya' })[k] || k;
const kpis = computed(() => s.value ? [
    ['Tayangan halaman', num(s.value.kpi.views)],
    ['Pengunjung (harian unik)', num(s.value.kpi.visitors)],
    ['Halaman / kunjungan', s.value.kpi.pages_per_visit],
    ['Lead dari form', num(s.value.kpi.leads)],
    ['Klik WhatsApp', num(s.value.kpi.wa_clicks)],
    ['Konversi', s.value.kpi.conversion + '%'],
] : []);
const maxOf = (rows) => Math.max(1, ...rows.map((r) => r.views));
</script>

<template>
    <Head title="Dashboard" />
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <div v-if="stats" class="flex flex-wrap items-center gap-1">
            <button v-for="r in ranges" :key="r[0]" class="btn btn-sm" :class="range === r[0] ? 'btn-primary' : ''" @click="setRange(r[0])">{{ r[1] }}</button>
            <button v-if="can_reset" class="btn btn-sm text-red-600" title="Hapus semua statistik pengunjung" @click="resetOpen = true">Reset statistik</button>
        </div>
    </div>

    <div v-if="resetOpen" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4" @keydown.esc="resetOpen = false">
        <form class="card w-full max-w-md p-5" role="dialog" aria-modal="true" aria-labelledby="reset-title" @submit.prevent="doReset">
            <h2 id="reset-title" class="text-lg font-semibold">Reset statistik pengunjung?</h2>
            <p class="mt-2 text-sm text-stone-600">Semua tayangan halaman, pengunjung, sumber trafik, dan grafik akan dihapus permanen, lalu penghitungan dimulai dari nol. <b>Data leads tidak ikut terhapus.</b> Tindakan ini tercatat di Log aktivitas.</p>
            <label class="label mt-4" for="reset-confirm">Ketik <b>RESET</b> untuk melanjutkan</label>
            <input id="reset-confirm" v-model="resetForm.confirm" class="input" autocomplete="off" autofocus>
            <p v-if="resetForm.errors.confirm" class="err">{{ resetForm.errors.confirm }}</p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="btn" @click="resetOpen = false">Batal</button>
                <button class="btn btn-danger" :disabled="resetForm.confirm !== 'RESET' || resetForm.processing">Reset sekarang</button>
            </div>
        </form>
    </div>

    <div v-if="stats && range === 'custom'" class="mb-4 flex flex-wrap items-end gap-2">
        <div><label class="label">Dari</label><input v-model="custom.from" type="date" class="input"></div>
        <div><label class="label">Sampai</label><input v-model="custom.to" type="date" class="input"></div>
        <button class="btn btn-primary" @click="setRange('custom')">Terapkan</button>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <Link v-if="counts.new_leads !== null" href="/admin/leads?status=baru" class="card p-4 hover:border-brand"><div class="text-3xl font-bold text-accent-deep">{{ counts.new_leads }}</div><div class="text-sm text-stone-600">Lead baru menunggu</div></Link>
        <Link v-if="counts.tours !== null" href="/admin/tours" class="card p-4 hover:border-brand"><div class="text-3xl font-bold text-brand">{{ counts.tours }}</div><div class="text-sm text-stone-600">Paket tour aktif</div></Link>
        <Link v-if="counts.posts !== null" href="/admin/posts" class="card p-4 hover:border-brand"><div class="text-3xl font-bold text-brand">{{ counts.posts }}</div><div class="text-sm text-stone-600">Artikel terbit</div></Link>
        <Link v-if="counts.drafts !== null" href="/admin/posts?status=draft" class="card p-4 hover:border-brand"><div class="text-3xl font-bold text-stone-700">{{ counts.drafts }}</div><div class="text-sm text-stone-600">Artikel draft</div></Link>
    </div>

    <template v-if="stats">
        <div v-if="!analytics_enabled" class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Analitik pengunjung dimatikan di Pengaturan, jadi data baru tidak dicatat.</div>
        <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            <div v-for="k in kpis" :key="k[0]" class="card p-4"><div class="text-2xl font-bold">{{ k[1] }}</div><div class="text-xs text-stone-500">{{ k[0] }}</div></div>
        </div>

        <div class="card mb-5 p-4">
            <h2 class="mb-2 font-semibold">Pengunjung & tayangan per hari</h2>
            <LineChart :labels="stats.series.map((x) => x.label)" :datasets="[{ label: 'Tayangan', data: stats.series.map((x) => x.views), color: '#0b1a5c' }, { label: 'Pengunjung', data: stats.series.map((x) => x.visitors), color: '#e8973f' }]" />
        </div>

        <div class="mb-5 grid gap-5 lg:grid-cols-2">
            <div class="card p-4">
                <h2 class="mb-3 font-semibold">Halaman terpopuler</h2>
                <div v-for="r in stats.top_pages" :key="r.key" class="mb-2">
                    <div class="flex justify-between gap-2 text-sm"><span class="truncate">{{ r.key }}</span><span class="shrink-0 font-medium">{{ num(r.views) }}</span></div>
                    <div class="h-1.5 rounded bg-stone-100"><div class="h-1.5 rounded bg-brand" :style="{ width: (r.views / maxOf(stats.top_pages)) * 100 + '%' }" /></div>
                </div>
                <p v-if="!stats.top_pages.length" class="text-sm text-stone-500">Belum ada data.</p>
            </div>
            <div class="card p-4">
                <h2 class="mb-3 font-semibold">Paket tour terpopuler</h2>
                <div v-for="r in stats.top_tours" :key="r.key" class="mb-2">
                    <div class="flex justify-between gap-2 text-sm"><span class="truncate">{{ r.name }}</span><span class="shrink-0 font-medium">{{ num(r.views) }}</span></div>
                    <div class="h-1.5 rounded bg-stone-100"><div class="h-1.5 rounded bg-accent" :style="{ width: (r.views / maxOf(stats.top_tours)) * 100 + '%' }" /></div>
                </div>
                <p v-if="!stats.top_tours.length" class="text-sm text-stone-500">Belum ada data.</p>
            </div>
            <div class="card p-4">
                <h2 class="mb-3 font-semibold">Artikel terpopuler</h2>
                <div v-for="r in stats.top_posts" :key="r.key" class="mb-2">
                    <div class="flex justify-between gap-2 text-sm"><span class="truncate">{{ r.name }}</span><span class="shrink-0 font-medium">{{ num(r.views) }}</span></div>
                    <div class="h-1.5 rounded bg-stone-100"><div class="h-1.5 rounded bg-teal-600" :style="{ width: (r.views / maxOf(stats.top_posts)) * 100 + '%' }" /></div>
                </div>
                <p v-if="!stats.top_posts.length" class="text-sm text-stone-500">Belum ada data.</p>
            </div>
            <div class="card p-4">
                <h2 class="mb-3 font-semibold">Halaman masuk pertama</h2>
                <div v-for="r in stats.entries" :key="r.key" class="mb-1.5 flex justify-between gap-2 text-sm"><span class="truncate">{{ r.key }}</span><span class="font-medium">{{ num(r.views) }}</span></div>
                <p v-if="!stats.entries.length" class="text-sm text-stone-500">Belum ada data.</p>
            </div>
        </div>

        <div class="mb-5 grid gap-5 md:grid-cols-3">
            <div class="card p-4"><h2 class="mb-2 font-semibold">Sumber trafik</h2><LineChart v-if="stats.sources.length" type="doughnut" :height="200" :labels="stats.sources.map((x) => sourceLabel(x.key))" :datasets="[{ label: 'Tayangan', data: stats.sources.map((x) => x.views) }]" /><p v-else class="text-sm text-stone-500">Belum ada data.</p></div>
            <div class="card p-4"><h2 class="mb-2 font-semibold">Perangkat</h2><LineChart v-if="stats.devices.length" type="doughnut" :height="200" :labels="stats.devices.map((x) => x.key)" :datasets="[{ label: 'Tayangan', data: stats.devices.map((x) => x.views) }]" /><p v-else class="text-sm text-stone-500">Belum ada data.</p></div>
            <div class="card p-4"><h2 class="mb-2 font-semibold">Browser</h2><LineChart v-if="stats.browsers.length" type="doughnut" :height="200" :labels="stats.browsers.map((x) => x.key)" :datasets="[{ label: 'Tayangan', data: stats.browsers.map((x) => x.views) }]" /><p v-else class="text-sm text-stone-500">Belum ada data.</p></div>
        </div>
    </template>

    <div v-if="recent_leads.length" class="card">
        <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
            <h2 class="font-semibold">Lead terbaru</h2>
            <Link href="/admin/leads" class="text-sm text-brand hover:underline">Lihat semua</Link>
        </div>
        <ul class="divide-y divide-stone-100">
            <li v-for="l in recent_leads" :key="l.id">
                <Link :href="`/admin/leads/${l.id}`" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-stone-50">
                    <span class="font-medium">{{ l.name }}</span><span class="text-sm text-stone-500">{{ l.phone }}</span>
                    <span class="flex-1 truncate text-sm text-stone-600">{{ l.tour }}</span>
                    <span class="badge">{{ statusLabel[l.status] || l.status }}</span><span class="text-xs text-stone-400">{{ l.at }}</span>
                </Link>
            </li>
        </ul>
    </div>
</template>
