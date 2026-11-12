@extends('site.layout', ['solidNav' => true])

@section('content')
<section class="pk-hero">
  <div class="wrap grid">
    <p class="kicker mono"><a href="{{ url('/destinations') }}">{{ t('dest.title') }}</a> / {{ t('region.'.$dest->region) }}</p>
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
          @include('site.partials.tour-tile', ['c' => $c, 'span' => \App\Support\SiteData::SPANS[$i % 10]])
        @endforeach
      </div>
    @else
      <p class="pk-empty">{{ t('dest.empty') }}</p>
    @endif
  </div>
</section>
@endsection
