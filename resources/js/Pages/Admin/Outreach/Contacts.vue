<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import Pagination from '../../../Components/Pagination.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import StatCard from '../../../Components/ui/StatCard.vue';
import Chip from '../../../Components/ui/Chip.vue';
import Modal from '../../../Components/ui/Modal.vue';
import Empty from '../../../Components/ui/Empty.vue';
import { contactStatus, verifyStatus } from '../../../lib/outreach';

const props = defineProps({ contacts: Object, filters: Object, segments: Array, organizations: Array, counts: Object, recentImports: Array, verifier: String, verifyError: String });

const f = reactive({ q: '', segment: '', status: '', org: '', verify: '', role: '', ...props.filters });
const verifyOne = (c) => router.post('/admin/outreach/contacts/verify', { contact_id: c.id }, { preserveScroll: true });
const selected = ref([]);
const bulkSegment = ref('');
const newSegment = ref('');

const params = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v != null));
const apply = () => router.get('/admin/outreach/contacts', params(), { preserveState: true, replace: true });
let typing;
watch(() => f.q, () => { clearTimeout(typing); typing = setTimeout(apply, 350); });
const reset = () => { Object.keys(f).forEach((k) => (f[k] = '')); apply(); };
const filtered = computed(() => Object.values(f).some((v) => v !== '' && v != null));

const allOnPage = computed(() => props.contacts.data.length > 0 && props.contacts.data.every((c) => selected.value.includes(c.id)));
const toggleAll = () => (selected.value = allOnPage.value ? [] : props.contacts.data.map((c) => c.id));

function bulk(action) {
    if (!selected.value.length) return;
    if (action === 'delete' && !confirm(`Hapus ${selected.value.length} kontak? Tidak bisa dibatalkan.`)) return;
    if (action === 'suppress' && !confirm(`Tandai ${selected.value.length} kontak sebagai "jangan dikirimi"? Mereka tidak akan menerima kampanye apa pun.`)) return;
    const body = { ids: selected.value, action };
    if (action === 'move') {
        body.segment_id = bulkSegment.value && bulkSegment.value !== 'none' ? bulkSegment.value : null;
        body.new_segment = newSegment.value || null;
    }
    router.post('/admin/outreach/contacts/bulk', body, { preserveScroll: true, onSuccess: () => { selected.value = []; newSegment.value = ''; bulkSegment.value = ''; } });
}

// contact form
const open = ref(false);
const editing = ref(null);
const blank = () => ({ name: '', title: '', organization: '', email: '', phone: '', segment_id: '', notes: '' });
const form = useForm(blank());
function add() { editing.value = null; form.defaults(blank()).reset(); form.clearErrors(); open.value = true; }
function edit(c) {
    editing.value = c.id;
    form.defaults({ name: c.name || '', title: c.title || '', organization: c.organization || '', email: c.email || '', phone: c.phone || '', segment_id: c.segment_id || '', notes: c.notes || '' }).reset();
    form.clearErrors();
    open.value = true;
}
function save() {
    form.transform((d) => ({ ...d, segment_id: d.segment_id || null }));
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) form.put(`/admin/outreach/contacts/${editing.value}`, opts);
    else form.post('/admin/outreach/contacts', opts);
}
function remove(c) {
    if (confirm(`Hapus ${c.name || c.email}?`)) router.delete(`/admin/outreach/contacts/${c.id}`, { preserveScroll: true });
}

// while addresses are still being checked, refresh the numbers by themselves
let poll;
onMounted(() => {
    poll = setInterval(() => { if (props.counts.pending > 0) router.reload({ only: ['contacts', 'counts'], preserveScroll: true }); }, 4000);
});
onBeforeUnmount(() => clearInterval(poll));
</script>

<template>
    <Head title="Kontak Outreach" />
    <PageHeader title="Kontak" subtitle="Daftar calon penerima email. Alamat dicek otomatis: yang kosong, salah format, atau domainnya tidak bisa menerima email tidak akan pernah dikirimi.">
        <Link href="/admin/outreach/import" class="o-btn"><Icon name="upload" class="h-4 w-4" /> Impor Excel / CSV</Link>
        <button class="o-btn o-btn-primary" @click="add"><Icon name="plus" class="h-4 w-4" /> Tambah kontak</button>
    </PageHeader>

    <div v-if="verifyError" class="mb-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-100">Layanan verifikasi berhenti di tengah jalan: {{ verifyError }} Hasil cek dasar tetap tersimpan.</div>
    <p class="mb-3 text-xs text-slate-500">Verifikasi: {{ verifier ? 'format, domain, MX, email sekali pakai, alamat peran, plus layanan ' + verifier : 'format, domain, MX, email sekali pakai, dan alamat peran (layanan verifikasi belum diatur di Pengaturan)' }}. Server ini tidak pernah mengetes kotak email penerima secara langsung.</p>
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Total kontak" :value="counts.total" />
        <StatCard label="Siap kirim" :value="counts.sendable" tone="green" hint="Email valid dan tidak berhenti" />
        <StatCard label="Perlu dicek" :value="counts.attention" tone="amber" hint="Kosong, salah, atau domain tak aktif" />
        <StatCard label="Jangan dikirimi" :value="counts.suppressed" tone="violet" hint="Berhenti langganan atau bounce" />
    </div>

    <div v-if="counts.pending > 0" class="mb-4 flex items-center gap-3 rounded-2xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-100">
        <svg class="h-4 w-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        Sedang mengecek {{ counts.pending }} alamat email. Angkanya akan berubah sendiri.
    </div>

    <div class="o-card mb-4 p-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr_1fr_auto]">
            <div class="relative">
                <Icon name="search" class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                <input v-model="f.q" class="o-input pl-9" placeholder="Cari nama, email, organisasi…" aria-label="Cari" @keydown.enter.prevent="apply">
            </div>
            <select v-model="f.segment" class="o-input" aria-label="Segmen" @change="apply"><option value="">Semua segmen</option><option value="none">Tanpa segmen</option><option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }}</option></select>
            <select v-model="f.status" class="o-input" aria-label="Status" @change="apply">
                <option value="">Semua status</option><option value="sendable">Siap kirim</option><option value="pending">Sedang dicek</option><option value="invalid_format">Format salah</option><option value="no_mx">Domain tak aktif</option><option value="empty">Tanpa email</option><option value="disposable">Email sekali pakai</option><option value="suppressed">Jangan dikirimi</option>
            </select>
            <select v-model="f.verify" class="o-input" aria-label="Hasil verifikasi" @change="apply">
                <option value="">Semua hasil verifikasi</option><option value="valid">Terverifikasi valid</option><option value="catch_all">Catch-all</option><option value="unknown">Tidak pasti</option><option value="invalid">Tidak valid</option><option value="disposable">Sekali pakai</option><option value="none">Belum diverifikasi</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input v-model="f.role" type="checkbox" true-value="1" false-value="" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand" @change="apply"> Alamat peran ({{ counts.role }})</label>
            <select v-model="f.org" class="o-input" aria-label="Organisasi" @change="apply"><option value="">Semua organisasi</option><option v-for="o in organizations" :key="o" :value="o">{{ o }}</option></select>
            <button v-if="filtered" class="o-btn" @click="reset"><Icon name="x" class="h-4 w-4" /> Reset</button>
        </div>
    </div>

    <Transition name="fade">
        <div v-if="selected.length" class="o-card mb-4 flex flex-wrap items-center gap-2 bg-brand/[0.04] p-3 ring-brand/20">
            <span class="px-2 text-sm font-semibold text-brand">{{ selected.length }} dipilih</span>
            <select v-model="bulkSegment" class="o-input !w-auto" aria-label="Pindahkan ke segmen"><option value="">Pindahkan ke segmen…</option><option value="none">Tanpa segmen</option><option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }}</option></select>
            <input v-model="newSegment" class="o-input !w-44" placeholder="atau segmen baru…" aria-label="Nama segmen baru">
            <button class="o-btn o-btn-sm" :disabled="!bulkSegment && !newSegment" @click="bulk('move')">Pindahkan</button>
            <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:block" />
            <button class="o-btn o-btn-sm" @click="bulk('verify')">Verifikasi ulang</button>
            <button class="o-btn o-btn-sm" @click="bulk('suppress')">Jangan dikirimi</button>
            <button class="o-btn o-btn-sm o-btn-danger" @click="bulk('delete')"><Icon name="trash" class="h-3.5 w-3.5" /> Hapus</button>
            <button class="o-btn o-btn-sm o-btn-ghost ml-auto" @click="selected = []">Batal pilih</button>
        </div>
    </Transition>

    <div class="o-card overflow-hidden">
        <div v-if="contacts.data.length" class="overflow-x-auto">
            <table class="w-full min-w-[820px]">
                <thead class="border-b border-slate-100 bg-slate-50/70">
                    <tr>
                        <th class="o-th w-10"><input type="checkbox" :checked="allOnPage" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand" aria-label="Pilih semua di halaman" @change="toggleAll"></th>
                        <th class="o-th">Kontak</th><th class="o-th">Organisasi</th><th class="o-th">Email</th><th class="o-th">Segmen</th><th class="o-th">Status</th><th class="o-th w-12" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in contacts.data" :key="c.id" class="hover:bg-slate-50/60" :class="{ 'bg-brand/[0.03]': selected.includes(c.id) }">
                        <td class="o-td"><input v-model="selected" type="checkbox" :value="c.id" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand" :aria-label="`Pilih ${c.name || c.email}`"></td>
                        <td class="o-td"><Link :href="`/admin/outreach/contacts/${c.id}`" class="font-medium text-slate-900 hover:text-brand hover:underline">{{ c.name || c.email || '—' }}</Link><div v-if="c.title" class="text-xs text-slate-500">{{ c.title }}</div></td>
                        <td class="o-td text-slate-600">{{ c.organization || '—' }}</td>
                        <td class="o-td"><div class="text-slate-700">{{ c.email || '—' }}</div><div v-if="c.phone" class="text-xs text-slate-400">{{ c.phone }}</div></td>
                        <td class="o-td"><span v-if="c.segment" class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ c.segment }}</span><span v-else class="text-xs text-slate-400">—</span></td>
                        <td class="o-td"><Chip :tone="contactStatus[c.status]?.tone">{{ contactStatus[c.status]?.label || c.status }}</Chip>
                            <div class="mt-1 flex flex-wrap gap-1"><Chip v-if="c.verify_status" :tone="verifyStatus[c.verify_status]?.tone" :dot="false" :title="c.verify_note">{{ verifyStatus[c.verify_status]?.label }}</Chip><Chip v-if="c.is_role" tone="amber" :dot="false" title="Alamat bersama seperti info@ atau sales@: boleh dikirimi, tapi biasanya lebih jarang dibalas">Alamat peran</Chip></div>
                            <div v-if="c.note && c.status !== 'valid'" class="mt-1 text-[11px] text-slate-400">{{ c.note }}</div></td>
                        <td class="o-td text-right">
                            <button class="o-btn o-btn-sm o-btn-ghost" aria-label="Verifikasi ulang" title="Verifikasi ulang" :disabled="!c.email" @click="verifyOne(c)"><Icon name="check" class="h-4 w-4" /></button>
                            <button class="o-btn o-btn-sm o-btn-ghost" aria-label="Ubah kontak" @click="edit(c)"><Icon name="edit" class="h-4 w-4" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Empty v-else-if="filtered" icon="search" title="Tidak ada kontak yang cocok" text="Coba ubah kata kunci atau hapus filter.">
            <button class="o-btn" @click="reset">Reset filter</button>
        </Empty>
        <Empty v-else icon="users" title="Belum ada kontak" text="Impor dari file Excel atau CSV, atau tambahkan satu per satu.">
            <Link href="/admin/outreach/import" class="o-btn o-btn-primary"><Icon name="upload" class="h-4 w-4" /> Impor Excel / CSV</Link>
            <button class="o-btn" @click="add"><Icon name="plus" class="h-4 w-4" /> Tambah kontak</button>
        </Empty>
        <div v-if="contacts.data.length" class="flex items-center justify-between border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
            <span>Menampilkan {{ contacts.from }}–{{ contacts.to }} dari {{ contacts.total }}</span>
            <Pagination :links="contacts.links" />
        </div>
    </div>

    <div v-if="recentImports.length" class="mt-6">
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Impor terakhir</h2>
        <div class="o-card divide-y divide-slate-100">
            <Link v-for="i in recentImports" :key="i.id" :href="`/admin/outreach/import/${i.id}`" class="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-slate-50">
                <span class="min-w-0 truncate font-medium text-slate-700">{{ i.filename }}</span>
                <span class="shrink-0 text-xs text-slate-500">{{ i.created }} baru · {{ i.duplicates }} dobel · {{ i.at }}</span>
            </Link>
        </div>
    </div>

    <Modal :open="open" :title="editing ? 'Ubah kontak' : 'Tambah kontak'" @close="open = false">
        <form id="contact-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
            <div><label class="o-label" for="c-name">Nama</label><input id="c-name" v-model="form.name" class="o-input"><p v-if="form.errors.name" class="o-err">{{ form.errors.name }}</p></div>
            <div><label class="o-label" for="c-title">Jabatan</label><input id="c-title" v-model="form.title" class="o-input"></div>
            <div><label class="o-label" for="c-org">Organisasi</label><input id="c-org" v-model="form.organization" class="o-input"></div>
            <div><label class="o-label" for="c-seg">Segmen</label><select id="c-seg" v-model="form.segment_id" class="o-input"><option value="">Tanpa segmen</option><option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
            <div><label class="o-label" for="c-email">Email</label><input id="c-email" v-model="form.email" class="o-input" autocomplete="off"><p v-if="form.errors.email" class="o-err">{{ form.errors.email }}</p></div>
            <div><label class="o-label" for="c-phone">Telepon</label><input id="c-phone" v-model="form.phone" class="o-input"></div>
            <div class="sm:col-span-2"><label class="o-label" for="c-notes">Catatan</label><textarea id="c-notes" v-model="form.notes" rows="3" class="o-input" /></div>
        </form>
        <template #footer>
            <button v-if="editing" type="button" class="o-btn o-btn-danger mr-auto" @click="open = false; remove({ id: editing, name: form.name, email: form.email })"><Icon name="trash" class="h-4 w-4" /> Hapus</button>
            <button type="button" class="o-btn" @click="open = false">Batal</button>
            <button type="submit" form="contact-form" class="o-btn o-btn-primary" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : 'Simpan' }}</button>
        </template>
    </Modal>
</template>
