<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\CaseStudy;
use App\Models\Destination;
use App\Models\Pillar;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<CaseStudy> */
class CaseStudyController extends ResourceController
{
    protected string $model = CaseStudy::class;

    protected string $slug = 'case-studies';

    protected string $title = 'Studi kasus';

    protected string $singular = 'Studi kasus';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        $pillars = Pillar::orderBy('sort')->get()->map(fn ($p) => ['value' => $p->id, 'label' => tr($p->title, 'id')])->values()->all();
        $dests = Destination::orderBy('slug')->get()->map(fn ($d) => ['value' => $d->id, 'label' => tr($d->name, 'id')])->values()->all();

        return [
            F::t('title', 'Judul program', ['required' => true]),
            F::slug('slug', 'Alamat (tampil di /studi-kasus/…)', 'title'),
            F::text('client', 'Klien (nama perusahaan / gereja / instansi)'),
            F::image('client_logo_id', 'Logo klien (opsional)'),
            F::select('pillar_id', 'Layanan', $pillars),
            F::trich('objective', 'Tujuan program'),
            F::multi('destinations_ids', 'Destinasi', $dests, ['relation' => 'destinations']),
            F::number('participants', 'Jumlah peserta'),
            F::t('period', 'Waktu pelaksanaan (contoh Maret 2026)'),
            F::image('cover_id', 'Foto utama'),
            F::images('gallery', 'Galeri foto'),
            F::trich('results', 'Hasil'),
            F::t('meta_title', 'SEO: judul'),
            F::tarea('meta_description', 'SEO: deskripsi'),
            F::toggle('is_active', 'Tampil di situs', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cover_id', 'label' => '', 'type' => 'image'],
            ['key' => 'title', 'label' => 'Program', 'type' => 'tr'],
            ['key' => 'client', 'label' => 'Klien', 'type' => 'text'],
        ];
    }
}
