<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Segment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Groups of contacts (for example "Gereja", "Perusahaan"). Each can have its own templates later. */
class SegmentController extends Controller
{
    public function index(): Response
    {
        $segments = Segment::withCount([
            'contacts',
            'contacts as sendable_count' => fn ($q) => $q->sendable(),
        ])->orderBy('name')->get()->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'description' => $s->description,
            'contacts_count' => $s->contacts_count, 'sendable_count' => $s->sendable_count,
        ]);

        return Inertia::render('Admin/Outreach/Segments', [
            'segments' => $segments,
            'unassigned' => Contact::whereNull('segment_id')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Segment::create($this->validated($request, null));

        return back()->with('success', 'Segmen dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $segment = Segment::findOrFail($id);
        $segment->update($this->validated($request, $segment));

        return back()->with('success', 'Segmen disimpan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $segment = Segment::findOrFail($id);
        $n = $segment->contacts()->count();
        $segment->delete(); // contacts stay, without a segment

        return back()->with('success', 'Segmen dihapus.'.($n ? " $n kontak sekarang tanpa segmen." : ''));
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Segment $segment): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('outreach_segments', 'name')->ignore($segment?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'Nama segmen ini sudah ada.']);
    }
}
