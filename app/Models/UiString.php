<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class UiString extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];
}
