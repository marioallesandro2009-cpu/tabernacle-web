<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = ['role' => 'array', 'bio' => 'array', 'is_active' => 'boolean'];
}
