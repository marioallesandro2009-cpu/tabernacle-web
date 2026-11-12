<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Turns a field schema (see F) into validation rules and cleans submitted values. */
class Schema
{
    public const TRANSLATABLE = ['t_text', 't_textarea', 't_html', 't_rich'];

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function rules(array $fields, string $prefix = ''): array
    {
        $rules = [];
        foreach ($fields as $f) {
            $k = $prefix.$f['key'];
            $req = ! empty($f['required']) ? 'required' : 'nullable';
            $max = $f['max'] ?? null;
            switch ($f['type']) {
                case 'text':
                    $rules[$k] = [$req, 'string', 'max:'.($max ?? 255)];
                    break;
                case 'textarea':
                    $rules[$k] = [$req, 'string', 'max:'.($max ?? 8000)];
                    break;
                case 'rich':
                    $rules[$k] = [$req, 'string', 'max:'.($max ?? 300000)];
                    break;
                case 'url':
                    $rules[$k] = [$req, 'string', 'max:500', fn ($a, $v, $fail) => preg_match('/^\s*(javascript|data|vbscript):/i', (string) $v) ? $fail('Tautan tidak valid.') : null];
                    break;
                case 'email':
                    $rules[$k] = [$req, 'email', 'max:255'];
                    break;
                case 'number':
                    $rules[$k] = [$req, 'numeric', 'min:'.($f['min'] ?? 0), 'max:'.($max ?? 999999999999)];
                    break;
                case 'toggle':
                    $rules[$k] = ['boolean'];
                    break;
                case 'date':
                case 'datetime':
                    $rules[$k] = [$req, 'date'];
                    break;
                case 'color':
                    $rules[$k] = [$req, 'regex:/^#[0-9a-fA-F]{6}$/'];
                    break;
                case 'slug':
                    $rules[$k] = ['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:160'];
                    break;
                case 'select':
                    $rules[$k] = [$req, Rule::in(array_map(fn ($o) => (string) $o['value'], $f['options']))];
                    break;
                case 'image':
                case 'file':
                    $rules[$k] = [$req, 'integer', 'exists:media,id'];
                    break;
                case 'images':
                    $rules[$k] = ['nullable', 'array', 'max:'.($max ?? 60)];
                    $rules[$k.'.*'] = ['integer', 'exists:media,id'];
                    break;
                case 'list':
                    $rules[$k] = ['nullable', 'array', 'max:'.($max ?? 80)];
                    $rules[$k.'.*'] = ['nullable', 'string', 'max:500'];
                    break;
                case 'multi':
                    $rules[$k] = ['nullable', 'array'];
                    $rules[$k.'.*'] = ['integer'];
                    break;
                case 'repeater':
                    $rules[$k] = [$req, 'array', 'max:'.($max ?? 200)];
                    $rules += self::rules($f['fields'], $k.'.*.');
                    break;
                default: // translatable types
                    $rules[$k] = [$req, 'array'];
                    $limit = $max ?? ($f['type'] === 't_rich' ? 100000 : ($f['type'] === 't_textarea' ? 8000 : 1000));
                    $rules[$k.'.en'] = [! empty($f['required']) ? 'required' : 'nullable', 'string', 'max:'.$limit];
                    $rules[$k.'.id'] = ['nullable', 'string', 'max:'.$limit];
            }
        }

        return $rules;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(array $fields, array $input): array
    {
        $out = [];
        foreach ($fields as $f) {
            $k = $f['key'];
            $v = $input[$k] ?? null;
            switch ($f['type']) {
                case 'toggle':
                    $out[$k] = (bool) $v;
                    break;
                case 'number':
                    $out[$k] = ($v === null || $v === '') ? null : (str_contains((string) $v, '.') ? (float) $v : (int) $v);
                    break;
                case 'rich':
                    $out[$k] = ($v === null || $v === '') ? null : clean_html((string) $v);
                    break;
                case 'image':
                case 'file':
                    $out[$k] = $v ? (int) $v : null;
                    break;
                case 'images':
                    $out[$k] = array_values(array_map('intval', (array) $v));
                    break;
                case 'list':
                    $out[$k] = array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) $v), fn ($s) => $s !== ''));
                    break;
                case 'multi':
                    $out[$k] = array_values(array_map('intval', (array) $v));
                    break;
                case 'repeater':
                    $out[$k] = array_values(array_map(fn ($row) => self::clean($f['fields'], (array) $row), (array) $v));
                    break;
                case 'slug':
                    $out[$k] = $v ? Str::slug((string) $v) : null;
                    break;
                case 't_text':
                case 't_textarea':
                case 't_html':
                case 't_rich':
                    $out[$k] = self::cleanTranslatable($f['type'], (array) $v);
                    break;
                default:
                    $out[$k] = ($v === null || $v === '') ? null : (is_string($v) ? trim($v) : $v);
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $v @return array<string, string>|null */
    protected static function cleanTranslatable(string $type, array $v): ?array
    {
        $res = [];
        foreach (['en', 'id'] as $lang) {
            $s = trim((string) ($v[$lang] ?? ''));
            $res[$lang] = match ($type) {
                't_rich' => $s === '' ? '' : clean_html($s),
                't_html' => $s === '' ? '' : clean_html($s, 'inline'),
                default => $s,
            };
        }

        return ($res['en'] === '' && $res['id'] === '') ? null : $res;
    }

    /**
     * Fill in defaults and shape stored values for the form.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function forForm(array $fields, array $values): array
    {
        $out = [];
        foreach ($fields as $f) {
            $k = $f['key'];
            $v = $values[$k] ?? ($f['default'] ?? null);
            $out[$k] = match (true) {
                in_array($f['type'], self::TRANSLATABLE, true) => ['en' => (string) (($v['en'] ?? '')), 'id' => (string) (($v['id'] ?? ''))],
                $f['type'] === 'toggle' => (bool) $v,
                in_array($f['type'], ['images', 'list', 'multi'], true) => array_values((array) ($v ?? [])),
                $f['type'] === 'repeater' => array_values(array_map(fn ($row) => self::forForm($f['fields'], (array) $row), (array) ($v ?? []))),
                default => $v,
            };
        }

        return $out;
    }
}
