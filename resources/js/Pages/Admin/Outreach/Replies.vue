<script setup>
import { au } from '../../../lib/admin';
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Empty from '../../../Components/ui/Empty.vue';
import StatCard from '../../../Components/ui/StatCard.vue';
import { replyStatus } from '../../../lib/outreach';

const props = defineProps({ messages: Object, tab: String, label: String, selected: Object, counts: Object, polled: String, summary: Object, statuses: Object, ours: Object, ourStatus: String, ourSource: String });

const labels = {
    interested: { text: 'Tertarik', tone: 'green' },
    not_now: { text: 'Belum sekarang', tone: 'amber' },
    not_interested: { text: 'Tidak tertarik', tone: 'red' },
};
const tabs = [
    { key: 'reply', text: 'Balasan', count: () => props.counts.reply },
    { key: 'auto', text: 'Otomatis', count: () => props.counts.auto },
    { key: 'bounce', text: 'Bounce', count: () => props.counts.bounce },
    { key: 'ours', text: 'Balasan kita', count: () => props.counts.ours },
];
const ourFilter = (params) => go({ tab: 'ours', status: props.ourStatus || undefined, source: props.ourSource || undefined, ...params });
const polling = ref(false);

const go = (params) => router.get(au('/admin/outreach/replies'), params, { preserveState: true, preserveScroll: true });
const open = (id) => go({ tab: props.tab, label: props.label || undefined, id });
const setTab = (tab) => go({ tab });
const setLabel = (label) => go({ tab: props.tab, label: label || undefined });

function setMessageLabel(label) {
    if (label === 'not_interested' && !confirm('Tandai "tidak tertarik"? Kontak ini juga dimasukkan ke daftar jangan dikirimi, jadi tidak akan menerima kampanye apa pun lagi.')) return;
    router.post(au(`/admin/outreach/replies/${props.selected.id}/label`), { label: props.selected.label === label ? null : label }, { preserveScroll: true });
}
const makeLead = () => router.post(au(`/admin/outreach/replies/${props.selected.id}/lead`));
const poll = () => { polling.value = true; router.post(au('/admin/outreach/replies/poll'), {}, { preserveScroll: true, onFinish: () => (polling.value = false) }); };
</script>

<template>
    <Head title="Balasan" />
    <PageHeader title="Balasan" :subtitle="`Balasan dari semua akun pengirim di satu tempat. Kotak masuk dicek otomatis tiap 10 menit${polled ? ', terakhir ' + polled : ''}.`">
        <button class="o-btn" :disabled="polling" @click="poll">
            <svg v-if="polling" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
            <Icon v-else name="inbox" class="h-4 w-4" />
            {{ polling ? 'Mengecek…' : 'Cek sekarang' }}
        </button>
    </PageHeader>

    <div class="mb-4 grid grid-cols-3 gap-3 md:max-w-xl">
        <button class="text-left" @click="ourFilter({ status: 'draft' })"><StatCard label="Draf menunggu" :value="summary.draft" tone="slate" hint="Perlu ditinjau lalu dikirim" /></button>
        <button class="text-left" @click="ourFilter({ status: 'failed' })"><StatCard label="Gagal kirim" :value="summary.failed" tone="red" hint="Bisa dikirim ulang" /></button>
        <button class="text-left" @click="ourFilter({ status: 'rejected' })"><StatCard label="Ditolak" :value="summary.rejected" tone="amber" hint="Dari email pribadi" /></button>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="inline-flex flex-wrap rounded-xl bg-slate-200/60 p-1">
            <button v-for="t in tabs" :key="t.key" class="rounded-lg px-3.5 py-1.5 text-sm font-medium transition" :class="tab === t.key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'" @click="setTab(t.key)">{{ t.text }} <span class="ml-1 tabular-nums text-slate-400">{{ t.count() }}</span></button>
        </div>
        <template v-if="tab === 'ours'">
            <button class="o-btn o-btn-sm" :class="!ourStatus ? 'o-btn-primary' : ''" @click="ourFilter({ status: undefined })">Semua status</button>
            <button v-for="(text, key) in statuses" :key="key" class="o-btn o-btn-sm" :class="ourStatus === key ? 'o-btn-primary' : ''" @click="ourFilter({ status: key })">{{ text }}</button>
            <select class="o-input h-8 w-auto py-0 text-sm" :value="ourSource || ''" @change="ourFilter({ source: $event.target.value || undefined })">
                <option value="">Semua sumber</option><option value="website">Dari website</option><option value="email">Dari email pribadi</option>
            </select>
        </template>
        <template v-if="tab === 'reply'">
            <button class="o-btn o-btn-sm" :class="!label ? 'o-btn-primary' : ''" @click="setLabel('')">Semua</button>
            <button class="o-btn o-btn-sm" :class="label === 'none' ? 'o-btn-primary' : ''" @click="setLabel('none')">Belum dilabeli <span class="ml-1 opacity-70">{{ counts.unlabeled }}</span></button>
            <button v-for="(l, key) in labels" :key="key" class="o-btn o-btn-sm" :class="label === key ? 'o-btn-primary' : ''" @click="setLabel(key)">{{ l.text }}</button>
        </template>
    </div>

    <div v-if="tab === 'ours'" class="o-card overflow-hidden">
        <ul v-if="ours.data.length" class="divide-y divide-slate-100">
            <li v-for="m in ours.data" :key="m.id" class="flex flex-wrap items-start gap-3 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-slate-900">{{ m.name }}</span>
                        <span v-if="m.organization" class="text-xs text-slate-500">{{ m.organization }}</span>
                        <Chip :tone="replyStatus[m.reply_status]?.tone">{{ statuses[m.reply_status] }}</Chip>
                        <Chip v-if="m.source_label" tone="slate" :dot="false">{{ m.source_label }}</Chip>
                    </div>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ m.preview }}</p>
                    <p v-if="m.reason && ['rejected', 'failed', 'bounced'].includes(m.reply_status)" class="mt-1 text-xs text-rose-600">{{ m.reason }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ m.at }}<span v-if="m.by"> · {{ m.by }}</span><span v-if="m.campaign"> · {{ m.campaign }}</span></p>
                </div>
                <Link v-if="m.thread_id" :href="au(`/admin/outreach/threads/${m.thread_id}`)" class="o-btn o-btn-sm">Buka percakapan</Link>
            </li>
        </ul>
        <Empty v-else icon="mail" title="Belum ada balasan kita" text="Balasan yang Anda kirim dari website atau dari email pribadi muncul di sini beserta statusnya." />
        <div v-if="ours.data.length" class="border-t border-slate-100 px-2"><Pagination :links="ours.links" /></div>
    </div>

    <div v-else-if="messages.data.length" class="grid gap-4 lg:grid-cols-[360px_1fr]">
        <div class="o-card overflow-hidden lg:self-start">
            <ul class="max-h-[70vh] divide-y divide-slate-100 overflow-y-auto">
                <li v-for="m in messages.data" :key="m.id">
                    <button class="block w-full px-4 py-3 text-left transition hover:bg-slate-50" :class="selected?.id === m.id ? 'bg-brand/[0.05]' : ''" @click="open(m.id)">
                        <div class="flex items-center justify-between gap-2"><span class="truncate text-sm font-semibold text-slate-900">{{ m.name }}</span><span class="shrink-0 text-[11px] text-slate-400">{{ m.at }}</span></div>
                        <div v-if="m.organization" class="truncate text-xs text-slate-500">{{ m.organization }}</div>
                        <div class="mt-1 line-clamp-2 text-sm text-slate-600">{{ m.preview }}</div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5"><Chip v-if="m.label" :tone="labels[m.label].tone">{{ labels[m.label].text }}</Chip><Chip v-if="m.lead_id" tone="blue" :dot="false">Jadi lead</Chip><Chip v-if="m.spam" tone="amber" :dot="false">Folder Spam</Chip><span v-if="m.campaign" class="truncate text-[11px] text-slate-400">{{ m.campaign }}</span></div>
                    </button>
                </li>
            </ul>
            <div class="border-t border-slate-100 px-2"><Pagination :links="messages.links" /></div>
        </div>

        <div v-if="selected" class="o-card p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-semibold text-slate-900">{{ selected.contact?.name || selected.contact?.email || 'Pengirim tidak dikenal' }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500"><span v-if="selected.contact?.title">{{ selected.contact.title }}, </span>{{ selected.contact?.organization }}</p>
                    <p class="mt-1 text-sm text-slate-500"><span v-if="selected.contact?.email">{{ selected.contact.email }}</span><span v-if="selected.contact?.phone"> · {{ selected.contact.phone }}</span></p>
                </div>
                <div class="text-right text-xs text-slate-400"><div>{{ selected.at }}</div><div v-if="selected.campaign">Kampanye: {{ selected.campaign }}</div><div v-if="selected.account">Ke: {{ selected.account }}</div></div>
            </div>

            <p v-if="selected.spam" class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-100">Pesan ini ditemukan di folder <b>{{ selected.folder }}</b>, bukan di kotak masuk. Tandai "bukan spam" di kotak email supaya balasan berikutnya dari orang ini tidak masuk Spam.</p>
            <div v-if="selected.kind === 'reply'" class="mt-4 flex flex-wrap items-center gap-2 border-y border-slate-100 py-3">
                <span class="mr-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Label</span>
                <button v-for="(l, key) in labels" :key="key" class="o-btn o-btn-sm" :class="selected.label === key ? 'o-btn-primary' : ''" @click="setMessageLabel(key)">{{ l.text }}</button>
                <span class="flex-1" />
                <Link v-if="selected.thread_id" :href="au(`/admin/outreach/threads/${selected.thread_id}`)" class="o-btn o-btn-sm"><Icon name="mail" class="h-3.5 w-3.5" /> Buka percakapan &amp; balas</Link>
                <Link v-if="selected.lead_id" :href="au(`/admin/leads/${selected.lead_id}`)" class="o-btn o-btn-sm"><Icon name="check" class="h-3.5 w-3.5" /> Lihat lead</Link>
                <button v-else class="o-btn o-btn-sm o-btn-primary" @click="makeLead"><Icon name="plus" class="h-3.5 w-3.5" /> Jadikan lead</button>
            </div>

            <div class="mt-5">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ selected.subject || '(tanpa subjek)' }}</div>
                <pre class="whitespace-pre-wrap rounded-2xl bg-slate-50 p-4 font-[inherit] text-sm leading-relaxed text-slate-800">{{ selected.body }}</pre>
            </div>

            <div v-if="selected.thread.length" class="mt-6">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Percakapan</div>
                <div class="space-y-2">
                    <div v-for="t in selected.thread" :key="t.id" class="rounded-2xl p-3 text-sm ring-1" :class="t.direction === 'out' ? 'bg-brand/[0.04] ring-brand/15' : 'bg-white ring-slate-200'">
                        <div class="mb-1 flex items-center justify-between gap-2 text-xs"><span class="font-semibold" :class="t.direction === 'out' ? 'text-brand' : 'text-slate-700'">{{ t.direction === 'out' ? (t.kind === 'reply_manual' ? 'Balasan kita' + (t.source_label ? ' · ' + t.source_label : '') : `Email kami${t.step ? ' (langkah ' + t.step + ')' : ''}`) : 'Balasan' }}</span><span class="text-slate-400">{{ t.at }}</span></div>
                        <div class="font-medium text-slate-800">{{ t.subject }}</div>
                        <div class="mt-1 line-clamp-4 whitespace-pre-line text-slate-600">{{ t.body }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-else class="o-card">
        <Empty icon="inbox" :title="tab === 'reply' ? 'Belum ada balasan' : tab === 'auto' ? 'Belum ada balasan otomatis' : 'Belum ada bounce'" text="Balasan yang masuk ke kotak email akun pengirim muncul di sini setelah dicek. Klik Cek sekarang untuk memeriksa langsung.">
            <button class="o-btn" :disabled="polling" @click="poll"><Icon name="inbox" class="h-4 w-4" /> Cek sekarang</button>
        </Empty>
    </div>
</template>
