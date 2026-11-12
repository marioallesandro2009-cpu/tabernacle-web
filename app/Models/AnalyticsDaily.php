<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDaily extends Model
{
    protected $table = 'analytics_daily';

    public $timestamps = false;

    protected $guarded = ['id'];
}
