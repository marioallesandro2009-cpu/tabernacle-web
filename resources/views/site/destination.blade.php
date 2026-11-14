@extends('site.layout', ['solidNav' => true])

@section('content')
<section class="pk-hero">
  <div class="wrap grid">
    <p class="kicker mono"><a href="{{ lurl('/destinations') }}">{{ t('dest.title') }}</a> / {{ t('region.'.$dest->region) }}</p>
    <h1 class="display h-xl"><span class="ln"><span>{{ tr($dest->name) }}</span></span></h1>
    @if (tr($dest->intro))<div class="pk-intro prose" data-fade>{!! tr($dest->intro) !!}</div>@endif
  </div>
</section>
<section class="sec pk-list">
  <div class="wrap">
    <div class="chapter"><b>{{ t('dest.tours', ['name' => tr($dest->name)]) }}</b><span class="mono">{{ count($cards) }}</span></div>
    @if (count($cards))
      <div class="pkgs">
        @foreach ($cards as $i => $c)
          @include('site.partials.tour-tile', ['c' => $c, 'span' => \App\Support\SiteData::SPANS[$i % 10], 'eager' => $i === 0])
        @endforeach
      </div>
    @else
      <p class="pk-empty">{{ t('dest.empty') }}</p>
    @endif
  </div>
</section>
@if ($posts->isNotEmpty())
<section class="sec on-paper">
  <div class="wrap">
    <div class="chapter"><b>{{ t('blog.related') }}</b><a class="mono" href="{{ lurl('/blog') }}">{{ t('blog.all') }}</a></div>
    <div class="post-grid">
      @foreach ($posts as $p)
        <a class="post-card" href="{{ lurl('/blog/'.$p->slug) }}"><div class="pic">@if ($p->cover_id)<img {!! media_attrs($p->cover_id, '(max-width: 860px) 100vw, 33vw', $p->title) !!} loading="lazy">@endif</div><p class="mono">{{ $p->published_at->locale(app()->getLocale())->translatedFormat('d M Y') }}</p><h3>{{ $p->title }}</h3></a>
      @endforeach
    </div>
  </div>
</section>
@endif
@endsection
