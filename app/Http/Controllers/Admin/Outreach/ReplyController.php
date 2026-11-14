<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Jobs\SendLeadNotification;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Outreach\Account;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\Message;
use App\Models\Outreach\Suppression;
use App\Services\Outreach\ReplyPoller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Every reply from every sender account in one place, to be labelled and turned into leads. */
class ReplyController extends Controller
{
    public const LABELS = ['interested', 'not_now', 'not_interested'];

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), ['auto', 'bounce', 'ours'], true) ? $request->query('tab') : 'reply';
        $label = $request->query('label');
        $summary = [
            'draft' => Message::where('kind', Message::MANUAL)->where('reply_status', 'draft')->count(),
            'failed' => Message::where('kind', Message::MANUAL)->where('reply_status', 'failed')->count(),
            'rejected' => Message::where('kind', Message::MANUAL)->where('reply_status', 'rejected')->count(),
        ];
        if ($tab === 'ours') {
            return $this->ours($request, $summary);
        }
        $kind = ['reply' => 'reply', 'auto' => 'auto_reply', 'bounce' => 'bounce'][$tab];
        $list = Message::where('direction', 'in')->where('kind', $kind)
            ->when($label === 'none', fn ($q) => $q->whereNull('label'))
            ->when(in_array($label, self::LABELS, true), fn ($q) => $q->where('label', $label))
            ->with(['contact:id,name,title,organization,email', 'campaign:id,name'])->orderByDesc('sent_at')->orderByDesc('id')->paginate(30)->withQueryString();
        $items = $list->through(fn (Message $m) => [
            'id' => $m->id, 'name' => $m->contact?->name ?: $m->contact?->email ?: ($m->error ?: 'Tidak dikenal'), 'organization' => $m->contact?->organization,
            'subject' => $m->subject, 'preview' => mb_substr(preg_replace('/\s+/', ' ', (string) $m->body) ?? '', 0, 110),
            'label' => $m->label, 'at' => $this->when($m), 'campaign' => $m->campaign?->name, 'lead_id' => $m->lead_id, 'spam' => self::isSpam($m->folder),
        ]);
        $selectedId = (int) $request->query('id', $list->first()['id'] ?? 0);

        return Inertia::render('Admin/Outreach/Replies', [
            'messages' => $items, 'tab' => $tab, 'label' => $label,
            'selected' => $selectedId ? $this->detail($selectedId, $kind) : null,
            'counts' => [
                'reply' => Message::where('direction', 'in')->where('kind', 'reply')->count(),
                'unlabeled' => Message::where('direction', 'in')->where('kind', 'reply')->whereNull('label')->count(),
                'auto' => Message::where('direction', 'in')->where('kind', 'auto_reply')->count(),
                'bounce' => Message::where('direction', 'in')->where('kind', 'bounce')->count(),
                'ours' => Message::where('kind', Message::MANUAL)->count(),
            ],
            'summary' => $summary, 'statuses' => Message::REPLY_STATUSES,
            'polled' => ($p = Account::where('is_active', true)->whereNotNull('imap_polled_at')->max('imap_polled_at')) ? Carbon::parse($p)->locale('id')->diffForHumans() : null,
        ]);
    }

    public function label(Request $request, string $id): RedirectResponse
    {
        $m = Message::where('direction', 'in')->findOrFail($id);
        $data = $request->validate(['label' => ['nullable', Rule::in(self::LABELS)]]);
        $m->update(['label' => $data['label'] ?? null]);
        $note = '';
        if (($data['label'] ?? null) === 'not_interested' && $m->contact?->email) {
            Suppression::add($m->contact->email, 'manual', 'Membalas: tidak tertarik');
            $note = ' Kontak ini dimasukkan ke daftar jangan dikirimi.';
        }
        ActivityLog::record('update', 'Balasan Outreach', $m->id, ($m->contact?->email ?: 'balasan').': '.($data['label'] ?? 'tanpa label'));

        return back()->with('success', 'Label disimpan.'.$note);
    }

    /** Turns a reply into a lead in the Leads module, so the sales team follows it up there. */
    public function lead(string $id): RedirectResponse
    {
        $m = Message::where('direction', 'in')->with(['contact', 'campaign'])->findOrFail($id);
        if ($m->lead_id && Lead::whereKey($m->lead_id)->exists()) {
            return redirect('/admin/leads/'.$m->lead_id)->with('success', 'Balasan ini sudah dijadikan lead.');
        }
        $c = $m->contact;
        $lead = Lead::create([
            'type' => 'form', 'name' => $c?->name ?: $c?->email, 'phone' => $c?->phone ? mb_substr($c->phone, 0, 30) : null, 'email' => $c?->email,
            'message' => mb_substr(strip_tags((string) $m->body), 0, 2000), 'status' => 'baru', 'source' => 'outreach',
            'notes' => 'Dari Outreach'.($m->campaign ? ', kampanye "'.$m->campaign->name.'"' : '').'. '.collect([$c?->title, $c?->organization])->filter()->implode(', '),
        ]);
        LeadEvent::create(['lead_id' => $lead->id, 'event' => 'created', 'to_value' => 'baru']);
        $m->update(['lead_id' => $lead->id, 'label' => $m->label ?: 'interested']);
        if (setting('lead_notify_enabled', true)) {
            SendLeadNotification::dispatch($lead->id);
        }
        ActivityLog::record('create', 'Lead dari Outreach', $lead->id, (string) ($c?->email ?: $c?->name));

        return redirect('/admin/leads/'.$lead->id)->with('success', 'Lead dibuat dari balasan ini.');
    }

    /**
     * "Balasan kita": every reply we wrote (from the website or a personal mailbox), with its status.
     *
     * @param  array<string, int>  $summary
     */
    protected function ours(Request $request, array $summary): Response
    {
        $status = $request->query('status');
        $status = is_string($status) && isset(Message::REPLY_STATUSES[$status]) ? $status : null;
        $source = in_array($request->query('source'), ['website', 'email'], true) ? $request->query('source') : null;
        $list = Message::where('kind', Message::MANUAL)->when($status, fn ($q) => $q->where('reply_status', $status))->when($source, fn ($q) => $q->where('source', $source))
            ->with(['contact:id,name,organization,email', 'campaign:id,name', 'user:id,name'])->orderByDesc('updated_at')->orderByDesc('id')->paginate(30)->withQueryString()
            ->through(fn (Message $m) => [
                'id' => $m->id, 'thread_id' => $m->campaign_contact_id, 'name' => $m->contact?->name ?: ($m->contact?->email ?: ($m->relay_from ?: 'Tidak dikenal')),
                'organization' => $m->contact?->organization, 'campaign' => $m->campaign?->name, 'preview' => mb_substr(preg_replace('/\s+/', ' ', (string) $m->body) ?? '', 0, 140),
                'reply_status' => $m->reply_status, 'source' => $m->source, 'source_label' => Message::SOURCES[$m->source ?? ''] ?? null,
                'by' => $m->user?->name ?: $m->relay_from, 'reason' => $m->error, 'at' => ($m->sent_at ?? $m->updated_at)?->timezone(Campaign::TIMEZONE)->locale('id')->isoFormat('D MMM YYYY, HH:mm'),
            ]);

        return Inertia::render('Admin/Outreach/Replies', [
            'messages' => ['data' => [], 'links' => []], 'tab' => 'ours', 'label' => null, 'selected' => null, 'ours' => $list,
            'ourStatus' => $status, 'ourSource' => $source, 'summary' => $summary, 'statuses' => Message::REPLY_STATUSES,
            'counts' => [
                'reply' => Message::where('direction', 'in')->where('kind', 'reply')->count(), 'unlabeled' => 0,
                'auto' => Message::where('direction', 'in')->where('kind', 'auto_reply')->count(),
                'bounce' => Message::where('direction', 'in')->where('kind', 'bounce')->count(),
                'ours' => Message::where('kind', Message::MANUAL)->count(),
            ],
            'polled' => null,
        ]);
    }

    /** "Cek sekarang": reads the inboxes right away instead of waiting for the 10-minute schedule. */
    public function poll(ReplyPoller $poller): RedirectResponse
    {
        if (! Cache::add('outreach:manual-poll', 1, 60)) {
            return back()->with('error', 'Baru saja dicek. Coba lagi sebentar lagi.');
        }
        $r = $poller->pollAll();
        if ($r['accounts'] === 0) {
            return back()->with('error', 'Belum ada akun pengirim aktif dengan server IMAP.');
        }

        return back()->with($r['errors'] ? 'error' : 'success', "{$r['accounts']} akun dicek: {$r['stored']} pesan baru".($r['errors'] ? ", {$r['errors']} akun gagal dibaca (lihat halaman Akun pengirim)." : '.'));
    }

    /** @return array<string, mixed>|null */
    protected function detail(int $id, string $kind): ?array
    {
        $m = Message::where('direction', 'in')->where('kind', $kind)->with(['contact', 'campaign:id,name', 'account:id,name,email'])->find($id);
        if (! $m) {
            return null;
        }
        $thread = $m->campaign_contact_id ? Message::where('campaign_contact_id', $m->campaign_contact_id)->where('id', '!=', $m->id)
            ->where(fn ($q) => $q->whereIn('status', ['sent', 'received'])->orWhere('reply_status', 'sent'))->orderByRaw('coalesce(sent_at, created_at)')->get() : collect();

        return [
            'id' => $m->id, 'subject' => $m->subject, 'body' => $m->body, 'label' => $m->label, 'lead_id' => $m->lead_id, 'kind' => $m->kind, 'thread_id' => $m->campaign_contact_id, 'folder' => $m->folder, 'spam' => self::isSpam($m->folder),
            'at' => $this->when($m), 'account' => $m->account ? $m->account->name.' · '.$m->account->email : null, 'campaign' => $m->campaign?->name,
            'contact' => $m->contact ? ['id' => $m->contact->id, 'name' => $m->contact->name, 'title' => $m->contact->title, 'organization' => $m->contact->organization, 'email' => $m->contact->email, 'phone' => $m->contact->phone] : null,
            'thread' => $thread->map(fn (Message $t) => ['id' => $t->id, 'direction' => $t->direction, 'kind' => $t->kind, 'subject' => $t->subject, 'body' => mb_substr((string) $t->body, 0, 1500), 'at' => $this->when($t), 'step' => $t->step, 'reply_status' => $t->reply_status, 'source_label' => Message::SOURCES[$t->source ?? ''] ?? null])->values(),
        ];
    }

    public static function isSpam(?string $folder): bool
    {
        return $folder !== null && strtoupper($folder) !== 'INBOX';
    }

    protected function when(Message $m): ?string
    {
        return $m->sent_at?->timezone(Campaign::TIMEZONE)->locale('id')->isoFormat('D MMM YYYY, HH:mm');
    }
}
