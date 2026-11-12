<?php

namespace App\Support;

use App\Models\AnalyticsDaily;
use App\Models\Lead;
use App\Models\Post;
use App\Models\Tour;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/** Reads the daily aggregates for a date range (days not yet aggregated are computed live). */
class AnalyticsService
{
    /** @return array{0: Carbon, 1: Carbon} */
    public static function resolveRange(?string $range, ?string $from, ?string $to): array
    {
        $end = now()->endOfDay();
        $start = match ($range) {
            'today' => now()->startOfDay(),
            'yesterday' => now()->subDay()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            '90d' => now()->subDays(89)->startOfDay(),
            'custom' => null,
            default => now()->subDays(29)->startOfDay(),
        };
        if ($range === 'yesterday') {
            $end = now()->subDay()->endOfDay();
        }
        if ($range === 'custom') {
            try {
                $start = Carbon::parse($from ?: 'now')->startOfDay();
                $end = Carbon::parse($to ?: 'now')->endOfDay();
            } catch (\Throwable) {
                $start = now()->subDays(29)->startOfDay();
                $end = now()->endOfDay();
            }
            if ($start->gt($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
            if ($start->diffInDays($end) > 366) {
                $start = $end->copy()->subDays(366)->startOfDay();
            }
        }

        return [$start, $end];
    }

    /** @return Collection<int, object{day: string, dimension: string, dim_key: string, views: int, visitors: int}> */
    protected static function rows(Carbon $from, Carbon $to): Collection
    {
        $stored = AnalyticsDaily::whereBetween('day', [$from->toDateString(), $to->toDateString()])->get(['day', 'dimension', 'dim_key', 'views', 'visitors']);
        $have = $stored->where('dimension', 'total')->map(fn ($r) => Carbon::parse($r->day)->toDateString())->flip();
        $live = collect();
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $d) {
            $day = $d->toDateString();
            if (! $have->has($day) && $d->lte(now())) {
                foreach (AnalyticsAggregator::forDay($day) as $r) {
                    $live->push((object) ($r + ['day' => $day]));
                }
            }
        }

        return $stored->map(fn ($r) => (object) [
            'day' => Carbon::parse($r->day)->toDateString(), 'dimension' => $r->dimension, 'dim_key' => $r->dim_key, 'views' => $r->views, 'visitors' => $r->visitors,
        ])->concat($live);
    }

    /** @return array<string, mixed> */
    public static function summary(Carbon $from, Carbon $to): array
    {
        $rows = self::rows($from, $to);
        $sum = fn (string $dim) => $rows->where('dimension', $dim)->groupBy('dim_key')
            ->map(fn ($g, $k) => ['key' => (string) $k, 'views' => (int) $g->sum('views'), 'visitors' => (int) $g->sum('visitors')])
            ->sortByDesc('views')->values();

        $series = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $d) {
            $r = $rows->first(fn ($x) => $x->dimension === 'total' && $x->day === $d->toDateString());
            $series[] = ['day' => $d->toDateString(), 'label' => $d->format('d M'), 'views' => (int) ($r->views ?? 0), 'visitors' => (int) ($r->visitors ?? 0)];
        }

        $tourNames = Tour::pluck('name', 'id');
        $postTitles = Post::pluck('title', 'id');
        $leads = Lead::whereBetween('created_at', [$from, $to]);
        $formLeads = (clone $leads)->where('type', 'form')->count();
        $waClicks = (clone $leads)->where('type', 'wa_click')->count();
        $views = array_sum(array_column($series, 'views'));
        $visitors = array_sum(array_column($series, 'visitors'));

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'kpi' => [
                'views' => $views, 'visitors' => $visitors,
                'pages_per_visit' => $visitors ? round($views / $visitors, 2) : 0,
                'leads' => $formLeads, 'wa_clicks' => $waClicks,
                'conversion' => $visitors ? round((($formLeads + $waClicks) / $visitors) * 100, 2) : 0,
            ],
            'series' => $series,
            'top_pages' => $sum('path')->take(10)->values(),
            'top_tours' => $sum('tour')->take(10)->map(fn ($r) => $r + ['name' => $tourNames[(int) $r['key']] ?? '#'.$r['key']])->values(),
            'top_posts' => $sum('post')->take(10)->map(fn ($r) => $r + ['name' => $postTitles[(int) $r['key']] ?? '#'.$r['key']])->values(),
            'entries' => $sum('entry')->take(8)->values(),
            'sources' => $sum('source')->values(),
            'devices' => $sum('device')->values(),
            'browsers' => $sum('browser')->take(8)->values(),
            'leads_by_status' => Lead::whereBetween('created_at', [$from, $to])->where('type', 'form')->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ];
    }
}
