<script setup>
import { au } from '../../lib/admin';
import { onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Chip from '../ui/Chip.vue';
import Icon from '../Icon.vue';
import { replyStatus } from '../../lib/outreach';

// One conversation: every email in order, status badges on our own replies, and a reply box that saves itself.
const props = defineProps({ thread: { type: Object, required: true }, statuses: { type: Object, required: true } });

const body = ref(props.thread.draft?.body || '');
const draftId = ref(props.thread.draft?.id || null);
const savedAt = ref(null);
const saving = ref(false);
const sending = ref(false);
const editing = ref({}); // draft id → text, for drafts that came from a personal mailbox
const openLog = ref({});

let timer = null;
watch(body, () => {
    clearTimeout(timer);
    timer = setTimeout(save, 1200);
});
onBeforeUnmount(() => clearTimeout(timer));

async function save() {
    if (!props.thread.can_reply || (!body.value.trim() && !draftId.value)) return;
    saving.value = true;
    try {
        const { data } = await axios.put(au(`/admin/outreach/threads/${props.thread.id}/draft`), { body: body.value, draft_id: draftId.value });
        draftId.value = data.id;
        savedAt.value = data.saved_at;
    } catch {
        savedAt.value = null;
    } finally {
        saving.value = false;
    }
}
function send() {
    if (!body.value.trim()) return;
    clearTimeout(timer);
    sending.value = true;
    router.post(au(`/admin/outreach/threads/${props.thread.id}/send`), { body: body.value, draft_id: draftId.value }, {
        preserveScroll: true,
        onSuccess: () => { body.value = ''; draftId.value = null; savedAt.value = null; },
        onFinish: () => (sending.value = false),
    });
}
const startEdit = (m) => (editing.value[m.id] = m.body || '');
const sendDraft = (m) => router.post(au(`/admin/outreach/manual-replies/${m.id}/send`), { body: editing.value[m.id] ?? m.body }, { preserveScroll: true, onSuccess: () => delete editing.value[m.id] });
const retry = (m) => router.post(au(`/admin/outreach/manual-replies/${m.id}/retry`), {}, { preserveScroll: true });
const discard = (m) => confirm('Hapus draf ini?') && router.delete(au(`/admin/outreach/manual-replies/${m.id}`), { preserveScroll: true });

const who = (m) => {
    if (m.direction === 'in') return m.kind === 'bounce' ? 'Laporan bounce' : m.kind === 'auto_reply' ? 'Balasan otomatis' : 'Prospek';
    if (m.kind === 'reply_manual') return `Balasan kita${m.by ? ' · ' + m.by : m.relay_from ? ' · ' + m.relay_from : ''}`;
    return `Email kampanye${m.step ? ' (langkah ' + m.step + ')' : ''}`;
};
</script>

<template>
    <div>
        <div class="space-y-3">
            <div v-for="m in thread.messages" :key="m.id" class="rounded-2xl p-4 text-sm ring-1"
                :class="m.direction === 'out' ? (m.kind === 'reply_manual' ? 'ml-6 bg-emerald-50/60 ring-emerald-100' : 'ml-6 bg-brand/[0.04] ring-brand/15') : 'mr-6 bg-white ring-slate-200'">
                <div class="mb-1.5 flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-semibold" :class="m.direction === 'out' ? 'text-brand' : 'text-slate-700'">{{ who(m) }}</span>
                    <Chip v-if="m.reply_status" :tone="replyStatus[m.reply_status]?.tone">{{ statuses[m.reply_status] || m.reply_status }}</Chip>
                    <Chip v-if="m.source_label" tone="slate" :dot="false">{{ m.source_label }}</Chip>
                    <Chip v-if="m.folder && m.folder !== 'INBOX'" tone="amber" :dot="false">di folder {{ m.folder }}</Chip>
                    <span class="ml-auto text-slate-400">{{ m.at }}</span>
                </div>
                <div v-if="m.subject" class="font-medium text-slate-800">{{ m.subject }}</div>
                <p v-if="m.reason" class="mt-1 rounded-lg bg-rose-50 px-2 py-1 text-xs text-rose-700">{{ m.reason }}</p>

                <div v-if="m.reply_status === 'draft' && editing[m.id] !== undefined" class="mt-2">
                    <textarea v-model="editing[m.id]" rows="6" class="o-input" />
                    <div class="mt-2 flex gap-2"><button class="o-btn o-btn-sm o-btn-primary" @click="sendDraft(m)">Kirim</button><button class="o-btn o-btn-sm" @click="delete editing[m.id]">Batal</button></div>
                </div>
                <div v-else class="mt-1 whitespace-pre-line text-slate-700">{{ m.body }}</div>

                <div v-if="m.kind === 'reply_manual'" class="mt-2 flex flex-wrap items-center gap-2">
                    <template v-if="m.reply_status === 'draft' && editing[m.id] === undefined">
                        <button class="o-btn o-btn-sm o-btn-primary" @click="startEdit(m)"><Icon name="edit" class="h-3.5 w-3.5" /> Tinjau &amp; kirim</button>
                        <button class="o-btn o-btn-sm o-btn-ghost o-btn-danger" @click="discard(m)">Hapus draf</button>
                    </template>
                    <button v-if="m.reply_status === 'failed'" class="o-btn o-btn-sm o-btn-primary" @click="retry(m)">Kirim ulang</button>
                    <button v-if="m.log?.length" class="text-xs text-slate-500 underline-offset-2 hover:underline" @click="openLog[m.id] = !openLog[m.id]">Riwayat status</button>
                </div>
                <ol v-if="openLog[m.id]" class="mt-2 space-y-0.5 border-l-2 border-slate-200 pl-3 text-xs text-slate-500">
                    <li v-for="(l, i) in m.log" :key="i">{{ l.at }} · {{ statuses[l.status] || l.status }}<span v-if="l.reason"> — {{ l.reason }}</span></li>
                </ol>
            </div>
            <p v-if="!thread.messages.length" class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada pesan di percakapan ini.</p>
        </div>

        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <label :for="`reply-${thread.id}`" class="text-sm font-semibold text-slate-800">Balas dari website</label>
                <span class="text-xs text-slate-400">{{ saving ? 'Menyimpan draf…' : savedAt ? 'Draf tersimpan ' + savedAt : '' }}</span>
            </div>
            <template v-if="thread.can_reply">
                <p class="mb-2 text-xs text-slate-500">Dikirim dari {{ thread.account?.email }} dengan subjek "{{ thread.subject }}", di percakapan yang sama. Teks biasa, tanpa footer berhenti berlangganan.</p>
                <textarea :id="`reply-${thread.id}`" v-model="body" rows="6" class="o-input" placeholder="Tulis balasan…" />
                <div class="mt-2 flex justify-end"><button class="o-btn o-btn-primary" :disabled="sending || !body.trim()" @click="send">{{ sending ? 'Mengirim…' : 'Kirim' }}</button></div>
            </template>
            <p v-else class="text-sm text-amber-700">Tidak bisa membalas: akun pengirim percakapan ini nonaktif atau sudah dihapus, atau kontak tidak punya email.</p>
        </div>
    </div>
</template>
