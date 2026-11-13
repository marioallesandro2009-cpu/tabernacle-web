<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outreach\Account;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignStep;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Template;
use App\Services\Outreach\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Email wording per segment: a church segment can have its own template, for example with a verse. */
class TemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Outreach/Templates', [
            'templates' => Template::with('segment:id,name')->orderBy('name')->get()->map(fn (Template $t) => [
                'id' => $t->id, 'name' => $t->name, 'segment_id' => $t->segment_id, 'segment' => $t->segment?->name,
                'subject' => $t->subject, 'body' => $t->body, 'is_active' => $t->is_active,
                'used_in' => CampaignStep::where('template_id', $t->id)->count(),
            ]),
            'segments' => Segment::orderBy('name')->get(['id', 'name']),
            'variables' => collect(Template::VARIABLES)->map(fn ($hint, $key) => ['key' => $key, 'hint' => $hint])->values(),
            'sampleContacts' => Contact::sendable()->orderBy('id')->limit(30)->get(['id', 'name', 'organization'])->map(fn ($c) => ['id' => $c->id, 'label' => trim(($c->name ?: 'Tanpa nama').($c->organization ? ' · '.$c->organization : ''))]),
            'senders' => Account::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, TemplateRenderer $renderer): RedirectResponse
    {
        $template = Template::create($this->validated($request, $renderer) + ['channel' => 'email']);
        ActivityLog::record('create', 'Template Outreach', $template->id, $template->name);

        return back()->with('success', 'Template dibuat.');
    }

    public function update(Request $request, string $id, TemplateRenderer $renderer): RedirectResponse
    {
        $template = Template::findOrFail($id);
        $template->update($this->validated($request, $renderer));
        ActivityLog::record('update', 'Template Outreach', $template->id, $template->name);

        return back()->with('success', 'Template disimpan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $template = Template::findOrFail($id);
        $busy = Campaign::where('status', 'running')->whereHas('steps', fn ($s) => $s->where('template_id', $template->id))->exists();
        if ($busy) {
            return back()->with('error', 'Template ini dipakai kampanye yang sedang berjalan. Jeda kampanyenya dulu.');
        }
        ActivityLog::record('delete', 'Template Outreach', $template->id, $template->name);
        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }

    /** Shows the email as one real contact would get it, with the footer. */
    public function preview(Request $request, TemplateRenderer $renderer): JsonResponse
    {
        $data = $request->validate(['subject' => ['nullable', 'string', 'max:250'], 'body' => ['nullable', 'string', 'max:8000'], 'contact_id' => ['nullable', 'integer'], 'account_id' => ['nullable', 'integer']]);
        $contact = ($data['contact_id'] ?? null) ? Contact::find($data['contact_id']) : null;
        $contact ??= new Contact(['name' => 'Pdt. Budi Santoso', 'title' => 'Ketua Majelis', 'organization' => 'GKI Contoh', 'email' => 'budi@contoh.example', 'unsub_token' => str_repeat('x', 40)]);
        $account = ($data['account_id'] ?? null) ? Account::find($data['account_id']) : null;
        $account ??= new Account(['name' => 'Tim '.setting('site_name', config('app.name')), 'email' => (string) config('mail.from.address')]);
        [$subject, $body] = $renderer->render($data['subject'] ?? '', $data['body'] ?? '', $contact, $account);

        return response()->json(['subject' => $subject, 'body' => $body, 'to' => $contact->email]);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, TemplateRenderer $renderer): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'segment_id' => ['nullable', 'integer', 'exists:outreach_segments,id'],
            'subject' => ['required', 'string', 'max:250'],
            'body' => ['required', 'string', 'max:8000'],
            'is_active' => ['boolean'],
        ], ['subject.required' => 'Subjek wajib diisi.', 'body.required' => 'Isi email wajib diisi.']);
        if ($unknown = $renderer->unknownVariables($data['subject'], $data['body'])) {
            throw ValidationException::withMessages(['body' => 'Variabel tidak dikenal: {'.implode('}, {', $unknown).'}. Yang tersedia: {'.implode('}, {', array_keys(Template::VARIABLES)).'}.']);
        }

        return $data;
    }
}
