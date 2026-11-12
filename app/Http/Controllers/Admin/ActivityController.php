<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $q = ActivityLog::with('user:id,name')->latest('id');
        foreach (['user_id', 'action', 'subject_type'] as $col) {
            if (($v = $request->query($col)) !== null && $v !== '') {
                $q->where($col, $v);
            }
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where('subject_label', 'like', "%$term%");
        }

        return Inertia::render('Admin/Activity', [
            'logs' => $q->paginate(40)->withQueryString()->through(fn (ActivityLog $l) => [
                'id' => $l->id, 'user' => $l->user?->name ?: 'Sistem', 'action' => $l->action, 'type' => $l->subject_type,
                'label' => $l->subject_label, 'changes' => $l->changes, 'ip' => $l->ip, 'at' => $l->created_at?->format('d M Y H:i:s'),
            ]),
            'query' => $request->only(['q', 'user_id', 'action', 'subject_type']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::query()->distinct()->pluck('action'),
            'types' => ActivityLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
