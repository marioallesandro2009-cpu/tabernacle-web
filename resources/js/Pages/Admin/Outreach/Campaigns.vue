<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Empty from '../../../Components/ui/Empty.vue';
import { campaignStatus } from '../../../lib/outreach';

defineProps({ campaigns: Array });
const pct = (c) => (c.contacts ? Math.min(100, Math.round((c.started / c.contacts) * 100)) : 0);
</script>

<template>
    <Head title="Kampanye Outreach" />
    <PageHeader title="Kampanye" subtitle="Rangkaian email ke satu segmen: email pertama, lalu follow-up otomatis sampai orangnya membalas, bounce, atau berhenti berlangganan.">
        <Link href="/admin/outreach/campaigns/create" class="o-btn o-btn-primary"><Icon name="plus" class="h-4 w-4" /> Kampanye baru</Link>
    </PageHeader>

    <div v-if="campaigns.length" class="grid gap-4 lg:grid-cols-2">
        <Link v-for="c in campaigns" :key="c.id" :href="`/admin/outreach/campaigns/${c.id}`" class="o-card group block p-5 transition hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="truncate text-base font-semibold text-slate-900 group-hover:text-brand">{{ c.name }}</h3>
                    <p class="mt-0.5 text-sm text-slate-500">{{ c.segment || 'Semua segmen' }} · dibuat {{ c.at }}</p>
                </div>
                <Chip :tone="campaignStatus[c.status]?.tone">{{ campaignStatus[c.status]?.label }}</Chip>
            </div>
            <div class="mt-4">
                <div class="mb-1.5 flex justify-between text-xs text-slate-500"><span>{{ c.started }} dari {{ c.contacts }} kontak sudah dikirimi</span><span class="tabular-nums">{{ pct(c) }}%</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand transition-all" :style="{ width: pct(c) + '%' }" /></div>
            </div>
            <div class="mt-4 grid grid-cols-4 gap-2 border-t border-slate-100 pt-4 text-center">
                <div><div class="text-lg font-semibold tabular-nums text-slate-900">{{ c.sent }}</div><div class="text-[11px] uppercase tracking-wide text-slate-400">Email</div></div>
                <div><div class="text-lg font-semibold tabular-nums text-emerald-600">{{ c.replied }}</div><div class="text-[11px] uppercase tracking-wide text-slate-400">Dibalas</div></div>
                <div><div class="text-lg font-semibold tabular-nums text-rose-600">{{ c.bounced }}</div><div class="text-[11px] uppercase tracking-wide text-slate-400">Bounce</div></div>
                <div><div class="text-lg font-semibold tabular-nums text-slate-900">{{ c.reply_rate }}%</div><div class="text-[11px] uppercase tracking-wide text-slate-400">Reply rate</div></div>
            </div>
        </Link>
    </div>
    <div v-else class="o-card">
        <Empty icon="plane" title="Belum ada kampanye" text="Siapkan dulu akun pengirim, kontak, dan template. Setelah itu buat kampanye pertama.">
            <Link href="/admin/outreach/campaigns/create" class="o-btn o-btn-primary"><Icon name="plus" class="h-4 w-4" /> Buat kampanye</Link>
        </Empty>
    </div>
</template>
