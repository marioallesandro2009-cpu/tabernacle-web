<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\UiString;
use App\Support\Content;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Adds interface texts and settings that a newer version of the site introduced.
 * Only keys that do not exist yet are inserted, so nothing an admin edited is ever
 * overwritten, and no content (tours, pages, ...) is touched. Safe on every deploy.
 */
class DefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('seed-data/');
        $added = 0;

        foreach (json_decode((string) file_get_contents($dir.'ui_strings.json'), true) ?: [] as $row) {
            $added += (int) UiString::firstOrCreate(['key' => $row['key']], ['group' => $row['group'], 'text_en' => $row['text_en'], 'text_id' => $row['text_id']])->wasRecentlyCreated;
        }

        foreach (json_decode((string) file_get_contents($dir.'settings.json'), true) ?: [] as $key => $value) {
            // media placeholders (@img/...) belong to the first install only
            if ($this->hasMediaPlaceholder($value)) {
                continue;
            }
            $added += (int) Setting::firstOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)])->wasRecentlyCreated;
        }

        Cache::forget('ui_strings_all');
        Settings::forget();
        Content::bump();
        $this->command?->info($added.' teks/pengaturan baru ditambahkan.');
    }

    protected function hasMediaPlaceholder(mixed $v): bool
    {
        if (is_array($v)) {
            foreach ($v as $x) {
                if ($this->hasMediaPlaceholder($x)) {
                    return true;
                }
            }

            return false;
        }

        return is_string($v) && (str_starts_with($v, '@img/') || str_starts_with($v, '@video/'));
    }
}
