<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Exports\CampaignResultsSheet;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outreach\Account;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Template;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\TemplateRenderer;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CampaignController extends Controller
{
    public const WEEKDAYS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    public function index(CampaignService $service): Response
    {
        $campaigns = Campaign::with('segment:id,name')->latest('id')->get()->map(function (Campaign $c) use ($service) {
            $s = $service->stats($c);

            return [
                'id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'segment' => $c->manual ? 'Kontak pilihan (kirim manual)' : $c->segment?->name,
                'contacts' => $s['contacts'], 'started' => $s['started'], 'replied' => $s['replied'], 'bounced' => $s['bounced'],
                'reply_rate' => $s['reply_rate'], 'sent' => $s['sent_messages'], 'at' => $c->created_at?->locale('id')->diffForHumans(),
            ];
        });

        return Inertia::render('Admin/Outreach/Campaigns', ['campaigns' => $campaigns]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(string $id): Response|RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);
        if ($campaign->status === 'running') {
            return redirect(admin_path().'/outreach/campaigns/'.$campaign->id)->with('error', 'Jeda kampanye dulu sebelum mengubah pengaturannya.');
        }

        return $this->form($campaign);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $campaign = Campaign::create($data['campaign'] + ['channel' => 'email', 'status' => 'draft', 'created_by' => $request->user()->id]);
            $this->sync($campaign, $data);

            return $campaign;
        });
        ActivityLog::record('create', 'Kampanye Outreach', $campaign->id, $campaign->name);

        return redirect(admin_path().'/outreach/campaigns/'.$campaign->id)->with('success', 'Kampanye dibuat. Periksa pengaturannya lalu klik Mulai.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);
        if ($campaign->status === 'running') {
            return back()->with('error', 'Jeda kampanye dulu sebelum mengubah pengaturannya.');
        }
        DB::transaction(function () use ($request, $campaign) {
            $data = $this->validated($request);
            $campaign->update($data['campaign']);
            $this->sync($campaign, $data);
        });
        ActivityLog::record('update', 'Kampanye Outreach', $campaign->id, $campaign->name);

        return redirect(admin_path().'/outreach/campaigns/'.$campaign->id)->with('success', 'Kampanye disimpan.');
    }

    public function show(Request $request, string $id, CampaignService $service, CampaignScheduler $scheduler): Response
    {
        $campaign = Campaign::with(['segment:id,name', 'steps.template:id,name', 'accounts'])->findOrFail($id);
        $status = $request->query('status');
        $rows = CampaignContact::where('campaign_id', $campaign->id)->with(['contact:id,name,title,organization,email', 'account:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))->orderByDesc('last_sent_at')->orderBy('id')->paginate(25)->withQueryString()
            ->through(fn (CampaignContact $cc) => [
                'id' => $cc->id, 'name' => $cc->contact?->name, 'title' => $cc->contact?->title, 'organization' => $cc->contact?->organization,
                'email' => $cc->contact?->email, 'status' => $cc->status, 'step' => $cc->current_step, 'account' => $cc->account?->name,
                'last' => $cc->last_sent_at?->timezone(Campaign::TIMEZONE)->locale('id')->isoFormat('D MMM, HH:mm'),
                'next' => $cc->next_send_at?->timezone(Campaign::TIMEZONE)->locale('id')->isoFormat('D MMM'),
            ]);
        $steps = $campaign->steps->whereNotNull('template_id');

        return Inertia::render('Admin/Outreach/CampaignShow', [
            'campaign' => [
                'id' => $campaign->id, 'name' => $campaign->name, 'status' => $campaign->status, 'segment' => $campaign->manual ? 'Kontak pilihan (kirim manual)' : $campaign->segment?->name,
                'window' => $campaign->window_start.'–'.$campaign->window_end, 'days' => collect($campaign->send_days)->map(fn ($d) => self::WEEKDAYS[$d] ?? $d)->implode(', '),
                'gap' => intdiv($campaign->gap_min_seconds, 60).'–'.ceil($campaign->gap_max_seconds / 60).' menit',
                'in_window' => $campaign->inWindow(), 'problem' => app(CampaignService::class)->problem($campaign),
                'steps' => $steps->map(fn ($s) => ['step' => $s->step, 'template' => $s->template?->name, 'day_offset' => $s->day_offset])->values(),
                'accounts' => $campaign->accounts->map(fn (Account $a) => ['id' => $a->id, 'name' => $a->name, 'email' => $a->email, 'is_active' => $a->is_active, 'today' => $scheduler->sentToday($a), 'limit' => $a->daily_limit])->values(),
            ],
            'stats' => $service->stats($campaign), 'variants' => $service->variants($campaign), 'rows' => $rows, 'filter' => $status,
        ]);
    }

    /** Excel file with one row per person: what was sent and how they responded. */
    public function export(string $id): BinaryFileResponse
    {
        $campaign = Campaign::findOrFail($id);
        $name = 'kampanye-'.(Str::slug($campaign->name) ?: $campaign->id).'-'.now()->timezone(Campaign::TIMEZONE)->format('Ymd');

        return Excel::download(new CampaignResultsSheet($campaign), $name.'.xlsx', ExcelFormat::XLSX);
    }

    public function start(string $id, CampaignService $service): RedirectResponse
    {
        return $this->run($id, fn (Campaign $c) => $service->start($c), 'Kampanye dimulai. Email dikirim bertahap di dalam jam kirim.');
    }

    public function pause(string $id, CampaignService $service): RedirectResponse
    {
        return $this->run($id, fn (Campaign $c) => $service->pause($c), 'Kampanye dijeda. Email yang sudah dijadwalkan dibatalkan otomatis.');
    }

    public function resume(string $id, CampaignService $service): RedirectResponse
    {
        return $this->run($id, fn (Campaign $c) => $service->resume($c), 'Kampanye dilanjutkan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);
        if ($campaign->status === 'running') {
            return back()->with('error', 'Jeda kampanye dulu sebelum menghapusnya.');
        }
        ActivityLog::record('delete', 'Kampanye Outreach', $campaign->id, $campaign->name);
        $campaign->delete();

        return redirect(admin_path().'/outreach/campaigns')->with('success', 'Kampanye dihapus.');
    }

    protected function run(string $id, callable $action, string $ok): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);
        try {
            $action($campaign);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        ActivityLog::record('update', 'Kampanye Outreach', $campaign->id, $campaign->name.' ('.$campaign->fresh()?->status.')');

        return back()->with('success', $ok);
    }

    protected function form(?Campaign $campaign): Response
    {
        $campaign?->load(['steps', 'accounts']);
        $stepOf = fn (int $n) => $campaign?->steps->firstWhere('step', $n);

        return Inertia::render('Admin/Outreach/CampaignForm', [
            'campaign' => $campaign ? [
                'id' => $campaign->id, 'name' => $campaign->name, 'subject_b' => $campaign->subject_b, 'allow_catch_all' => (bool) $campaign->allow_catch_all, 'segment_id' => $campaign->segment_id, 'account_ids' => $campaign->accounts->pluck('id')->all(),
                'window_start' => $campaign->window_start, 'window_end' => $campaign->window_end, 'send_days' => $campaign->send_days,
                'gap_min' => intdiv($campaign->gap_min_seconds, 60), 'gap_max' => (int) ceil($campaign->gap_max_seconds / 60),
                'steps' => collect([1, 2, 3])->map(fn ($n) => ['template_id' => data_get($stepOf($n), 'template_id'), 'day_offset' => data_get($stepOf($n), 'day_offset', [1 => 0, 2 => 4, 3 => 10][$n])])->all(),
            ] : null,
            'segments' => Segment::withCount(['contacts as sendable_count' => fn ($q) => $q->sendable(), 'contacts as catch_all_count' => fn ($q) => $q->sendable(true)->where('verify_status', 'catch_all')])->orderBy('name')->get(['id', 'name']),
            'accounts' => Account::orderBy('name')->get(['id', 'name', 'email', 'is_active', 'daily_limit', 'smtp_ok']),
            'templates' => Template::with('segment:id,name')->where('is_active', true)->orderBy('name')->get()->map(fn (Template $t) => ['id' => $t->id, 'name' => $t->name, 'segment_id' => $t->segment_id, 'segment' => $t->segment?->name]),
            'weekdays' => collect(self::WEEKDAYS)->map(fn ($label, $n) => ['n' => $n, 'label' => $label])->values(),
        ]);
    }

    /** @return array{campaign: array<string, mixed>, account_ids: array<int, int>, steps: array<int, array{step: int, template_id: int, day_offset: int}>} */
    protected function validated(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject_b' => ['nullable', 'string', 'max:500'],
            'allow_catch_all' => ['boolean'],
            'segment_id' => ['nullable', 'integer', 'exists:outreach_segments,id'],
            'account_ids' => ['required', 'array', 'min:1'], 'account_ids.*' => ['integer', 'exists:outreach_accounts,id'],
            'window_start' => ['required', 'date_format:H:i'], 'window_end' => ['required', 'date_format:H:i', 'after:window_start'],
            'send_days' => ['required', 'array', 'min:1'], 'send_days.*' => ['integer', 'between:1,7'],
            'gap_min' => ['required', 'integer', 'between:1,30'], 'gap_max' => ['required', 'integer', 'between:1,60', 'gte:gap_min'],
            'steps' => ['required', 'array', 'size:3'],
            'steps.*.template_id' => ['nullable', 'integer', 'exists:outreach_templates,id'], 'steps.*.day_offset' => ['required', 'integer', 'between:0,120'],
        ], [
            'account_ids.required' => 'Pilih minimal satu akun pengirim.', 'account_ids.min' => 'Pilih minimal satu akun pengirim.',
            'send_days.required' => 'Pilih minimal satu hari kirim.', 'window_end.after' => 'Jam selesai harus setelah jam mulai.',
            'gap_max.gte' => 'Jeda maksimal tidak boleh lebih kecil dari jeda minimal.',
        ]);
        $subjectB = trim((string) ($d['subject_b'] ?? '')) ?: null;
        if ($subjectB && ($unknown = app(TemplateRenderer::class)->unknownVariables($subjectB))) {
            throw ValidationException::withMessages(['subject_b' => 'Variabel tidak dikenal: {'.implode('}, {', $unknown).'}.']);
        }
        $steps = [];
        foreach (array_values($d['steps']) as $i => $s) {
            if (! empty($s['template_id'])) {
                $steps[] = ['step' => $i + 1, 'template_id' => (int) $s['template_id'], 'day_offset' => $i === 0 ? 0 : (int) $s['day_offset']];
            }
        }
        if (empty($steps) || $steps[0]['step'] !== 1) {
            throw ValidationException::withMessages(['steps' => 'Pilih template untuk langkah 1 (email pertama).']);
        }
        for ($i = 1; $i < count($steps); $i++) {
            if ($steps[$i]['step'] !== $steps[$i - 1]['step'] + 1) {
                throw ValidationException::withMessages(['steps' => 'Langkah 3 hanya bisa dipakai kalau langkah 2 juga diisi.']);
            }
            if ($steps[$i]['day_offset'] <= $steps[$i - 1]['day_offset']) {
                throw ValidationException::withMessages(['steps' => 'Hari kirim tiap follow-up harus lebih lambat dari langkah sebelumnya.']);
            }
        }

        return [
            'campaign' => [
                'name' => $d['name'], 'subject_b' => $subjectB, 'allow_catch_all' => (bool) ($d['allow_catch_all'] ?? false), 'segment_id' => $d['segment_id'] ?? null, 'window_start' => $d['window_start'], 'window_end' => $d['window_end'],
                'send_days' => array_values(array_unique(array_map('intval', $d['send_days']))), 'gap_min_seconds' => $d['gap_min'] * 60, 'gap_max_seconds' => $d['gap_max'] * 60,
            ],
            'account_ids' => array_map('intval', $d['account_ids']), 'steps' => $steps,
        ];
    }

    /** @param array{account_ids: array<int, int>, steps: array<int, array<string, int>>} $data */
    protected function sync(Campaign $campaign, array $data): void
    {
        $campaign->accounts()->sync($data['account_ids']);
        $campaign->steps()->delete();
        foreach ($data['steps'] as $s) {
            $campaign->steps()->create($s);
        }
    }
}
