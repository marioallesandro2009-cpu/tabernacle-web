<section class="sec on-paper ceo" id="ceo">
  <div class="wrap grid">
    @if (! empty($s['photo']))
    <div class="ceo-photo pic" data-reveal><img {!! media_attrs($s['photo'], '(max-width: 860px) 90vw, 40vw', tr($s['photo_alt'] ?? null) ?: null) !!} loading="lazy"></div>
    @endif
    <div class="ceo-text">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ $s['first'] ?? '' }} <span class="it">{{ $s['last'] ?? '' }}</span></h2>
      <blockquote data-fade>{{ tr($s['quote'] ?? null) }}</blockquote>
      <div class="letter" data-fade>{!! tr($s['letter'] ?? null) !!}</div>
      <p class="ceo-sign mono">{{ tr($s['sign'] ?? null) }}</p>
    </div>
  </div>
</section>
