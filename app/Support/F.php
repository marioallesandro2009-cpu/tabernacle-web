<?php

namespace App\Support;

/**
 * Field builders. One schema describes a form for the admin UI (Vue), the validation
 * rules and the cleaning of what gets saved, so every module stays consistent.
 *
 * @phpstan-type Field array<string, mixed>
 */
class F
{
    /** @param array<string, mixed> $o @return array<string, mixed> */
    protected static function make(string $type, string $key, string $label, array $o = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type] + $o;
    }

    public static function text(string $key, string $label, array $o = []): array
    {
        return self::make('text', $key, $label, $o);
    }

    public static function textarea(string $key, string $label, array $o = []): array
    {
        return self::make('textarea', $key, $label, $o);
    }

    public static function url(string $key, string $label, array $o = []): array
    {
        return self::make('url', $key, $label, $o);
    }

    public static function email(string $key, string $label, array $o = []): array
    {
        return self::make('email', $key, $label, $o);
    }

    public static function number(string $key, string $label, array $o = []): array
    {
        return self::make('number', $key, $label, $o);
    }

    public static function toggle(string $key, string $label, array $o = []): array
    {
        return self::make('toggle', $key, $label, $o + ['default' => false]);
    }

    public static function date(string $key, string $label, array $o = []): array
    {
        return self::make('date', $key, $label, $o);
    }

    public static function datetime(string $key, string $label, array $o = []): array
    {
        return self::make('datetime', $key, $label, $o);
    }

    public static function color(string $key, string $label, array $o = []): array
    {
        return self::make('color', $key, $label, $o);
    }

    public static function slug(string $key, string $label, string $source, array $o = []): array
    {
        return self::make('slug', $key, $label, ['source' => $source] + $o);
    }

    public static function rich(string $key, string $label, array $o = []): array
    {
        return self::make('rich', $key, $label, $o);
    }

    /** @param array<int, array{value: string|int, label: string}> $options */
    public static function select(string $key, string $label, array $options, array $o = []): array
    {
        return self::make('select', $key, $label, ['options' => $options] + $o);
    }

    /** Translatable single line (English + Indonesian). */
    public static function t(string $key, string $label, array $o = []): array
    {
        return self::make('t_text', $key, $label, $o);
    }

    /** Translatable paragraph. */
    public static function tarea(string $key, string $label, array $o = []): array
    {
        return self::make('t_textarea', $key, $label, $o);
    }

    /** Translatable text that may contain <em>, <strong>, <b>, <br> (short headings). */
    public static function thtml(string $key, string $label, array $o = []): array
    {
        return self::make('t_html', $key, $label, $o);
    }

    /** Translatable rich text (editor). */
    public static function trich(string $key, string $label, array $o = []): array
    {
        return self::make('t_rich', $key, $label, $o);
    }

    public static function image(string $key, string $label, array $o = []): array
    {
        return self::make('image', $key, $label, $o);
    }

    public static function images(string $key, string $label, array $o = []): array
    {
        return self::make('images', $key, $label, $o);
    }

    /** A file from the media library: pdf, video or svg. @param array<string, mixed> $o */
    public static function file(string $key, string $label, string $kind = 'file', array $o = []): array
    {
        return self::make('file', $key, $label, ['kind' => $kind] + $o);
    }

    /** List of plain strings. */
    public static function list(string $key, string $label, array $o = []): array
    {
        return self::make('list', $key, $label, $o);
    }

    /** @param array<int, array<string, mixed>> $fields */
    public static function repeat(string $key, string $label, array $fields, array $o = []): array
    {
        return self::make('repeater', $key, $label, ['fields' => $fields, 'item_label' => $o['item_label'] ?? null] + $o);
    }

    /** Many-to-many selection. $options: [{value,label}] */
    public static function multi(string $key, string $label, array $options, array $o = []): array
    {
        return self::make('multi', $key, $label, ['options' => $options] + $o);
    }
}
