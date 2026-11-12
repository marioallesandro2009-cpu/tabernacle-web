@php($seo = $seo ?? \App\Support\Seo::make(null, null))
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
@if (! empty($seo['noindex']))
<meta name="robots" content="noindex, follow">
@else
<meta name="robots" content="index, follow, max-image-preview:large">
@endif
<meta property="og:site_name" content="{{ setting('site_name') }}">
<meta property="og:type" content="{{ $seo['type'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:locale" content="{{ app()->getLocale() === 'id' ? 'id_ID' : 'en_US' }}">
@if (! empty($seo['image']))
<meta property="og:image" content="{{ $seo['image'] }}">
@endif
<meta name="twitter:card" content="{{ ! empty($seo['image']) ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
@if (! empty($seo['image']))
<meta name="twitter:image" content="{{ $seo['image'] }}">
@endif
<link rel="alternate" type="application/rss+xml" title="{{ setting('site_name') }} Blog" href="{{ url('/feed.xml') }}">
@foreach ($seo['schema'] ?? [] as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
