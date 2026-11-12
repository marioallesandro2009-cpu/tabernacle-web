<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Content;
use App\Support\MediaInfo;
use App\Support\Schema;
use App\Support\Settings;
use App\Support\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $fields = SettingsRegistry::allFields();
        $stored = Settings::all();
        $values = Schema::forForm($fields, collect($fields)->mapWithKeys(fn ($f) => [$f['key'] => $stored[$f['key']] ?? null])->all());

        return Inertia::render('Admin/Settings', [
            'groups' => SettingsRegistry::groups(),
            'values' => $values,
            'media' => MediaInfo::for($fields, $values),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $fields = SettingsRegistry::allFields();
        $request->validate(Schema::rules($fields));
        $data = Schema::clean($fields, $request->all());
        $wa = preg_replace('/\D+/', '', (string) ($data['wa_number'] ?? ''));
        $data['wa_number'] = str_starts_with($wa, '0') ? '62'.substr($wa, 1) : $wa;

        foreach ($data as $key => $value) {
            Settings::set($key, $value);
        }
        Settings::forget();
        Content::bump();
        ActivityLog::record('update', 'Pengaturan situs', null, 'Pengaturan situs');

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
