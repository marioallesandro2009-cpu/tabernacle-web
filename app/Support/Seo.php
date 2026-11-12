<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Builds the head data of a public page: title, description, canonical, Open Graph and schema.org. */
class Seo
{
    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<string, mixed>
     */
    public static function make(?string $title, ?string $description, ?int $image = null, ?string $canonical = null, string $type = 'website', bool $noindex = false, array $schema = [], bool $titleIsFinal = true): array
    {
        $title = trim((string) $title);
        if ($title === '') {
            $title = (string) setting('site_name', config('app.name')).' '.tr(['en' => 'Tour & Travel', 'id' => 'Tour & Travel']);
        } elseif (! $titleIsFinal) {
            $title .= (string) setting('title_suffix', '');
        }
        $description = trim(strip_tags((string) $description)) ?: tr(setting('default_description'));
        $imageUrl = media_url($image ?: setting('og_default_image'), 1600);

        return [
            'title' => $title,
            'description' => Str::limit($description, 300, ''),
            'canonical' => $canonical ?: url()->current(),
            'image' => $imageUrl,
            'type' => $type,
            'noindex' => $noindex,
            'schema' => $schema,
        ];
    }

    /** @return array<string, mixed> */
    public static function organization(): array
    {
        $sameAs = array_values(array_filter([setting('social_instagram'), setting('social_tiktok'), setting('social_facebook'), setting('social_youtube')]));
        $logo = media_url(setting('logo_dark') ?: setting('logo_light'));

        return array_filter([
            '@context' => 'https://schema.org', '@type' => 'TravelAgency',
            'name' => trim(setting('site_name', '').' '.setting('site_suffix', '')) ?: config('app.name'),
            'legalName' => setting('company_name'), 'url' => url('/'), 'logo' => $logo,
            'image' => media_url(setting('og_default_image'), 1600),
            'description' => tr(setting('default_description')),
            'email' => setting('contact_email'), 'telephone' => setting('wa_display') ?: ('+'.setting('wa_number')),
            'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => tr(setting('address')), 'addressCountry' => 'ID']),
            'sameAs' => $sameAs ?: null, 'slogan' => tr(setting('tagline')),
        ]);
    }

    /** @param array<int, array{0: string, 1: string}> $items [[name, url], ...] @return array<string, mixed> */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($i, $n) => ['@type' => 'ListItem', 'position' => $n + 1, 'name' => $i[0], 'item' => $i[1]])->all(),
        ];
    }
}
