<?php

namespace App\Support;

use App\Models\Destination;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Section;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Finds where each media item is used, so unused ones can be cleaned up safely. */
class MediaUsage
{
    /** @var array<int, array<int, string>>|null */
    protected static ?array $map = null;

    /** @return array<int, array<int, string>> media id => list of places */
    public static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }
        $map = [];
        $add = function (mixed $id, string $where) use (&$map) {
            if (is_numeric($id) && (int) $id > 0) {
                $map[(int) $id][] = $where;
            }
        };

        foreach (SettingsRegistry::allFields() as $f) {
            if (in_array($f['type'], ['image', 'file'], true)) {
                $add(Settings::get($f['key']), 'Pengaturan situs: '.$f['label']);
            }
        }
        foreach (Page::get(['id', 'key', 'og_image_id']) as $p) {
            $add($p->og_image_id, 'Halaman '.$p->key.' (OG image)');
        }
        foreach (Section::with('page:id,key')->get() as $s) {
            self::walk(SectionRegistry::fields($s->type), (array) $s->data, fn ($id) => $add($id, 'Halaman '.$s->page->key.' › '.($s->name ?: $s->type)));
        }
        foreach (Destination::get(['id', 'name', 'cover_id']) as $d) {
            $add($d->cover_id, 'Destinasi: '.tr($d->name));
        }
        foreach (Tour::get(['id', 'name', 'cover_id', 'pdf_id', 'gallery']) as $t) {
            $add($t->cover_id, 'Paket: '.$t->name);
            $add($t->pdf_id, 'Paket (PDF): '.$t->name);
            foreach ((array) $t->gallery as $g) {
                $add($g, 'Paket (galeri): '.$t->name);
            }
        }
        foreach (Testimonial::get(['id', 'name', 'photo_id']) as $t) {
            $add($t->photo_id, 'Testimoni: '.$t->name);
        }
        foreach (TeamMember::get(['id', 'name', 'photo_id']) as $t) {
            $add($t->photo_id, 'Tim: '.$t->name);
        }
        foreach (Gallery::get() as $g) {
            $add($g->cover_id, 'Galeri: '.tr($g->title));
            foreach ((array) $g->items as $it) {
                $add($it['media_id'] ?? null, 'Galeri: '.tr($g->title));
            }
        }
        foreach (Post::get(['id', 'title', 'cover_id', 'og_image_id', 'content']) as $p) {
            $add($p->cover_id, 'Artikel: '.$p->title);
            $add($p->og_image_id, 'Artikel (OG): '.$p->title);
        }
        foreach (User::whereNotNull('avatar_id')->get(['id', 'name', 'avatar_id']) as $u) {
            $add($u->avatar_id, 'Avatar: '.$u->name);
        }

        // images placed inside rich text are referenced by path, not by id
        $paths = DB::table('media')->pluck('path', 'id');
        $texts = [
            ...Post::pluck('content')->all(),
            ...Section::pluck('data')->map(fn ($d) => (string) $d)->all(),
            ...Tour::pluck('itinerary')->map(fn ($d) => (string) $d)->all(),
        ];
        $blob = implode("\n", array_map(fn ($t) => str_replace('\/', '/', (string) $t), $texts));
        foreach ($paths as $id => $path) {
            if ($path && str_contains($blob, 'uploads/'.$path)) {
                $map[(int) $id][] = 'Dipakai di dalam teks';
            }
        }

        return self::$map = array_map(fn ($v) => array_values(array_unique($v)), $map);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     */
    protected static function walk(array $fields, array $values, \Closure $found): void
    {
        foreach ($fields as $f) {
            $v = $values[$f['key']] ?? null;
            if (in_array($f['type'], ['image', 'file'], true)) {
                $found($v);
            } elseif ($f['type'] === 'images') {
                foreach ((array) $v as $id) {
                    $found($id);
                }
            } elseif ($f['type'] === 'repeater') {
                foreach ((array) $v as $row) {
                    self::walk($f['fields'], (array) $row, $found);
                }
            }
        }
    }

    /** @return array<int, string> */
    public static function of(int $id): array
    {
        self::$map = null;

        return self::map()[$id] ?? [];
    }

    /** @return array<int, int> */
    public static function usedIds(): array
    {
        self::$map = null;

        return array_keys(self::map());
    }
}
