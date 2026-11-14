<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../../Components/ui/PageHeader.vue';
import Chip from '../../../Components/ui/Chip.vue';

const props = defineProps({ campaign: Object, segments: Array, accounts: Array, templates: Array, weekdays: Array });

const c = props.campaign;
const form = useForm({
    name: c?.name || '',
    subject_b: c?.subject_b || '',
    segment_id: c?.segment_id || '',
    account_ids: c?.account_ids || props.accounts.filter((a) => a.is_active).slice(0, 1).map((a) => a.id),
    window_start: c?.window_start || '08:00',
    window_end: c?.window_end || '17:00',
    send_days: c?.send_days || [1, 2, 3, 4, 5],
    gap_min: c?.gap_min || 1,
    gap_max: c?.gap_max || 4,
    steps: c?.steps || [{ template_id: null, day_offset: 0 }, { template_id: null, day_offset: 4 }, { template_id: null, day_offset: 10 }],
});

const segment = computed(() => props.segments.find((s) => String(s.id) === String(form.segment_id)));
const audience = computed(() => (segment.value ? segment.value.sendable_count : props.segments.reduce((n, s) => n + s.sendable_count, 0)));
// templates written for the chosen segment first, then the general ones, then the rest
const ordered = computed(() => {
    const id = form.segment_id ? Number(form.segment_id) : null;
    const rank = (t) => (id && t.segment_id === id ? 0 : t.segment_id === null ? 1 : 2);

    return [...props.templates].sort((a, b) => rank(a) - rank(b) || a.name.localeCompare(b.name));
});
const toggle = (list, v) => { const i = list.indexOf(v); if (i === -1) list.push(v); else list.splice(i, 1); };
const label = (t) => `${t.name}${t.segment ? ` (${t.segment})` : ' (umum)'}`;

function save() {
    form.transform((d) => ({ ...d, segment_id: d.segment_id || null, steps: d.steps.map((s, i) => ({ template_id: s.template_id || null, day_offset: i === 0 ? 0 : Number(s.day_offset) })) }));
    if (c) form.put(`/admin/outreach/campaigns/${c.id}`);
    else form.post('/admin/outreach/campaigns');
}
</script>

<template>
    <Head :title="c ? 'Ubah kampanye' : 'Kampanye baru'" />
    <PageHeader :title="c ? 'Ubah kampanye' : 'Kampanye baru'" subtitle="Kampanye belum mengirim apa pun sampai Anda menekan Mulai di halaman berikutnya.">
        <Link :href="c ? `/admin/outreach/campaigns/${c.id}` : '/admin/outreach/campaigns'" class="o-btn">Batal</Link>
    </PageHeader>

    <form class="grid gap-5 xl:grid-cols-[1fr_300px]" @submit.prevent="save">
        <div class="space-y-5">
            <section class="o-card p-5">
                <h2 class="mb-4 text-sm font-semibold text-slate-900">1. Nama dan sasaran</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="o-label" for="f-name">Nama kampanye</label><input id="f-name" v-model="form.name" class="o-input" placeholder="Tur Turki, gereja Jabodetabek"><p v-if="form.errors.name" class="o-err">{{ form.errors.name }}</p></div>
                    <div><label class="o-label" for="f-seg">Segmen sasaran</label><select id="f-seg" v-model="form.segment_id" class="o-input"><option value="">Semua segmen</option><option v-for="s in segments" :key="s.id" :value="s.id">{{ s.name }} ({{ s.sendable_count }} siap kirim)</option></select></div>
                </div>
            </section>

            <section class="o-card p-5">
                <h2 class="mb-1 text-sm font-semibold text-slate-900">2. Email dan follow-up</h2>
                <p class="mb-4 text-sm text-slate-500">Email pertama dikirim di hari 0. Follow-up berhenti otomatis kalau orangnya membalas, bounce, atau berhenti berlangganan.</p>
                <div class="space-y-3">
                    <div v-for="(s, i) in form.steps" :key="i" class="grid items-end gap-3 rounded-2xl bg-slate-50 p-3 sm:grid-cols-[110px_1fr_140px]">
                        <div class="pb-2 text-sm font-semibold text-slate-700">{{ i === 0 ? 'Email pertama' : `Follow-up ${i}` }}<div v-if="i > 0" class="text-xs font-normal text-slate-400">opsional</div></div>
                        <div>
                            <label class="o-label" :for="`s-t-${i}`">Template</label>
                            <select :id="`s-t-${i}`" v-model="s.template_id" class="o-input">
                                <option :value="null">{{ i === 0 ? '— pilih template —' : '— tidak dipakai —' }}</option>
                                <option v-for="t in ordered" :key="t.id" :value="t.id">{{ label(t) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="o-label" :for="`s-d-${i}`">Dikirim pada hari ke-</label>
                            <input :id="`s-d-${i}`" v-model="s.day_offset" type="number" min="0" max="120" class="o-input" :disabled="i === 0">
                        </div>
                    </div>
                </div>
                <p v-if="form.errors.steps" class="o-err mt-2">{{ form.errors.steps }}</p>
                <div class="mt-4 rounded-2xl border border-dashed border-slate-200 p-4">
                    <label class="o-label" for="f-subb">Uji A/B: subjek kedua untuk email pertama (opsional)</label>
                    <input id="f-subb" v-model="form.subject_b" class="o-input" placeholder="Contoh: {nama}, rencana ziarah jemaat tahun depan?">
                    <p class="mt-1 text-xs text-slate-500">Kalau diisi, separuh kontak menerima subjek dari template (A) dan separuhnya subjek ini (B). Isi emailnya sama. Hasilnya, mana yang lebih banyak dibalas, tampil di halaman kampanye. Variabel seperti {nama} boleh dipakai.</p>
                    <p v-if="form.errors.subject_b" class="o-err">{{ form.errors.subject_b }}</p>
                </div>
                <p v-if="!templates.length" class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">Belum ada template. <Link href="/admin/outreach/templates" class="font-semibold underline">Buat template dulu</Link>.</p>
            </section>

            <section class="o-card p-5">
                <h2 class="mb-1 text-sm font-semibold text-slate-900">3. Akun pengirim</h2>
                <p class="mb-4 text-sm text-slate-500">Pilih satu atau beberapa. Email dibagi bergiliran, dan setiap kontak selalu dikirimi dari akun yang sama.</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label v-for="a in accounts" :key="a.id" class="flex cursor-pointer items-start gap-3 rounded-2xl p-3 ring-1 transition" :class="[form.account_ids.includes(a.id) ? 'bg-brand/[0.04] ring-brand/40' : 'ring-slate-200 hover:bg-slate-50', !a.is_active && 'opacity-50']">
                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand" :checked="form.account_ids.includes(a.id)" :disabled="!a.is_active" @change="toggle(form.account_ids, a.id)">
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-slate-900">{{ a.name }}</span><span class="block truncate text-xs text-slate-500">{{ a.email }}</span>
                            <span class="mt-1.5 flex flex-wrap gap-1.5"><Chip tone="slate" :dot="false">{{ a.daily_limit }} / hari</Chip><Chip v-if="a.smtp_ok === false" tone="red">SMTP gagal</Chip><Chip v-else-if="a.smtp_ok === null" tone="amber">Belum dites</Chip><Chip v-if="!a.is_active" tone="slate" :dot="false">Nonaktif</Chip></span></span>
                    </label>
                </div>
                <p v-if="!accounts.length" class="mt-1 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">Belum ada akun. <Link href="/admin/outreach/accounts" class="font-semibold underline">Tambah akun pengirim</Link>.</p>
                <p v-if="form.errors.account_ids" class="o-err mt-2">{{ form.errors.account_ids }}</p>
            </section>

            <section class="o-card p-5">
                <h2 class="mb-4 text-sm font-semibold text-slate-900">4. Jadwal kirim (waktu Jakarta)</h2>
                <div class="grid gap-4 sm:grid-cols-4">
                    <div><label class="o-label" for="w-s">Mulai jam</label><input id="w-s" v-model="form.window_start" type="time" class="o-input"></div>
                    <div><label class="o-label" for="w-e">Selesai jam</label><input id="w-e" v-model="form.window_end" type="time" class="o-input"><p v-if="form.errors.window_end" class="o-err">{{ form.errors.window_end }}</p></div>
                    <div><label class="o-label" for="g-min">Jeda minimal (menit)</label><input id="g-min" v-model="form.gap_min" type="number" min="1" max="30" class="o-input"></div>
                    <div><label class="o-label" for="g-max">Jeda maksimal (menit)</label><input id="g-max" v-model="form.gap_max" type="number" min="1" max="60" class="o-input"><p v-if="form.errors.gap_max" class="o-err">{{ form.errors.gap_max }}</p></div>
                </div>
                <div class="mt-4">
                    <div class="o-label">Hari kirim</div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="d in weekdays" :key="d.n" type="button" class="o-btn o-btn-sm" :class="form.send_days.includes(d.n) ? 'o-btn-primary' : ''" :aria-pressed="form.send_days.includes(d.n)" @click="toggle(form.send_days, d.n)">{{ d.label }}</button>
                    </div>
                    <p v-if="form.errors.send_days" class="o-err">{{ form.errors.send_days }}</p>
                </div>
            </section>
        </div>

        <aside class="xl:sticky xl:top-6 xl:self-start">
            <div class="o-card space-y-4 p-5">
                <div><div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sasaran</div><div class="mt-1 text-3xl font-semibold tabular-nums text-slate-900">{{ audience }}</div><div class="text-sm text-slate-500">kontak siap kirim{{ segment ? ` di ${segment.name}` : '' }}</div></div>
                <div class="border-t border-slate-100 pt-4 text-sm leading-relaxed text-slate-600">
                    Perkiraan: dengan {{ form.account_ids.length || 0 }} akun dan jeda {{ form.gap_min }}–{{ form.gap_max }} menit, tiap akun mengirim sekitar {{ Math.floor(60 / ((Number(form.gap_min) + Number(form.gap_max)) / 2 || 1)) }} email per jam, selama jam kirim dan di bawah batas harian akun.
                </div>
                <button class="o-btn o-btn-primary w-full" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : c ? 'Simpan perubahan' : 'Simpan kampanye' }}</button>
            </div>
        </aside>
    </form>
</template>
