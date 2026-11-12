<?php

use App\Models\Media;
use App\Models\Section;
use App\Services\MediaService;
use App\Support\Content;
use Illuminate\Database\Migrations\Migration;

/**
 * Replaces the placeholder CEO (Daniel Hartono) on the About page with the real one,
 * Vazia Telelepta. Only touches a section that still shows the placeholder name, so
 * anything an admin already edited is left alone. Fresh installs get this from the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sections = Section::where('type', 'ceo')->get()->filter(fn (Section $s) => ($s->data['last'] ?? null) === 'Hartono' && ($s->data['first'] ?? null) === 'Daniel');
        if ($sections->isEmpty()) {
            return;
        }

        $file = database_path('seed-assets/img/ceo-vazia-telelepta.jpg');
        $name = 'ceo-vazia-telelepta.jpg';
        $media = Media::where('name', $name)->where('folder', 'tentang')->first();
        if (! $media && is_file($file)) {
            $media = app(MediaService::class)->import($file, $name, 'tentang', 'Vazia Telelepta, Founder and CEO of Tabernacle Ways');
        }

        foreach ($sections as $s) {
            $data = $s->data;
            $data['first'] = 'Vazia';
            $data['last'] = 'Telelepta';
            $data['sign'] = ['en' => 'Vazia Telelepta, Founder & CEO', 'id' => 'Vazia Telelepta, Pendiri & CEO'];
            $data['photo_alt'] = ['en' => 'Vazia Telelepta, Founder and CEO, at the ancient ruins of Hierapolis, Pamukkale', 'id' => 'Vazia Telelepta, Pendiri dan CEO, di reruntuhan kuno Hierapolis, Pamukkale'];
            if ($media) {
                $data['photo'] = $media->id;
            }
            $s->update(['data' => $data, 'name' => 'Pendiri / CEO']);
        }
        Content::bump();
    }

    public function down(): void
    {
        // content change only; nothing to undo automatically
    }
};
