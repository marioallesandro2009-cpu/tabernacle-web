<section class="sec on-paper" id="portfolio">
  <div class="wrap">
    <div class="grid pf-head">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
      @if (! empty($s['filters']))
      <div class="pf-filter" role="group" aria-label="{{ tr($s['filter_aria'] ?? null) }}">
        @foreach ($s['filters'] as $i => $f)
          <button type="button" data-f="{{ $f['key'] ?? '' }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">{{ tr($f['label'] ?? null) }}</button>
        @endforeach
      </div>
      @endif
    </div>
    <ul class="pf-grid">
      @foreach ($s['items'] ?? [] as $it)
        <li data-cat="{{ $it['cats'] ?? '' }}"><button class="trip" type="button" data-full="{{ media_url($it['image'] ?? null, 1600) }}" data-title="{{ tr($it['modal_title'] ?? null) ?: tr($it['place'] ?? null) }}" data-meta="{{ tr($it['modal_meta'] ?? null) }}"><div class="pic"><img {!! media_attrs($it['image'] ?? null, '(max-width: 860px) 100vw, 33vw', tr($it['alt'] ?? null) ?: null) !!} loading="lazy"></div><div class="trip-cap"><b>{{ tr($it['place'] ?? null) }}</b><span class="mono">{{ tr($it['meta'] ?? null) }}</span></div></button></li>
      @endforeach
    </ul>
  </div>
</section>
