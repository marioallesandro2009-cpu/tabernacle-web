<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Message;
use App\Services\Outreach\ThreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** One conversation with a prospect, and answering it from the website. */
class ThreadController extends Controller
{
    public function __construct(protected ThreadService $threads) {}

    public function show(Request $request, string $id): Response
    {
        $cc = CampaignContact::with(['contact.segment:id,name', 'campaign:id,name', 'account:id,name,email,is_active'])->findOrFail($id);

        return Inertia::render('Admin/Outreach/Thread', [
            'thread' => $this->present($cc, $request),
            'contact' => $this->contactCard($cc->contact),
            'statuses' => Message::REPLY_STATUSES,
        ]);
    }

    /** All conversations of one contact (every campaign they were in), each with its reply box. */
    public function contact(Request $request, string $id): Response
    {
        $contact = Contact::with('segment:id,name')->findOrFail($id);
        $status = $request->query('status');
        $ccs = CampaignContact::where('contact_id', $contact->id)->with(['campaign:id,name', 'account:id,name,email,is_active', 'contact'])->orderByDesc('id')->get();

        return Inertia::render('Admin/Outreach/ContactShow', [
            'contact' => $this->contactCard($contact),
            'threads' => $ccs->map(fn (CampaignContact $cc) => $this->present($cc, $request, is_string($status) && isset(Message::REPLY_STATUSES[$status]) ? $status : null))->values(),
            'statuses' => Message::REPLY_STATUSES, 'filter' => $status,
        ]);
    }

    /** Autosave while typing: one website draft per admin per conversation. */
    public function draft(Request $request, string $id): JsonResponse
    {
        $cc = CampaignContact::findOrFail($id);
        $data = $request->validate(['body' => ['present', 'nullable', 'string', 'max:20000'], 'draft_id' => ['nullable', 'integer']]);
        $draft = $this->threads->saveDraft($cc, $request->user(), (string) ($data['body'] ?? ''), $data['draft_id'] ?? null);

        return response()->json(['id' => $draft->id, 'saved_at' => now()->timezone(Campaign::TIMEZONE)->format('H:i')]);
    }

    /** "Kirim": saves the text as the draft and puts it in the send queue. */
    public function send(Request $request, string $id): RedirectResponse
    {
        $cc = CampaignContact::with(['account', 'contact'])->findOrFail($id);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'draft_id' => ['nullable', 'integer']], ['body.required' => 'Tulis balasannya dulu.']);
        if (! $cc->account || ! $cc->account->is_active) {
            return back()->with('error', 'Akun pengirim percakapan ini tidak aktif atau sudah dihapus.');
        }
        if (! $cc->contact?->email) {
            return back()->with('error', 'Kontak ini tidak punya alamat email.');
        }
        $draft = $this->threads->saveDraft($cc, $request->user(), trim($data['body']), $data['draft_id'] ?? null);
        $this->threads->queue($draft, $request->user());
        ActivityLog::record('create', 'Balasan Outreach (website)', $draft->id, (string) $cc->contact->email);

        return back()->with('success', 'Balasan masuk antrean dan dikirim dalam satu menit.');
    }

    /** Sends a draft (also a draft that came from a personal mailbox) after it was reviewed or edited. */
    public function sendDraft(Request $request, string $id): RedirectResponse
    {
        $m = $this->manual($id, ['draft']);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']], ['body.required' => 'Isi balasan masih kosong.']);
        $m->update(['body' => trim($data['body'])]);
        $this->threads->queue($m, $request->user());

        return back()->with('success', 'Draf masuk antrean dan dikirim dalam satu menit.');
    }

    public function retry(Request $request, string $id): RedirectResponse
    {
        $m = $this->manual($id, ['failed']);
        $this->threads->queue($m, $request->user());

        return back()->with('success', 'Dikirim ulang. Statusnya akan berubah dalam satu menit.');
    }

    public function discard(string $id): RedirectResponse
    {
        $m = $this->manual($id, ['draft']);
        $m->delete();

        return back()->with('success', 'Draf dihapus.');
    }

    /** @param array<int, string> $statuses */
    protected function manual(string $id, array $statuses): Message
    {
        $m = Message::where('kind', Message::MANUAL)->findOrFail($id);
        abort_unless(in_array($m->reply_status, $statuses, true), 422, 'Status balasan ini tidak bisa diubah lagi.');

        return $m;
    }

    /** @return array<string, mixed> */
    protected function present(CampaignContact $cc, Request $request, ?string $status = null): array
    {
        $msgs = $this->threads->messages($cc);
        $myDraft = $msgs->first(fn (Message $m) => $m->kind === Message::MANUAL && $m->reply_status === 'draft' && $m->source === 'website' && $m->user_id === $request->user()?->id);

        return [
            'id' => $cc->id, 'status' => $cc->status, 'campaign' => $cc->campaign?->name, 'campaign_id' => $cc->campaign_id,
            'account' => $cc->account ? ['name' => $cc->account->name, 'email' => $cc->account->email, 'active' => (bool) $cc->account->is_active] : null,
            'subject' => $this->threads->subject($cc), 'token' => $cc->thread_token,
            'can_reply' => (bool) ($cc->account?->is_active && $cc->contact?->email),
            'draft' => $myDraft ? ['id' => $myDraft->id, 'body' => $myDraft->body] : null,
            'messages' => $msgs->reject(fn (Message $m) => $myDraft && $m->id === $myDraft->id)
                ->filter(fn (Message $m) => ! $status || ($m->kind === Message::MANUAL && $m->reply_status === $status))
                ->map(fn (Message $m) => [
                    'id' => $m->id, 'direction' => $m->direction, 'kind' => $m->kind, 'subject' => $m->subject, 'body' => $m->body, 'step' => $m->step,
                    'at' => ($m->sent_at ?? $m->created_at)?->timezone(Campaign::TIMEZONE)->locale('id')->isoFormat('D MMM YYYY, HH:mm'),
                    'reply_status' => $m->reply_status, 'source' => $m->source, 'source_label' => Message::SOURCES[$m->source ?? ''] ?? null,
                    'by' => $m->user?->name, 'relay_from' => $m->relay_from, 'reason' => in_array($m->reply_status, ['rejected', 'failed', 'bounced'], true) ? $m->error : null,
                    'log' => collect($m->status_log ?? [])->map(fn ($l) => ['status' => $l['status'], 'reason' => $l['reason'] ?? null,
                        'at' => Carbon::parse($l['at'])->timezone(Campaign::TIMEZONE)->locale('id')->isoFormat('D MMM, HH:mm')])->values(),
                    'label' => $m->label, 'folder' => $m->getAttribute('folder'),
                ])->values(),
        ];
    }

    /** @return array<string, mixed>|null */
    protected function contactCard(?Contact $c): ?array
    {
        return $c ? ['id' => $c->id, 'name' => $c->name, 'title' => $c->title, 'organization' => $c->organization, 'email' => $c->email, 'phone' => $c->phone, 'segment' => $c->segment?->name, 'notes' => $c->notes] : null;
    }
}
