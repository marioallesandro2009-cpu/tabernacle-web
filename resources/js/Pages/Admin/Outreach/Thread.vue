<script setup>
import { au } from '../../../lib/admin';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';
import ThreadView from '../../../Components/outreach/ThreadView.vue';
import { sendStatus } from '../../../lib/outreach';

defineProps({ thread: Object, contact: Object, statuses: Object });
</script>

<template>
    <Head :title="'Percakapan: ' + (contact?.name || contact?.email || '')" />
    <PageHeader :title="contact?.name || contact?.email || 'Percakapan'" :subtitle="[contact?.title, contact?.organization].filter(Boolean).join(' · ') || contact?.email">
        <Link v-if="contact" :href="au(`/admin/outreach/contacts/${contact.id}`)" class="o-btn">Profil kontak</Link>
        <Link :href="au('/admin/outreach/replies')" class="o-btn">Kembali ke Balasan</Link>
    </PageHeader>

    <div class="grid gap-5 xl:grid-cols-[1fr_300px]">
        <div class="o-card p-5"><ThreadView :thread="thread" :statuses="statuses" /></div>
        <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
            <div class="o-card space-y-2 p-5 text-sm">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Percakapan</div>
                <div><span class="text-slate-500">Status:</span> <Chip :tone="sendStatus[thread.status]?.tone">{{ sendStatus[thread.status]?.label || thread.status }}</Chip></div>
                <div v-if="thread.campaign"><span class="text-slate-500">Kampanye:</span> <Link :href="au(`/admin/outreach/campaigns/${thread.campaign_id}`)" class="text-brand hover:underline">{{ thread.campaign }}</Link></div>
                <div v-if="thread.account"><span class="text-slate-500">Akun:</span> {{ thread.account.email }}<span v-if="!thread.account.active" class="text-amber-600"> (nonaktif)</span></div>
                <div v-if="thread.token"><span class="text-slate-500">Kode terusan:</span> <span class="font-mono">#{{ thread.token }}</span></div>
            </div>
            <div v-if="contact" class="o-card space-y-1 p-5 text-sm">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Kontak</div>
                <div>{{ contact.email }}</div>
                <div v-if="contact.phone">{{ contact.phone }}</div>
                <div v-if="contact.segment" class="text-slate-500">Segmen: {{ contact.segment }}</div>
                <p v-if="contact.notes" class="mt-2 whitespace-pre-line text-slate-600">{{ contact.notes }}</p>
            </div>
        </aside>
    </div>
</template>
