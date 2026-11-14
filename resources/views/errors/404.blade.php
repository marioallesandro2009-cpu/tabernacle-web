@php
    $seo = \App\Support\Seo::make(t('e404.title'), t('e404.text'), noindex: true);
    try {
        $popular = collect(\App\Support\SiteData::featuredCards())->take(3);
        if ($popular->isEmpty()) {
            $popular = collect(\App\Support\SiteData::tourCards())->take(3);
        }
    } catch (\Throwable) {
        $popular = collect(); // the error page itself must never fail
    }
    $en = app()->getLocale() === 'en';
    $links = [
        [lurl('/packages'), $en ? 'All packages' : 'Semua paket'],
        [lurl('/destinations'), $en ? 'Destinations' : 'Destinasi'],
        [lurl('/blog'), $en ? 'Travel articles' : 'Artikel perjalanan'],
        [lurl('/contact'), $en ? 'Contact us' : 'Hubungi kami'],
    ];
@endphp
@extends('site.layout', ['solidNav' => true, 'bodyClass' => 'page-404'])

@section('content')
<section class="pk-hero e404">
  <div class="wrap grid">
    <p class="kicker mono">404</p>
    <h1 class="display h-xl"><span class="ln"><span>{{ t('e404.title') }}</span></span></h1>
    <div class="pk-intro">
      <p>{{ t('e404.text') }}</p>
      <form class="e404-find" action="{{ lurl('/packages') }}#list" method="get" role="search">
        <label class="sr-only" for="e404-q">{{ $en ? 'Search packages' : 'Cari paket' }}</label>
        <input id="e404-q" type="search" name="q" placeholder="{{ $en ? 'Search a destination or package…' : 'Cari destinasi atau paket…' }}" autocomplete="off">
        <button class="btn btn-navy" type="submit">{{ $en ? 'Search' : 'Cari' }}</button>
      </form>
      <ul class="e404-links">
        @foreach ($links as [$href, $label])
          <li><a href="{{ $href }}">{{ $label }}</a></li>
        @endforeach
      </ul>
      <p><a class="btn" href="{{ lurl('/') }}">{{ t('e404.back') }} <svg aria-hidden="true"><use href="#arrow"/></svg></a></p>
    </div>
  </div>
</section>
@if ($popular->isNotEmpty())
<section class="sec pk-list">
  <div class="wrap">
    <div class="chapter"><b>{{ $en ? 'Popular packages' : 'Paket populer' }}</b><a class="mono" href="{{ lurl('/packages') }}">{{ t('tour.all_packages') }}</a></div>
    <div class="pkgs">
      @foreach ($popular as $c)
        @include('site.partials.tour-tile', ['c' => $c, 'span' => 4])
      @endforeach
    </div>
  </div>
</section>
@endif
@endsection
