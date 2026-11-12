<?php

use App\Models\Post;
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
    $n = Post::where('status', 'scheduled')->where('published_at', '<=', now())->update(['status' => 'published']);
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

/*
| Every task runs inside the schedule:run process (Schedule::call + Artisan::call).
| Shared hosting such as Rumahweb disables proc_open, and Schedule::command() needs it
| to start each task as a separate process, so those tasks would never run there.
*/
$task = fn (string $name, array $args = []) => Schedule::call(fn () => Artisan::call($name, $args))->name($name.' '.json_encode($args));

$task('analytics:aggregate')->hourly();
Schedule::call(fn () => Artisan::call('analytics:aggregate', ['--day' => now()->subDay()->toDateString()]))->name('analytics:aggregate yesterday')->dailyAt('00:10');
$task('posts:publish-due')->everyFiveMinutes();
$task('db:backup')->weeklyOn(0, '02:00');
// No queue daemon on shared hosting: drain the queue every minute instead (lead emails go out within a minute).
$task('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3])->everyMinute()->withoutOverlapping(10);
$task('queue:prune-failed', ['--hours' => 720])->daily();
