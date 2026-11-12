<section class="sec" id="journeys">
  <div class="wrap">
    <div class="grid jr-head">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
      <p data-fade>{{ tr($s['intro'] ?? null) }}</p>
    </div>
    @foreach ($s['chapters'] ?? [] as $ch)
      <div class="chapter"><b>{{ tr($ch['title'] ?? null) }}</b><span class="mono">{{ tr($ch['meta'] ?? null) }}</span></div>
      <div class="gallery">
        @foreach ($ch['tiles'] ?? [] as $tile)
          <button class="tile {{ $tile['size'] ?? 'g-b' }}" type="button" data-full="{{ media_url($tile['image'] ?? null, 1600) }}" data-title="{{ tr($tile['modal_title'] ?? null) ?: tr($tile['caption'] ?? null) }}" data-meta="{{ tr($tile['modal_meta'] ?? null) }}"><img {!! media_attrs($tile['image'] ?? null, '(max-width: 860px) 100vw, 50vw', tr($tile['alt'] ?? null) ?: null) !!} loading="lazy" @if (! empty($tile['focus'])) style="object-position:{{ preg_replace('/[^0-9%\s.]/', '', $tile['focus']) }}" @endif><span class="cap">{{ tr($tile['caption'] ?? null) }}</span></button>
        @endforeach
      </div>
    @endforeach
  </div>
</section>
