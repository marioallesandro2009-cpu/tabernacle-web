<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Analytics;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sign-in history of every admin account (super admin only, via logs.view). Read-only. */
class LoginHistoryController extends Controller
{
    /** @return Builder<ActivityLog> */
    protected function filtered(Request $request): Builder
    {
        $q = ActivityLog::query()->with(['user:id,name,email', 'account:id,name,email'])->whereIn('action', array_keys(ActivityLog::AUTH_ACTIONS))->latest('id');
        if ($uid = $request->query('user_id')) {
            $q->where(fn ($w) => $w->where('user_id', $uid)->orWhere('subject_id', $uid));
        }
        if ($action = $request->query('action')) {
            $action === 'warnings' ? $q->whereIn('action', ActivityLog::WARNING_ACTIONS) : $q->where('action', $action);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('subject_label', 'like', "%$term%")->orWhere('ip', 'like', "%$term%"));
        }

        return $q;
    }

    /** @return array<string, mixed> */
    protected function row(ActivityLog $l): array
    {
        // the account the event is about (subject), else whoever was signed in
        $account = $l->subject_id ? $l->account : null;
        $actor = $l->user_id ? $l->user : null;
        $who = $account ?: $actor;

        return [
            'id' => $l->id, 'at' => $l->created_at?->format('d M Y H:i:s'), 'action' => $l->action,
            'label' => ActivityLog::AUTH_ACTIONS[$l->action] ?? $l->action, 'warning' => in_array($l->action, ActivityLog::WARNING_ACTIONS, true),
            'account' => $who ? $who->name : null, 'email' => $who ? $who->email : $l->subject_label, 'typed' => $l->subject_label,
            'by' => ($account && $actor && $actor->id !== $account->id) ? $actor->name : null,
            'ip' => $l->ip, 'device' => $l->user_agent ? Analytics::device($l->user_agent) : null, 'browser' => $l->user_agent ? Analytics::browser($l->user_agent) : null,
            'user_agent' => $l->user_agent,
        ];
    }

    public function index(Request $request): Response
    {
        $since = now()->subDays(7);
        $users = User::with('roles')->orderBy('name')->get();
        $lastLogins = ActivityLog::query()->where('action', 'login')->selectRaw('subject_id, max(created_at) as at, count(*) as n')
            ->where('created_at', '>=', now()->subDays(30))->groupBy('subject_id')->get()->keyBy('subject_id');

        return Inertia::render('Admin/Logins', [
            'logs' => $this->filtered($request)->paginate(50)->withQueryString()->through(fn (ActivityLog $l) => $this->row($l)),
            'query' => $request->only(['q', 'user_id', 'action', 'from', 'to']),
            'actions' => collect(ActivityLog::AUTH_ACTIONS)->map(fn ($label, $v) => ['value' => $v, 'label' => $label])->values(),
            'stats' => [
                'logins_today' => ActivityLog::where('action', 'login')->whereDate('created_at', today())->count(),
                'logins_7d' => ActivityLog::where('action', 'login')->where('created_at', '>=', $since)->count(),
                'warnings_7d' => ActivityLog::whereIn('action', ActivityLog::WARNING_ACTIONS)->where('created_at', '>=', $since)->count(),
                'active_admins_7d' => ActivityLog::where('action', 'login')->where('created_at', '>=', $since)->distinct()->count('subject_id'),
            ],
            'accounts' => $this->accounts($users, $lastLogins),
        ]);
    }

    /**
     * @param  iterable<int, User>  $users
     * @param  \Illuminate\Support\Collection<int|string, ActivityLog>  $lastLogins
     * @return array<int, array<string, mixed>>
     */
    protected function accounts(iterable $users, $lastLogins): array
    {
        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => Permissions::LABELS[$u->getRoleNames()->first()] ?? '–',
                'is_active' => (bool) $u->is_active, 'two_factor' => $u->hasTwoFactor(),
                'last_login' => $u->last_login_at ? $u->last_login_at->format('d M Y H:i') : null,
                'logins_30d' => (int) ($lastLogins->get($u->id)?->getAttribute('n') ?? 0),
            ];
        }

        return $out;
    }

    /** CSV of the current filter, for audits outside the panel. */
    public function export(Request $request): StreamedResponse
    {
        ActivityLog::record('export', 'Riwayat login', null, 'Ekspor riwayat login');
        $rows = $this->filtered($request)->limit(50000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
            fputcsv($out, ['Waktu', 'Peristiwa', 'Akun', 'Email / yang diketik', 'Oleh', 'IP', 'Perangkat', 'Browser', 'User agent']);
            foreach ($rows as $l) {
                $r = $this->row($l);
                $safe = fn ($v) => ($v !== null && $v !== '' && str_contains('=+-@', (string) $v[0])) ? "'".$v : $v;
                fputcsv($out, [$r['at'], $r['label'], $safe($r['account']), $safe($r['email']), $safe($r['by']), $r['ip'], $r['device'], $r['browser'], $safe($r['user_agent'])]);
            }
            fclose($out);
        }, 'riwayat-login-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
