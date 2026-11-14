@extends('site.layout', ['solidNav' => true])

@php($en = app()->getLocale() === 'en')
@if ($pillar->cover_id)
  @push('head'){!! media_preload($pillar->cover_id, '(max-width: 860px) 100vw, 50vw') !!}@endpush
@endif

@section('content')
<section class="pk-hero pillar-hero">
  <div class="wrap grid">
    <p class="kicker mono"><a href="{{ lurl('/layanan') }}">{{ $en ? 'Services' : 'Layanan' }}</a></p>
    <h1 class="display h-xl"><span class="ln"><span>{{ tr($pillar->title) }}</span></span></h1>
    @if (tr($pillar->intro))<div class="pk-intro prose">{!! tr($pillar->intro) !!}</div>@endif
    @if ($pillar->cover_id)<figure class="pillar-cover pic"><img {!! media_attrs($pillar->cover_id, '(max-width: 860px) 100vw, 50vw', tr($pillar->title)) !!} fetchpriority="high"></figure>@endif
  </div>
</section>

@if (count($cards))
<section class="sec pk-list">
  <div class="wrap">
    <div class="chapter"><b>{{ $en ? 'Packages' : 'Paket' }}</b><span class="mono">{{ count($cards) }}</span></div>
    <div class="pkgs">
      @foreach ($cards as $i => $c)
        @include('site.partials.tour-tile', ['c' => $c, 'span' => \App\Support\SiteData::SPANS[$i % 10], 'eager' => $i === 0 && ! $pillar->cover_id])
      @endforeach
    </div>
  </div>
</section>
@endif

@if ($destinations->isNotEmpty())
<section class="sec pk-list dest-sec">
  <div class="wrap">
    <div class="chapter"><b>{{ t('dest.title') }}</b><a class="mono" href="{{ lurl('/destinations') }}">{{ $en ? 'All destinations' : 'Semua destinasi' }}</a></div>
    <div class="dest-grid">
      @foreach ($destinations as $d)
        <a class="dest" href="{{ lurl('/destinations/'.$d->slug) }}"><b>{{ tr($d->name) }}</b></a>
      @endforeach
    </div>
  </div>
</section>
@endif

@if ($cases->isNotEmpty())
<section class="sec on-paper">
  <div class="wrap">
    <div class="chapter"><b>{{ $en ? 'Case studies' : 'Studi kasus' }}</b><a class="mono" href="{{ lurl('/studi-kasus') }}">{{ $en ? 'All case studies' : 'Semua studi kasus' }}</a></div>
    @include('site.partials.case-cards', ['cases' => $cases])
  </div>
</section>
@endif

@if ($posts->isNotEmpty())
<section class="sec">
  <div class="wrap">
    <div class="chapter"><b>{{ t('blog.related') }}</b><a class="mono" href="{{ lurl('/blog') }}">{{ t('blog.all') }}</a></div>
    @include('site.partials.post-cards', ['posts' => $posts])
  </div>
</section>
@endif

<section class="sec">
  <div class="wrap pillar-cta">
    <p class="lede">{{ $en ? 'Planning a program like this? Tell us what you need and we will prepare a proposal.' : 'Merencanakan program seperti ini? Ceritakan kebutuhan Anda, kami siapkan penawarannya.' }}</p>
    <a class="btn btn-navy" href="{{ lurl('/contact') }}">{{ $en ? 'Contact us' : 'Hubungi kami' }} <svg aria-hidden="true"><use href="#arrow"/></svg></a>
  </div>
</section>
@endsection
