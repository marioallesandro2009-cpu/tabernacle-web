<?php

namespace App\Support;

use App\Models\AnalyticsDaily;
use Illuminate\Support\Facades\DB;

/** Turns raw page_views of one day into the compact analytics_daily rows. */
class AnalyticsAggregator
{
    /**
     * @return array<int, array{dimension: string, dim_key: string, views: int, visitors: int}>
     */
    public static function forDay(string $day): array
    {
        $rows = [];
        $push = function (string $dim, string $key, int $views, int $visitors) use (&$rows) {
            $rows[] = ['dimension' => $dim, 'dim_key' => mb_substr($key, 0, 190), 'views' => $views, 'visitors' => $visitors];
        };
        $base = fn () => DB::table('page_views')->where('day', $day);

        $t = $base()->selectRaw('count(*) v, count(distinct visitor_hash) u')->first();
        if (! $t || ! $t->v) {
            return [];
        }
        $push('total', 'all', (int) $t->v, (int) $t->u);

        $group = function (string $dim, string $col, ?string $where = null) use ($base, $push) {
            $q = $base()->selectRaw("$col k, count(*) v, count(distinct visitor_hash) u")->whereNotNull($col)->groupBy($col);
            if ($where) {
                $q->whereRaw($where);
            }
            foreach ($q->get() as $r) {
                $push($dim, (string) $r->k, (int) $r->v, (int) $r->u);
            }
        };
        $group('path', 'path');
        $group('tour', 'ref_id', "page_type = 'tour'");
        $group('post', 'ref_id', "page_type = 'post'");
        $group('source', 'source');
        $group('device', 'device');
        $group('browser', 'browser');
        $group('entry', 'path', 'is_entry = 1');

        return $rows;
    }

    public static function store(string $day): int
    {
        $rows = self::forDay($day);
        DB::transaction(function () use ($day, $rows) {
            AnalyticsDaily::where('day', $day)->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                AnalyticsDaily::insert(array_map(fn ($r) => $r + ['day' => $day], $chunk));
            }
        });

        return count($rows);
    }
}
