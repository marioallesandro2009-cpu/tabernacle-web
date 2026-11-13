<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Modal from '../../../Components/ui/Modal.vue';
import Empty from '../../../Components/ui/Empty.vue';

defineProps({ segments: Array, unassigned: Number });

const open = ref(false);
const editing = ref(null);
const form = useForm({ name: '', description: '' });

function add() {
    editing.value = null;
    form.defaults({ name: '', description: '' }).reset();
    form.clearErrors();
    open.value = true;
}
function edit(s) {
    editing.value = s.id;
    form.defaults({ name: s.name, description: s.description || '' }).reset();
    form.clearErrors();
    open.value = true;
}
function save() {
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) form.put(`/admin/outreach/segments/${editing.value}`, opts);
    else form.post('/admin/outreach/segments', opts);
}
function remove(s) {
    if (confirm(`Hapus segmen "${s.name}"? Kontaknya tidak ikut terhapus, hanya menjadi tanpa segmen.`)) router.delete(`/admin/outreach/segments/${s.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Segmen" />
    <PageHeader title="Segmen" subtitle="Kelompokkan kontak, misalnya Gereja atau Perusahaan. Tiap segmen nanti punya template email sendiri, jadi isi pesannya bisa disesuaikan.">
        <button class="o-btn o-btn-primary" @click="add"><Icon name="plus" class="h-4 w-4" /> Segmen baru</button>
    </PageHeader>

    <div v-if="segments.length || unassigned" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div v-for="s in segments" :key="s.id" class="o-card group flex flex-col p-5">
            <div class="flex items-start justify-between gap-3">
                <Link :href="`/admin/outreach/contacts?segment=${s.id}`" class="min-w-0">
                    <h3 class="truncate text-base font-semibold text-slate-900 group-hover:text-brand">{{ s.name }}</h3>
                    <p class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm text-slate-500">{{ s.description || 'Tanpa deskripsi' }}</p>
                </Link>
                <div class="flex shrink-0 gap-1">
                    <button class="o-btn o-btn-sm o-btn-ghost" aria-label="Ubah" @click="edit(s)"><Icon name="edit" class="h-4 w-4" /></button>
                    <button class="o-btn o-btn-sm o-btn-ghost o-btn-danger" aria-label="Hapus" @click="remove(s)"><Icon name="trash" class="h-4 w-4" /></button>
                </div>
            </div>
            <div class="mt-3 flex items-end justify-between border-t border-slate-100 pt-4">
                <div><div class="text-3xl font-semibold tabular-nums text-slate-900">{{ s.contacts_count }}</div><div class="text-xs text-slate-500">kontak</div></div>
                <div class="text-right"><div class="text-lg font-semibold tabular-nums text-emerald-600">{{ s.sendable_count }}</div><div class="text-xs text-slate-500">siap kirim</div></div>
            </div>
        </div>

        <Link v-if="unassigned" href="/admin/outreach/contacts?segment=none" class="o-card flex flex-col justify-between border-dashed p-5 hover:bg-slate-50">
            <div><h3 class="text-base font-semibold text-slate-700">Tanpa segmen</h3><p class="mt-0.5 text-sm text-slate-500">Kontak yang belum dimasukkan ke segmen mana pun.</p></div>
            <div class="mt-3 border-t border-slate-100 pt-4"><div class="text-3xl font-semibold tabular-nums text-slate-900">{{ unassigned }}</div><div class="text-xs text-slate-500">kontak</div></div>
        </Link>
    </div>
    <div v-else class="o-card">
        <Empty icon="tag" title="Belum ada segmen" text="Segmen bisa dibuat di sini, atau otomatis dari kolom Segmen saat Anda mengimpor file Excel.">
            <button class="o-btn o-btn-primary" @click="add"><Icon name="plus" class="h-4 w-4" /> Buat segmen</button>
            <Link href="/admin/outreach/import" class="o-btn"><Icon name="upload" class="h-4 w-4" /> Impor kontak</Link>
        </Empty>
    </div>

    <Modal :open="open" :title="editing ? 'Ubah segmen' : 'Segmen baru'" size="sm" @close="open = false">
        <form id="segment-form" class="space-y-4" @submit.prevent="save">
            <div><label class="o-label" for="s-name">Nama segmen</label><input id="s-name" v-model="form.name" class="o-input" placeholder="Gereja" autofocus><p v-if="form.errors.name" class="o-err">{{ form.errors.name }}</p></div>
            <div><label class="o-label" for="s-desc">Deskripsi (opsional)</label><textarea id="s-desc" v-model="form.description" rows="3" class="o-input" placeholder="Gereja dan komunitas yang mungkin tertarik tur ziarah" /></div>
        </form>
        <template #footer>
            <button type="button" class="o-btn" @click="open = false">Batal</button>
            <button type="submit" form="segment-form" class="o-btn o-btn-primary" :disabled="form.processing">Simpan</button>
        </template>
    </Modal>
</template>
