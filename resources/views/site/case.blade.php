@extends('site.layout', ['solidNav' => true])

@php($en = app()->getLocale() === 'en')
@if ($case->cover_id)
  @push('head'){!! media_preload($case->cover_id, '(max-width: 1400px) 100vw, 1400px') !!}@endpush
@endif

@section('content')
<article>
  <section class="pk-hero">
    <div class="wrap grid">
      <p class="kicker mono">@if ($case->pillar && $case->pillar->is_active)<a href="{{ lurl('/layanan/'.$case->pillar->slug) }}">{{ tr($case->pillar->title) }}</a>@else{{ $listTitle }}@endif</p>
      <h1 class="display h-xl"><span class="ln"><span>{{ tr($case->title) }}</span></span></h1>
      <dl class="case-facts">
        @if ($case->client)<div><dt class="mono">{{ $en ? 'Client' : 'Klien' }}</dt><dd>@if ($case->client_logo_id)<img {!! media_attrs($case->client_logo_id, '120px', $case->client) !!} class="case-logo">@endif{{ $case->client }}</dd></div>@endif
        @if ($case->destinations->isNotEmpty())<div><dt class="mono">{{ t('dest.title') }}</dt><dd>@foreach ($case->destinations as $d)<a href="{{ lurl('/destinations/'.$d->slug) }}">{{ tr($d->name) }}</a>@if (! $loop->last), @endif @endforeach</dd></div>@endif
        @if ($case->participants)<div><dt class="mono">{{ $en ? 'Participants' : 'Peserta' }}</dt><dd>{{ $case->participants }}</dd></div>@endif
        @if (tr($case->period))<div><dt class="mono">{{ $en ? 'When' : 'Waktu' }}</dt><dd>{{ tr($case->period) }}</dd></div>@endif
      </dl>
    </div>
  </section>
  @if ($case->cover_id)
    <div class="wrap"><div class="post-cover pic"><img {!! media_attrs($case->cover_id, '(max-width: 1400px) 100vw, 1400px', tr($case->title)) !!} fetchpriority="high"></div></div>
  @endif
  <div class="wrap post-body">
    @if (tr($case->objective))<h2 class="h-m">{{ $en ? 'Program goal' : 'Tujuan program' }}</h2><div class="prose">{!! tr($case->objective) !!}</div>@endif
    @if (tr($case->results))<h2 class="h-m">{{ $en ? 'Results' : 'Hasil' }}</h2><div class="prose">{!! tr($case->results) !!}</div>@endif
  </div>
  @if (! empty($case->gallery))
    <section class="sec">
      <div class="wrap">
        <div class="chapter"><b>{{ t('common.photo') }}</b></div>
        <div class="case-gallery">
          @foreach ($case->gallery as $mid)
            <button class="tile" type="button" data-full="{{ media_url($mid, 1600) }}" data-cap="{{ tr($case->title) }}"><img {!! media_attrs($mid, '(max-width: 860px) 50vw, 25vw') !!} loading="lazy"></button>
          @endforeach
        </div>
      </div>
    </section>
  @endif
</article>
@if (count($related))
<section class="sec on-paper">
  <div class="wrap">
    <div class="chapter"><b>{{ $en ? 'Packages to these destinations' : 'Paket ke destinasi ini' }}</b><a class="mono" href="{{ lurl('/packages') }}">{{ t('tour.all_packages') }}</a></div>
    <div class="pkgs">@foreach ($related as $c)@include('site.partials.tour-tile', ['c' => $c, 'span' => 4])@endforeach</div>
  </div>
</section>
@endif
@endsection
