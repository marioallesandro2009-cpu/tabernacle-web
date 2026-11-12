<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class LeadRecipient extends Model
{
    use LogsActivity;

    /** Hard limit of notification addresses. */
    public const MAX = 10;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
