<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;

class ContactImport extends Model
{
    protected $table = 'outreach_imports';

    protected $guarded = ['id'];

    protected $casts = ['mapping' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
}
