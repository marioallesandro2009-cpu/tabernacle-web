<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = ['content' => 'array', 'is_active' => 'boolean', 'rating' => 'integer'];
}
