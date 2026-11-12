<a class="pkg s{{ $span }}" href="{{ url('/packages/'.$c['slug']) }}" data-type="{{ $c['kind'] }}" data-region="{{ $c['region'] }}" data-countries="{{ implode('|', $c['countries']) }}" data-search="{{ $c['search'] }}">
  <img {!! media_attrs($c['cover'], '(max-width: 860px) 100vw, 55vw', tr($c['name'])) !!} loading="lazy">
  <span class="pkg-badge mono">{{ t('tour.type.'.$c['kind']) }}</span>
  <div class="pkg-in"><div class="pkg-top"><h3>{{ tr($c['name']) }}</h3><p class="mono"><span>{{ $c['duration'] }}</span> · <span>{{ tr($c['area']) }}</span></p></div>
  <div class="pkg-foot"><p class="mono">@if ($c['min_pax'])<span>{{ t('tour.min_pax', ['n' => $c['min_pax']]) }}</span>@endif<span>{{ $c['flights'] ? t('tour.flights_included') : t('tour.flights_excluded') }}</span></p><div class="pkg-price"><span class="mono">{{ t('tour.from') }}</span><b>{{ $c['price'] ? rupiah($c['price']) : t('tour.on_request') }}</b></div></div></div>
</a>
