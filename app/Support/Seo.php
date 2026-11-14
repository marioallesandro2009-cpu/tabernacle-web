<?php

namespace App\Support;

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
            // always one space before the suffix, whether or not the admin typed one
            // (the settings form trims leading spaces)
            $suffix = trim((string) setting('title_suffix', ''));
            if ($suffix !== '') {
                $title .= ' '.$suffix;
            }
        }
        $description = trim(strip_tags((string) $description)) ?: tr(setting('default_description'));
        $imageUrl = media_url($image ?: setting('og_default_image'), 1600);

        return [
            'title' => $title,
            'description' => self::trimDescription($description),
            'canonical' => Locale::canonical($canonical ?: url()->current()),
            'image' => $imageUrl,
            'type' => $type,
            'noindex' => $noindex,
            'schema' => $schema,
        ];
    }

    /** About 160 characters, cut at a word and ended with "…" (what search results show). */
    public static function trimDescription(string $text, int $max = 160): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:-–').'…';
    }

    /** @return array<string, mixed> */
    public static function organization(): array
    {
        $sameAs = array_values(array_filter([setting('social_instagram'), setting('social_tiktok'), setting('social_facebook'), setting('social_youtube')]));
        $logo = media_url(setting('logo_dark') ?: setting('logo_light'));
        $phone = setting('wa_display') ?: (setting('wa_number') ? '+'.setting('wa_number') : null);

        return array_filter([
            '@context' => 'https://schema.org', '@type' => 'TravelAgency',
            'name' => trim(setting('site_name', '').' '.setting('site_suffix', '')) ?: config('app.name'),
            'legalName' => setting('company_name'), 'url' => url('/'), 'logo' => $logo,
            'image' => media_url(setting('og_default_image'), 1600),
            'description' => tr(setting('default_description')),
            'email' => setting('contact_email'), 'telephone' => $phone,
            'foundingDate' => preg_match('/^(19|20)\d{2}$/', (string) setting('company_founded')) ? (string) setting('company_founded') : null,
            'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => tr(setting('address'), 'id') ?: null, 'addressLocality' => setting('address_city'),
                'addressRegion' => setting('address_region'), 'postalCode' => setting('address_postal_code'), 'addressCountry' => 'ID']),
            'contactPoint' => [array_filter(['@type' => 'ContactPoint', 'contactType' => 'customer service', 'telephone' => $phone, 'email' => setting('contact_email'),
                'areaServed' => 'ID', 'availableLanguage' => ['Indonesian', 'English']])],
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
