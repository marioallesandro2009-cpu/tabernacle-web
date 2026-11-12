<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Faq;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<Faq> */
class FaqController extends ResourceController
{
    protected string $model = Faq::class;

    protected string $slug = 'faqs';

    protected string $title = 'FAQ';

    protected string $singular = 'Pertanyaan';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        return [
            F::t('question', 'Pertanyaan', ['required' => true]),
            F::trich('answer', 'Jawaban', ['required' => true]),
            F::text('group', 'Kelompok (opsional)'),
            F::toggle('is_active', 'Tampil di situs', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'question', 'label' => 'Pertanyaan', 'type' => 'tr'],
            ['key' => 'group', 'label' => 'Kelompok', 'type' => 'badge'],
        ];
    }
}
