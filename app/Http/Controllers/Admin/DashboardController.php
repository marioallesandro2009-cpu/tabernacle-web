<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AnalyticsDaily;
use App\Models\Lead;
use App\Models\PageView;
use App\Models\Post;
use App\Models\Tour;
use App\Support\AnalyticsService;
use App\Support\Content;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $range = (string) $request->query('range', '30d');
        [$from, $to] = AnalyticsService::resolveRange($range, $request->query('from'), $request->query('to'));
        $canStats = $user->can('analytics.view');
        $canLeads = $user->can('leads.manage');

        return Inertia::render('Admin/Dashboard', [
            'range' => $range,
            'stats' => $canStats ? AnalyticsService::summary($from, $to) : null,
            'recent_leads' => $canLeads ? Lead::with('tour:id,name')->where('type', 'form')->latest('id')->limit(6)->get()->map(fn (Lead $l) => [
                'id' => $l->id, 'name' => $l->name, 'phone' => $l->phone, 'tour' => $l->tour?->name ?: $l->tour_name,
                'status' => $l->status, 'at' => $l->created_at?->diffForHumans(),
            ]) : [],
            'counts' => [
                'tours' => $user->can('tours.manage') ? Tour::where('is_active', true)->count() : null,
                'posts' => $user->can('blog.manage') ? Post::where('status', 'published')->count() : null,
                'drafts' => $user->can('blog.manage') ? Post::where('status', 'draft')->count() : null,
                'new_leads' => $canLeads ? Lead::where('status', 'baru')->where('type', 'form')->count() : null,
            ],
            'analytics_enabled' => (bool) setting('analytics_enabled', true),
            'can_reset' => $user->can('settings.manage'),
            'export_url' => admin_path().'/leads/export/',
        ]);
    }

    /**
     * Clears visitor statistics (page views and daily totals) so counting starts fresh,
     * e.g. after testing. Leads are business records and are never touched here.
     */
    public function resetStats(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:RESET']], ['confirm.in' => 'Ketik RESET (huruf besar) untuk mengonfirmasi.', 'confirm.required' => 'Ketik RESET untuk mengonfirmasi.']);
        $views = PageView::count();
        PageView::query()->delete();
        AnalyticsDaily::query()->delete();
        Content::bump();
        ActivityLog::record('reset', 'Statistik', null, 'Reset statistik pengunjung ('.$views.' tayangan dihapus)');

        return redirect(admin_path())->with('success', 'Statistik pengunjung direset. Penghitungan dimulai dari nol. Data leads tidak berubah.');
    }
}
