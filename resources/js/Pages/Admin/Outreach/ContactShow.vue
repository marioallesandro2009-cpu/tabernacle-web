<script setup>
import { au } from '../../../lib/admin';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Empty from '../../../Components/ui/Empty.vue';
import ThreadView from '../../../Components/outreach/ThreadView.vue';
import { sendStatus } from '../../../lib/outreach';

const props = defineProps({ contact: Object, threads: Array, statuses: Object, filter: String });
const setFilter = (s) => router.get(au(`/admin/outreach/contacts/${props.contact.id}`), s ? { status: s } : {}, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <Head :title="contact.name || contact.email" />
    <PageHeader :title="contact.name || contact.email || 'Kontak'" :subtitle="[contact.title, contact.organization, contact.segment && 'Segmen ' + contact.segment].filter(Boolean).join(' · ')">
        <Link :href="au('/admin/outreach/contacts')" class="o-btn">Semua kontak</Link>
    </PageHeader>

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-slate-500">{{ contact.email }}<span v-if="contact.phone"> · {{ contact.phone }}</span></span>
        <span class="flex-1" />
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Balasan kita:</span>
        <button class="o-btn o-btn-sm" :class="{ 'o-btn-primary': !filter }" @click="setFilter('')">Semua pesan</button>
        <button v-for="(label, key) in statuses" :key="key" class="o-btn o-btn-sm" :class="{ 'o-btn-primary': filter === key }" @click="setFilter(key)">{{ label }}</button>
    </div>

    <div v-if="threads.length" class="space-y-5">
        <section v-for="t in threads" :key="t.id" class="o-card p-5">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <h2 class="text-base font-semibold text-slate-900">{{ t.campaign || 'Percakapan' }}</h2>
                <Chip :tone="sendStatus[t.status]?.tone">{{ sendStatus[t.status]?.label || t.status }}</Chip>
                <span class="text-xs text-slate-500">via {{ t.account?.email || 'akun dihapus' }}</span>
                <Link :href="au(`/admin/outreach/threads/${t.id}`)" class="ml-auto text-sm text-brand hover:underline">Buka percakapan</Link>
            </div>
            <ThreadView :thread="t" :statuses="statuses" />
        </section>
    </div>
    <div v-else class="o-card"><Empty icon="mail" title="Belum ada percakapan" text="Kontak ini belum masuk kampanye mana pun." /></div>
</template>
