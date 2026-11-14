<script setup>
import { au } from '../../../lib/admin';
import { onBeforeUnmount, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import StatCard from '../../../Components/ui/StatCard.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Empty from '../../../Components/ui/Empty.vue';
import { campaignStatus, sendStatus } from '../../../lib/outreach';

const props = defineProps({ campaign: Object, stats: Object, variants: { type: Array, default: () => [] }, rows: Object, filter: String });

const post = (action, confirmText) => {
    if (confirmText && !confirm(confirmText)) return;
    router.post(au(`/admin/outreach/campaigns/${props.campaign.id}/${action}`), {}, { preserveScroll: true });
};
const remove = () => {
    if (confirm(`Hapus kampanye "${props.campaign.name}" beserta riwayatnya?`)) router.delete(au(`/admin/outreach/campaigns/${props.campaign.id}`));
};
const setFilter = (s) => router.get(au(`/admin/outreach/campaigns/${props.campaign.id}`), s ? { status: s } : {}, { preserveState: true, replace: true });

// numbers move while it runs, so look again every few seconds
let timer;
onMounted(() => { timer = setInterval(() => { if (props.campaign.status === 'running') router.reload({ only: ['stats', 'rows', 'campaign'], preserveScroll: true }); }, 8000); });
onBeforeUnmount(() => clearInterval(timer));

const filters = ['queued', 'sent', 'replied', 'bounced', 'auto_replied', 'unsubscribed', 'invalid', 'failed'];
</script>

<template>
    <Head :title="campaign.name" />
    <PageHeader :title="campaign.name" :subtitle="`${campaign.segment || 'Semua segmen'} · ${campaign.days}, jam ${campaign.window} WIB · jeda ${campaign.gap}`">
        <Chip :tone="campaignStatus[campaign.status]?.tone">{{ campaignStatus[campaign.status]?.label }}</Chip>
        <button v-if="campaign.status === 'running'" class="o-btn" @click="post('pause')">Jeda</button>
        <button v-if="campaign.status === 'paused'" class="o-btn o-btn-primary" @click="post('resume')">Lanjutkan</button>
        <button v-if="campaign.status === 'draft'" class="o-btn o-btn-primary" @click="post('start', 'Mulai kampanye ini? Email pertama dikirim bertahap di dalam jam kirim.')">Mulai kampanye</button>
        <a :href="au(`/admin/outreach/campaigns/${campaign.id}/export`)" class="o-btn"><Icon name="download" class="h-4 w-4" /> Unduh Excel</a>
        <Link v-if="campaign.status !== 'running'" :href="au(`/admin/outreach/campaigns/${campaign.id}/edit`)" class="o-btn">Ubah</Link>
        <button v-if="campaign.status !== 'running'" class="o-btn o-btn-danger" aria-label="Hapus" @click="remove"><Icon name="trash" class="h-4 w-4" /></button>
    </PageHeader>

    <div v-if="campaign.problem && campaign.status !== 'completed'" class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-100">{{ campaign.problem }} <Link :href="au(`/admin/outreach/campaigns/${campaign.id}/edit`)" class="font-semibold underline">Ubah kampanye</Link></div>
    <div v-else-if="campaign.status === 'running'" class="mb-4 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm ring-1" :class="campaign.in_window ? 'bg-emerald-50 text-emerald-900 ring-emerald-100' : 'bg-slate-100 text-slate-700 ring-slate-200'">
        <span class="h-2 w-2 shrink-0 rounded-full" :class="campaign.in_window ? 'animate-pulse bg-emerald-500' : 'bg-slate-400'" />
        {{ campaign.in_window ? 'Sedang di dalam jam kirim. Email berikutnya dikirim bertahap.' : 'Di luar jam kirim. Pengiriman berlanjut otomatis pada jadwal berikutnya.' }}
    </div>
    <div v-else-if="campaign.status === 'draft'" class="mb-4 rounded-2xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-100">Kampanye ini masih draft dan belum mengirim apa pun. Periksa pengaturan di bawah, lalu klik <b>Mulai kampanye</b>.</div>

    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <StatCard label="Kontak" :value="stats.contacts" :hint="`${stats.queued} masih antre`" />
        <StatCard label="Email terkirim" :value="stats.sent_messages" tone="blue" :hint="`${stats.started} orang sudah dikirimi`" />
        <StatCard label="Dibalas" :value="stats.replied" tone="green" />
        <StatCard label="Reply rate" :value="stats.reply_rate + '%'" tone="green" hint="Dibalas dari yang dikirimi" />
        <StatCard label="Bounce" :value="stats.bounced" tone="red" :hint="`${stats.auto_replied} balasan otomatis`" />
        <StatCard label="Berhenti" :value="stats.unsubscribed" tone="violet" :hint="`${stats.invalid} tidak valid`" />
    </div>

    <div v-if="variants.length" class="o-card mb-5 p-5">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Uji A/B subjek email pertama</h2>
            <span class="text-xs text-slate-500">Pemenang ditentukan setelah tiap versi dikirim ke minimal 20 orang.</span>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            <div v-for="v in variants" :key="v.variant" class="rounded-2xl p-4 ring-1" :class="v.best ? 'bg-emerald-50 ring-emerald-200' : 'bg-slate-50 ring-slate-100'">
                <div class="mb-2 flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-white" :class="v.best ? 'bg-emerald-600' : 'bg-slate-500'">{{ v.variant }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800" :title="v.subject">{{ v.subject || '—' }}</span>
                    <Chip v-if="v.best" tone="green">Lebih banyak dibalas</Chip>
                </div>
                <div class="flex items-end gap-6">
                    <div><div class="text-2xl font-semibold tabular-nums text-slate-900">{{ v.rate }}%</div><div class="text-xs text-slate-500">reply rate</div></div>
                    <div class="text-sm text-slate-600"><b class="tabular-nums">{{ v.replied }}</b> dibalas dari <b class="tabular-nums">{{ v.started }}</b> orang</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-5 grid gap-4 lg:grid-cols-2">
        <div class="o-card p-5">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Langkah</h2>
            <ol class="space-y-2">
                <li v-for="s in campaign.steps" :key="s.step" class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5 text-sm">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-semibold text-white">{{ s.step }}</span>
                    <span class="min-w-0 flex-1 truncate font-medium text-slate-800">{{ s.template }}</span>
                    <span class="shrink-0 text-xs text-slate-500">{{ s.day_offset === 0 ? 'langsung' : `hari ke-${s.day_offset}` }}</span>
                </li>
            </ol>
        </div>
        <div class="o-card p-5">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Akun pengirim hari ini</h2>
            <div v-for="a in campaign.accounts" :key="a.id" class="mb-3 last:mb-0">
                <div class="mb-1 flex items-center justify-between text-sm"><span class="truncate font-medium text-slate-800">{{ a.name }} <span class="font-normal text-slate-400">{{ a.email }}</span></span><span class="shrink-0 tabular-nums text-slate-500">{{ a.today }} / {{ a.limit }}</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" :class="a.today >= a.limit ? 'bg-amber-500' : 'bg-brand'" :style="{ width: Math.min(100, (a.today / a.limit) * 100) + '%' }" /></div>
            </div>
        </div>
    </div>

    <div class="mb-3 flex flex-wrap gap-2">
        <button class="o-btn o-btn-sm" :class="!filter ? 'o-btn-primary' : ''" @click="setFilter('')">Semua</button>
        <button v-for="s in filters" :key="s" class="o-btn o-btn-sm" :class="filter === s ? 'o-btn-primary' : ''" @click="setFilter(s)">{{ sendStatus[s].label }}</button>
    </div>

    <div class="o-card overflow-hidden">
        <div v-if="rows.data.length" class="overflow-x-auto">
            <table class="w-full min-w-[760px]">
                <thead class="border-b border-slate-100 bg-slate-50/70"><tr><th class="o-th">Kontak</th><th class="o-th">Organisasi</th><th class="o-th">Email</th><th class="o-th">Status</th><th class="o-th">Langkah</th><th class="o-th">Terakhir kirim</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows.data" :key="r.id" class="hover:bg-slate-50/60">
                        <td class="o-td"><div class="font-medium text-slate-900">{{ r.name || '—' }}</div><div v-if="r.title" class="text-xs text-slate-500">{{ r.title }}</div></td>
                        <td class="o-td text-slate-600">{{ r.organization || '—' }}</td>
                        <td class="o-td"><div class="text-slate-700">{{ r.email || '—' }}</div><div v-if="r.account" class="text-xs text-slate-400">via {{ r.account }}</div></td>
                        <td class="o-td"><Chip :tone="sendStatus[r.status]?.tone">{{ sendStatus[r.status]?.label || r.status }}</Chip></td>
                        <td class="o-td tabular-nums text-slate-600">{{ r.step }}/{{ campaign.steps.length }}<div v-if="r.next" class="text-xs text-slate-400">berikutnya {{ r.next }}</div></td>
                        <td class="o-td text-slate-500">{{ r.last || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Empty v-else icon="users" :title="filter ? 'Tidak ada kontak dengan status ini' : 'Belum ada kontak di kampanye ini'" :text="filter ? '' : 'Kontak masuk ke kampanye saat kampanye dimulai.'" />
        <div v-if="rows.data.length" class="flex items-center justify-between border-t border-slate-100 px-4 py-2 text-xs text-slate-500"><span>{{ rows.from }}–{{ rows.to }} dari {{ rows.total }}</span><Pagination :links="rows.links" /></div>
    </div>
</template>
