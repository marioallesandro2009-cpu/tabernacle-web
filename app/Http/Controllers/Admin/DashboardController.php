<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Post;
use App\Models\Tour;
use App\Support\AnalyticsService;
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
            'export_url' => '/admin/leads/export/',
        ]);
    }
}
