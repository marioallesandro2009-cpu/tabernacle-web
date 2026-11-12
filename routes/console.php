<?php

use App\Services\DatabaseBackup;
use App\Support\AnalyticsAggregator;
use App\Support\Content;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/*
| Everything runs from ONE cron line: * * * * * php /path/artisan schedule:run
*/

Artisan::command('analytics:aggregate {--day= : YYYY-MM-DD, default yesterday and today}', function () {
    $days = $this->option('day') ? [$this->option('day')] : [now()->subDay()->toDateString(), now()->toDateString()];
    foreach ($days as $d) {
        $n = AnalyticsAggregator::store($d);
        $this->info("$d: $n baris");
    }
    // raw rows are kept 120 days; the aggregates stay forever
    DB::table('page_views')->where('day', '<', now()->subDays(120)->toDateString())->delete();
})->purpose('Rangkum page_views menjadi statistik harian');

Artisan::command('posts:publish-due', function () {
    $n = \App\Models\Post::where('status', 'scheduled')->where('published_at', '<=', now())->update(['status' => 'published']);
    if ($n) {
        Content::bump();
    }
    $this->info("$n artikel terbit");
})->purpose('Menerbitkan artikel terjadwal yang waktunya sudah tiba');

Artisan::command('db:backup {--keep=8}', function (DatabaseBackup $backup) {
    $path = $backup->create();
    $backup->prune((int) $this->option('keep'));
    $this->info('Backup: '.$path);
})->purpose('Backup database ke storage/app/backups');

Schedule::command('analytics:aggregate')->hourly();
Schedule::command('analytics:aggregate --day='.now()->subDay()->toDateString())->dailyAt('00:10');
Schedule::command('posts:publish-due')->everyFiveMinutes();
Schedule::command('db:backup')->weeklyOn(0, '02:00');
// Shared hosting has no queue daemon: drain the queue every minute instead.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
