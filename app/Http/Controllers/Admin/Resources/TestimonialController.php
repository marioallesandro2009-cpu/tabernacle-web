<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Testimonial;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<Testimonial> */
class TestimonialController extends ResourceController
{
    protected string $model = Testimonial::class;

    protected string $slug = 'testimonials';

    protected string $title = 'Testimoni';

    protected string $singular = 'Testimoni';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        return [
            F::text('name', 'Nama', ['required' => true]),
            F::image('photo_id', 'Foto'),
            F::text('trip', 'Trip yang diikuti'),
            F::tarea('content', 'Isi testimoni (hanya testimoni asli)', ['required' => true]),
            F::number('rating', 'Rating 1–5', ['min' => 1, 'max' => 5, 'default' => 5]),
            F::toggle('is_active', 'Tampil di situs', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'photo_id', 'label' => '', 'type' => 'image'],
            ['key' => 'name', 'label' => 'Nama', 'type' => 'text'],
            ['key' => 'trip', 'label' => 'Trip', 'type' => 'text'],
            ['key' => 'rating', 'label' => 'Rating', 'type' => 'text'],
        ];
    }
}
