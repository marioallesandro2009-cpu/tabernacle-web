<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LoginBlock;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\LoginGuard;
use App\Support\Analytics;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'devices' => TrustedDevice::with('user:id,name,email')->whereNull('revoked_at')->where('updated_at', '>', now()->subDays(LoginGuard::DEVICE_DAYS))
                ->orderByDesc('last_used_at')->limit(100)->get()->map(fn (TrustedDevice $d) => [
                    'id' => $d->id, 'account' => $d->user?->name ?: $d->user?->email, 'browser' => $d->user_agent ? Analytics::browser($d->user_agent).' · '.Analytics::device($d->user_agent) : '–',
                    'ip' => $d->ip_last, 'last_used' => $d->last_used_at?->format('d M Y H:i'), 'since' => $d->created_at?->format('d M Y'),
                    'two_factor_until' => $d->two_factor_until?->isFuture() ? $d->two_factor_until->format('d M Y') : null,
                ]),
            'blocks' => LoginBlock::with('user:id,name')->where('created_at', '>', now()->subDays(7))->latest('id')->limit(100)->get()->map(fn (LoginBlock $b) => [
                'id' => $b->id, 'scope' => LoginBlock::SCOPES[$b->scope] ?? $b->scope, 'ip' => $b->ip, 'account' => $b->user?->name,
                'until' => $b->blocked_until->format('d M Y H:i'), 'at' => $b->created_at?->format('d M Y H:i'),
                'duration' => LoginGuard::duration((int) min(15 * (2 ** $b->level), 1440)),
                'active' => ! $b->lifted_at && $b->blocked_until->isFuture(), 'lifted' => (bool) $b->lifted_at,
            ]),
            'protected' => $users->filter(fn (User $u) => $u->protected_at && app(LoginGuard::class)->isProtected($u))
                ->map(fn (User $u) => ['name' => $u->name, 'email' => $u->email, 'since' => $u->protected_at?->format('d M Y H:i')])->values(),
        ]);
    }

    /**
     * @param  iterable<int, User>  $users
     * @param  Collection<int|string, ActivityLog>  $lastLogins
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

    /** Revokes one trusted device: the next sign-in from it is treated as a new device. */
    public function revokeDevice(string $id): RedirectResponse
    {
        $d = TrustedDevice::with('user')->findOrFail($id);
        $d->update(['revoked_at' => now()]);
        ActivityLog::auth('device_revoked', $d->user, ($d->user->email ?? '').' · '.($d->user_agent ? Analytics::browser($d->user_agent) : 'perangkat'), auth()->id());

        return back()->with('success', 'Perangkat dicabut. Login berikutnya dari perangkat itu dianggap perangkat baru.');
    }

    /** Lifts a block early (for example when a colleague mistyped the password a few times). */
    public function liftBlock(string $id): RedirectResponse
    {
        $b = LoginBlock::findOrFail($id);
        $b->update(['lifted_at' => now()]);
        ActivityLog::auth('block_lifted', $b->user, (LoginBlock::SCOPES[$b->scope] ?? $b->scope).($b->ip ? ' '.$b->ip : ''), auth()->id());

        return back()->with('success', 'Blokir dibuka.');
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
