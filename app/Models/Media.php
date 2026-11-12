<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use FlushesContent, LogsActivity;

    protected $table = 'media';

    protected $guarded = ['id'];

    protected $casts = ['variants' => 'array'];

    protected $appends = ['url', 'thumb'];

    public function getUrlAttribute(): string
    {
        return asset('uploads/'.$this->path);
    }

    public function getThumbAttribute(): string
    {
        if ($this->kind !== 'image') {
            return '';
        }
        $v = $this->variants ?: [];
        ksort($v, SORT_NUMERIC);

        return asset('uploads/'.($v[array_key_first($v)] ?? $this->path));
    }

    /** All files on disk that belong to this item (original and sizes). */
    public function allPaths(): array
    {
        return array_values(array_unique(array_filter([$this->path, ...array_values($this->variants ?: [])])));
    }
}
