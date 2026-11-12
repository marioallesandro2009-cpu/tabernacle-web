<?php

namespace App\Support;

use App\Models\Media;

/** Looks up the media items a form points at so the pickers can show a preview. */
class MediaInfo
{
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @return array<int, array<string, mixed>>
     */
    public static function for(array $fields, array $values): array
    {
        $ids = [];
        $walk = function (array $fields, array $values) use (&$walk, &$ids) {
            foreach ($fields as $f) {
                $v = $values[$f['key']] ?? null;
                if (in_array($f['type'], ['image', 'file'], true) && is_numeric($v)) {
                    $ids[] = (int) $v;
                } elseif ($f['type'] === 'images') {
                    $ids = [...$ids, ...array_map('intval', (array) $v)];
                } elseif ($f['type'] === 'repeater') {
                    foreach ((array) $v as $row) {
                        $walk($f['fields'], (array) $row);
                    }
                }
            }
        };
        $walk($fields, $values);

        return Media::whereIn('id', array_unique($ids))->get()->keyBy('id')->map(fn (Media $m) => self::row($m))->all();
    }

    /** @return array<string, mixed> */
    public static function row(Media $m): array
    {
        return ['id' => $m->id, 'name' => $m->name, 'alt' => $m->alt, 'kind' => $m->kind, 'url' => $m->url, 'thumb' => $m->thumb, 'mime' => $m->mime];
    }
}
