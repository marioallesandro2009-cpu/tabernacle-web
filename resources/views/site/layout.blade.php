@php
    $bodyClass = $bodyClass ?? '';
    $faviconId = setting('favicon');
    $faviconMime = $faviconId ? (media_map()[(int) $faviconId]['mime'] ?? 'image/png') : null;
    $logoLight = setting('logo_light');
    $logoDark = setting('logo_dark');
    $here = request()->getRequestUri();
    $siteName = (string) setting('site_name', config('app.name'));
    $siteSuffix = (string) setting('site_suffix', '');
    $tw = [
        'lang' => app()->getLocale(),
        'wa' => preg_replace('/\D/', '', (string) setting('wa_number', '')),
        'csrf' => csrf_token(),
        'msg' => ['plan' => t('wa.plan'), 'pkg' => t('wa.pkg')],
    ];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" class="is-loading">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@include('site.partials.seo')
<meta name="theme-color" content="{{ setting('color_navy', '#0b1a5c') }}">
@if ($faviconId)
<link rel="icon" href="{{ media_url($faviconId) }}" type="{{ $faviconMime }}">
@endif
@stack('head')
<link rel="stylesheet" href="{{ asset_v('assets/css/style.css') }}">
<link rel="stylesheet" href="{{ asset_v('assets/css/site.css') }}">
<link rel="stylesheet" href="{{ asset_v('assets/css/corporate.css') }}">
<style>:root{--navy:{{ setting('color_navy', '#0b1a5c') }};--navy-deep:{{ setting('color_navy_deep', '#071140') }};--amber:{{ setting('color_amber', '#e8973f') }};--amber-deep:{{ setting('color_amber_deep', '#b8661c') }};--cream:{{ setting('color_cream', '#ffffff') }};--sand:{{ setting('color_sand', '#f4f5f7') }};--paper:{{ setting('color_paper', '#ffffff') }};--teal:{{ setting('color_teal', '#17a08c') }}}</style>
<noscript><style>.gates{display:none}</style></noscript>
{!! with_nonce(setting('tracking_head')) !!}
</head>
<body class="{{ $bodyClass }}">
@if (! empty($preview))
  {{-- admin-only notice, never shown to visitors --}}
  <div role="status" style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:9999;background:#111;color:#fff;font:600 13px/1.3 system-ui,sans-serif;padding:10px 16px;border-radius:999px;box-shadow:0 6px 24px rgba(0,0,0,.3)">Pratinjau admin: halaman ini belum tentu terlihat oleh pengunjung</div>
@endif
{!! with_nonce(setting('tracking_body')) !!}
<a class="skip" href="#main">{{ t('nav.skip') }}</a>

<svg style="position:absolute;width:0;height:0" aria-hidden="true">
  <defs>
    <mask id="mark-cut" maskUnits="userSpaceOnUse" x="-104" y="-104" width="208" height="208">
      <rect x="-104" y="-104" width="208" height="208" fill="#fff"/>
      <g stroke="#000" fill="none"><path d="M-41-110V110M43-110V110" stroke-width="3.4"/><path d="M0-68V-15M0 20V73" stroke-width="2.4"/></g>
    </mask>
    <symbol id="mark" viewBox="-104 -104 208 208"><circle r="100" fill="currentColor" mask="url(#mark-cut)"/></symbol>
    <symbol id="arrow" viewBox="0 0 22 10"><path d="M0 5h20M16 1l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.2"/></symbol>
    <symbol id="play" viewBox="0 0 20 20"><path d="M6 3.5v13l11-6.5z" fill="currentColor"/></symbol>
  </defs>
</svg>

<div class="gates" aria-hidden="true">
  @foreach (['a', 'b', 'c'] as $g)
  <div class="gate gate-{{ $g }}"><div class="gate-mark"><svg viewBox="0 0 208 208"><use href="#mark" width="208" height="208"/></svg></div></div>
  @endforeach
</div>

<header class="nav" @if (! empty($solidNav)) data-solid @endif>
  <div class="wrap nav-in">
    <a class="brand" href="{{ url('/') }}" aria-label="{{ t('brand.aria') }}">
      @if ($logoLight || $logoDark)
        @if ($logoLight)<img class="logo-img logo-light" {!! media_attrs($logoLight, '160px', $siteName) !!}>@endif
        @if ($logoDark)<img class="logo-img logo-dark" {!! media_attrs($logoDark, '160px', $siteName) !!}>@endif
      @else
        <svg aria-hidden="true"><use href="#mark"/></svg>
        <span><b>{{ strtoupper($siteName) }}</b><small>{{ $siteSuffix }}</small></span>
      @endif
    </a>
    <nav aria-label="{{ t('nav.main') }}">
      <ul>
        @foreach (\App\Support\SiteData::menu('header') as $item)
          <li @if ($item->children->isNotEmpty()) class="has-sub" @endif>
            <a class="nav-link" href="{{ $item->url }}" @if ($item->new_tab) target="_blank" rel="noopener" @endif @if (rtrim(parse_url($item->url, PHP_URL_PATH) ?: '', '/') === rtrim(request()->getPathInfo(), '/') && ! str_contains($item->url, '#')) aria-current="page" @endif>{{ tr($item->label) }}</a>
            @if ($item->children->isNotEmpty())
              <ul class="sub">
                @foreach ($item->children as $child)
                  <li><a href="{{ $child->url }}" @if ($child->new_tab) target="_blank" rel="noopener" @endif>{{ tr($child->label) }}</a></li>
                @endforeach
              </ul>
            @endif
          </li>
        @endforeach
        <li><a class="nav-cta" href="{{ wa_link(t('wa.plan')) }}" data-wa>{{ t('nav.cta') }}</a></li>
      </ul>
    </nav>
    <div class="lang" role="group" aria-label="{{ t('lang.choose') }}">
      <a href="{{ url('/lang/id') }}?back={{ urlencode($here) }}" hreflang="id" lang="id" @if (app()->getLocale() === 'id') aria-current="true" @endif>ID</a>
      <a href="{{ url('/lang/en') }}?back={{ urlencode($here) }}" hreflang="en" lang="en" @if (app()->getLocale() === 'en') aria-current="true" @endif>EN</a>
    </div>
    <button class="burger" type="button" aria-label="{{ t('nav.menu') }}" aria-expanded="false"><i></i><i></i></button>
  </div>
</header>

<div class="menu">
  <ul>
    @unless (request()->is('/'))
      <li><a class="big" href="{{ url('/') }}">{{ t('nav.home') }}</a></li>
    @endunless
    @foreach (\App\Support\SiteData::menu('header') as $item)
      <li><a class="big" href="{{ $item->url }}" @if ($item->new_tab) target="_blank" rel="noopener" @endif>{{ tr($item->label) }}</a></li>
    @endforeach
  </ul>
  <p class="mono">{{ setting('wa_display') }}<br>{{ setting('contact_email') }}</p>
</div>

<main id="main">
@yield('content')
</main>

<footer class="foot">
  <div class="wrap">
    <div class="foot-top">
      <p class="foot-tag">"{{ tr(setting('tagline')) }}"</p>
      <p class="foot-addr">{!! nl2br(e((string) setting('address_footer', ''))) !!}</p>
      <div class="foot-nav mono">
        @unless (request()->is('/'))
          <a href="{{ url('/') }}">{{ t('nav.home') }}</a>
        @endunless
        @foreach (\App\Support\SiteData::menu('footer') as $item)
          <a href="{{ $item->url }}" @if ($item->new_tab) target="_blank" rel="noopener" @endif>{{ tr($item->label) }}</a>
        @endforeach
      </div>
    </div>
    <div class="foot-word">{{ $siteName }}</div>
    <div class="foot-base mono"><span>{{ setting('company_name') }}</span><span>{{ setting('wa_display') }}</span></div>
  </div>
</footer>

<div class="lb" role="dialog" aria-modal="true" aria-label="{{ t('common.photo') }}">
  <button class="x" type="button">{{ t('common.close') }}</button>
  <div class="lb-img"><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt=""></div>
  <div class="lb-bar"><span class="lb-cap lede"></span><span class="lb-meta mono"></span></div>
</div>
<div class="vb" role="dialog" aria-modal="true" aria-label="{{ t('common.film') }}">
  <button class="x" type="button">{{ t('common.close') }}</button>
  <video controls playsinline preload="none"></video>
</div>

<script nonce="{{ csp_nonce() }}">window.TW = @json($tw);</script>
{{-- calm corporate look: the motion libraries (GSAP, Lenis) are no longer loaded; main.js then runs without animations --}}
<script nonce="{{ csp_nonce() }}" src="{{ asset_v('assets/js/main.js') }}"></script>
@if (is_file(public_path('build/manifest.json')))
  @vite(['resources/js/site.js'])
@endif
@stack('scripts')
</body>
</html>
