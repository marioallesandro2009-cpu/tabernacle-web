<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Icon from '../../../Components/Icon.vue';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';

const props = defineProps({ quote: Object, data: Object, tours: Array, statuses: Object, currencies: Object });

const q = computed(() => props.quote);
const form = useForm({ ...props.data, items: props.data.items.map((i) => ({ ...i })) });
const tone = { draft: 'slate', sent: 'blue', accepted: 'green', rejected: 'red' };

const num = (v) => Number(String(v ?? '').replace(',', '.')) || 0;
const round = (v) => (form.currency === 'IDR' ? Math.round(v) : Math.round(v * 100) / 100);
const money = (v) => (form.currency === 'USD'
    ? 'US$ ' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)
    : 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(v));
const totals = computed(() => {
    const subtotal = round(form.items.reduce((n, i) => n + round(num(i.qty) * num(i.price)), 0));
    const discount = Math.min(subtotal, Math.max(0, round(num(form.discount))));
    const after = subtotal - discount;
    const tax = round((after * num(form.tax_percent)) / 100);
    return { subtotal, discount, after, tax, total: after + tax };
});

const addItem = () => form.items.push({ description: '', qty: 1, unit: 'pax', price: 0 });
const removeItem = (i) => form.items.length > 1 && form.items.splice(i, 1);
const move = (i, d) => { const j = i + d; if (j < 0 || j >= form.items.length) return; const [x] = form.items.splice(i, 1); form.items.splice(j, 0, x); };
const quick = [
    ['Paket tour dewasa', 'pax'], ['Anak (dengan tempat tidur)', 'pax'], ['Tambahan kamar single', 'pax'], ['Visa', 'pax'], ['Tiket pesawat', 'pax'], ['Asuransi perjalanan', 'pax'],
];
const addQuick = ([description, unit]) => form.items.push({ description, qty: num(form.pax) || 1, unit, price: 0 });

// picking a tour fills the title and the first line with its price (the departure on the travel date when known)
function pickTour() {
    const t = props.tours.find((x) => x.id === Number(form.tour_id));
    if (!t) return;
    const dep = form.travel_date ? (t.departures.find((d) => d.date >= form.travel_date) || t.departures.at(-1)) : null;
    const price = dep ? dep.price : t.price;
    form.title = t.name;
    const desc = `Paket ${t.name}${t.duration ? ` (${t.duration})` : ''}`;
    const first = form.items[0];
    if (first && (!first.description || first.description.startsWith('Paket '))) Object.assign(first, { description: desc, price, qty: num(form.pax) || first.qty || 1, unit: first.unit || 'pax' });
    else form.items.unshift({ description: desc, qty: num(form.pax) || 1, unit: 'pax', price });
}

function save() {
    const opts = { preserveScroll: true, onSuccess: () => form.defaults() };
    if (q.value) form.put(`/admin/quotations/${q.value.id}`, opts);
    else form.post('/admin/quotations', opts);
}
const setStatus = (status) => router.post(`/admin/quotations/${q.value.id}/status`, { status }, { preserveScroll: true });
const duplicate = () => router.post(`/admin/quotations/${q.value.id}/duplicate`);
const remove = () => confirm(`Hapus penawaran ${q.value.number}? Riwayat revisinya ikut terhapus.`) && router.delete(`/admin/quotations/${q.value.id}`);
const err = (k) => form.errors[k];
</script>

<template>
    <Head :title="q ? q.number : 'Penawaran baru'" />
    <PageHeader :title="q ? q.number : 'Penawaran baru'" :subtitle="q ? `Penawaran untuk ${data.client_name}` : 'Isi data klien dan item. Nomor penawaran dibuat otomatis saat disimpan.'">
        <Link v-if="q?.lead" :href="`/admin/leads/${q.lead.id}`" class="o-btn"><Icon name="inbox" class="h-4 w-4" /> Lead: {{ q.lead.name }}</Link>
        <Link href="/admin/quotations" class="o-btn">Kembali</Link>
    </PageHeader>

    <div v-if="q && q.status !== 'draft'" class="mb-4 rounded-2xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-100">
        Penawaran ini sudah berstatus <b>{{ statuses[q.status] }}</b>. Kalau Anda mengubah isinya, versi sekarang disimpan di riwayat dan nomornya menjadi revisi berikutnya.
    </div>

    <form class="grid gap-5 xl:grid-cols-[1fr_320px]" @submit.prevent="save">
        <div class="space-y-5">
            <section class="o-card p-5">
                <h2 class="mb-4 text-sm font-semibold text-slate-900">Klien</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="o-label" for="c-name">Nama klien / PIC</label><input id="c-name" v-model="form.client_name" class="o-input" placeholder="Bapak Andreas"><p v-if="err('client_name')" class="o-err">{{ err('client_name') }}</p></div>
                    <div><label class="o-label" for="c-co">Perusahaan / gereja / organisasi</label><input id="c-co" v-model="form.client_company" class="o-input" placeholder="GKI Contoh"></div>
                    <div><label class="o-label" for="c-mail">Email</label><input id="c-mail" v-model="form.client_email" type="email" class="o-input"><p v-if="err('client_email')" class="o-err">{{ err('client_email') }}</p></div>
                    <div><label class="o-label" for="c-ph">Telepon</label><input id="c-ph" v-model="form.client_phone" class="o-input"></div>
                    <div class="sm:col-span-2"><label class="o-label" for="c-addr">Alamat (opsional)</label><textarea id="c-addr" v-model="form.client_address" rows="2" class="o-input" /></div>
                </div>
            </section>

            <section class="o-card p-5">
                <h2 class="mb-4 text-sm font-semibold text-slate-900">Perihal</h2>
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                        <label class="o-label" for="p-tour">Ambil dari paket tour (opsional)</label>
                        <select id="p-tour" v-model="form.tour_id" class="o-input" @change="pickTour"><option :value="null">— tidak dari paket —</option><option v-for="t in tours" :key="t.id" :value="t.id">{{ t.name }}</option></select>
                        <p class="mt-1 text-xs text-slate-500">Judul dan harga baris pertama terisi otomatis, dan tetap bisa diubah.</p>
                    </div>
                    <div class="sm:col-span-6"><label class="o-label" for="p-title">Judul penawaran</label><input id="p-title" v-model="form.title" class="o-input" placeholder="Tour Ziarah Turki 9 Hari"><p v-if="err('title')" class="o-err">{{ err('title') }}</p></div>
                    <div class="sm:col-span-2"><label class="o-label" for="p-date">Tanggal berangkat</label><input id="p-date" v-model="form.travel_date" type="date" class="o-input"></div>
                    <div class="sm:col-span-2"><label class="o-label" for="p-pax">Jumlah peserta</label><input id="p-pax" v-model="form.pax" type="number" min="1" class="o-input"></div>
                    <div class="sm:col-span-2"><label class="o-label" for="p-cur">Mata uang</label><select id="p-cur" v-model="form.currency" class="o-input"><option v-for="(l, k) in currencies" :key="k" :value="k">{{ l }}</option></select></div>
                </div>
            </section>

            <section class="o-card p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-900">Item</h2>
                    <div class="flex flex-wrap gap-1.5"><button v-for="x in quick" :key="x[0]" type="button" class="o-btn o-btn-sm o-btn-ghost" @click="addQuick(x)">+ {{ x[0] }}</button></div>
                </div>
                <div class="space-y-2">
                    <div v-for="(it, i) in form.items" :key="i" class="grid gap-2 rounded-2xl bg-slate-50 p-3 sm:grid-cols-[1fr_80px_80px_150px_auto]">
                        <div><label class="o-label sm:sr-only" :for="`i-d-${i}`">Deskripsi</label><textarea :id="`i-d-${i}`" v-model="it.description" rows="1" class="o-input min-h-[40px]" placeholder="Deskripsi" /><p v-if="err(`items.${i}.description`)" class="o-err">{{ err(`items.${i}.description`) }}</p></div>
                        <div><label class="o-label sm:sr-only" :for="`i-q-${i}`">Jumlah</label><input :id="`i-q-${i}`" v-model="it.qty" type="number" step="any" min="0" class="o-input" placeholder="Jml"></div>
                        <div><label class="o-label sm:sr-only" :for="`i-u-${i}`">Satuan</label><input :id="`i-u-${i}`" v-model="it.unit" class="o-input" placeholder="pax"></div>
                        <div><label class="o-label sm:sr-only" :for="`i-p-${i}`">Harga satuan</label><input :id="`i-p-${i}`" v-model="it.price" type="number" step="any" class="o-input text-right" placeholder="Harga"><div class="mt-1 text-right text-xs tabular-nums text-slate-500">= {{ money(round(num(it.qty) * num(it.price))) }}</div></div>
                        <div class="flex items-start gap-1">
                            <button type="button" class="o-btn o-btn-sm o-btn-ghost" aria-label="Naik" @click="move(i, -1)"><Icon name="up" class="h-4 w-4" /></button>
                            <button type="button" class="o-btn o-btn-sm o-btn-ghost" aria-label="Turun" @click="move(i, 1)"><Icon name="down" class="h-4 w-4" /></button>
                            <button type="button" class="o-btn o-btn-sm o-btn-ghost o-btn-danger" aria-label="Hapus baris" :disabled="form.items.length < 2" @click="removeItem(i)"><Icon name="trash" class="h-4 w-4" /></button>
                        </div>
                    </div>
                </div>
                <p v-if="err('items')" class="o-err mt-2">{{ err('items') }}</p>
                <button type="button" class="o-btn o-btn-sm mt-3" @click="addItem"><Icon name="plus" class="h-3.5 w-3.5" /> Tambah baris</button>

                <div class="mt-5 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-3">
                    <div><label class="o-label" for="t-disc">Diskon (nominal)</label><input id="t-disc" v-model="form.discount" type="number" step="any" min="0" class="o-input"></div>
                    <div><label class="o-label" for="t-tl">Nama pajak</label><input id="t-tl" v-model="form.tax_label" class="o-input" placeholder="PPN"></div>
                    <div><label class="o-label" for="t-tp">Pajak (%)</label><input id="t-tp" v-model="form.tax_percent" type="number" step="any" min="0" max="100" class="o-input"><p class="mt-1 text-xs text-slate-500">Isi 0 kalau tidak ada pajak.</p></div>
                </div>
            </section>

            <section class="o-card p-5">
                <h2 class="mb-4 text-sm font-semibold text-slate-900">Catatan, syarat, dan tanda tangan</h2>
                <div class="grid gap-4">
                    <div><label class="o-label" for="n-notes">Catatan untuk klien (opsional)</label><textarea id="n-notes" v-model="form.notes" rows="3" class="o-input" placeholder="Harga sudah termasuk… / Belum termasuk…" /></div>
                    <div><label class="o-label" for="n-terms">Syarat &amp; ketentuan</label><textarea id="n-terms" v-model="form.terms" rows="5" class="o-input" /><p class="mt-1 text-xs text-slate-500">Isi bawaannya bisa diatur di Pengaturan → Penawaran harga.</p></div>
                    <div><label class="o-label" for="n-pay">Informasi pembayaran</label><textarea id="n-pay" v-model="form.payment_info" rows="3" class="o-input" placeholder="Transfer ke rekening …" /></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="o-label" for="n-sn">Nama penanda tangan</label><input id="n-sn" v-model="form.signatory_name" class="o-input"></div>
                        <div><label class="o-label" for="n-st">Jabatan</label><input id="n-st" v-model="form.signatory_title" class="o-input" placeholder="Direktur"></div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
            <div class="o-card space-y-4 p-5">
                <div v-if="q" class="flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Status</span><Chip :tone="tone[q.status]">{{ statuses[q.status] }}</Chip></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="o-label" for="d-iss">Tanggal</label><input id="d-iss" v-model="form.issued_on" type="date" class="o-input"></div>
                    <div><label class="o-label" for="d-val">Berlaku sampai</label><input id="d-val" v-model="form.valid_until" type="date" class="o-input"></div>
                </div>
                <p v-if="err('valid_until')" class="o-err">{{ err('valid_until') }}</p>

                <dl class="space-y-1.5 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular-nums">{{ money(totals.subtotal) }}</dd></div>
                    <div v-if="totals.discount" class="flex justify-between"><dt class="text-slate-500">Diskon</dt><dd class="tabular-nums">− {{ money(totals.discount) }}</dd></div>
                    <div v-if="num(form.tax_percent)" class="flex justify-between"><dt class="text-slate-500">{{ form.tax_label || 'Pajak' }} {{ num(form.tax_percent) }}%</dt><dd class="tabular-nums">{{ money(totals.tax) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-semibold text-slate-900"><dt>Total</dt><dd class="tabular-nums">{{ money(totals.total) }}</dd></div>
                </dl>

                <button class="o-btn o-btn-primary w-full" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : q ? 'Simpan perubahan' : 'Simpan penawaran' }}</button>
                <template v-if="q">
                    <p v-if="form.isDirty" class="text-center text-xs text-amber-600">Ada perubahan yang belum disimpan. Simpan dulu sebelum membuka PDF.</p>
                    <div class="grid grid-cols-2 gap-2">
                        <a :href="`/admin/quotations/${q.id}/pdf`" target="_blank" rel="noopener" class="o-btn justify-center" :class="{ 'pointer-events-none opacity-50': form.isDirty }"><Icon name="eye" class="h-4 w-4" /> Pratinjau</a>
                        <a :href="`/admin/quotations/${q.id}/pdf?download=1`" class="o-btn justify-center" :class="{ 'pointer-events-none opacity-50': form.isDirty }"><Icon name="download" class="h-4 w-4" /> Unduh PDF</a>
                    </div>
                </template>
            </div>

            <div v-if="q" class="o-card space-y-3 p-5">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tandai sebagai</div>
                <div class="flex flex-wrap gap-2">
                    <button v-for="(label, key) in statuses" :key="key" type="button" class="o-btn o-btn-sm" :class="{ 'o-btn-primary': q.status === key }" :disabled="q.status === key" @click="setStatus(key)">{{ label }}</button>
                </div>
                <p v-if="q.sent_at" class="text-xs text-slate-500">Dikirim {{ q.sent_at }}</p>
                <div class="flex gap-2 border-t border-slate-100 pt-3">
                    <button type="button" class="o-btn o-btn-sm" @click="duplicate"><Icon name="doc" class="h-3.5 w-3.5" /> Duplikat</button>
                    <button type="button" class="o-btn o-btn-sm o-btn-ghost o-btn-danger ml-auto" @click="remove"><Icon name="trash" class="h-3.5 w-3.5" /> Hapus</button>
                </div>
            </div>

            <div v-if="q?.revisions?.length" class="o-card p-5">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Riwayat revisi</div>
                <ul class="space-y-2 text-sm">
                    <li v-for="r in q.revisions" :key="r.revision" class="flex items-center gap-2">
                        <span class="font-medium text-slate-800">{{ r.label }}</span>
                        <span class="truncate text-xs text-slate-500">{{ r.at }}<span v-if="r.by"> · {{ r.by }}</span></span>
                        <a :href="`/admin/quotations/${q.id}/revisions/${r.revision}/pdf`" target="_blank" rel="noopener" class="ml-auto text-slate-500 hover:text-brand" aria-label="Lihat PDF"><Icon name="eye" class="h-4 w-4" /></a>
                    </li>
                </ul>
            </div>
        </aside>
    </form>
</template>
