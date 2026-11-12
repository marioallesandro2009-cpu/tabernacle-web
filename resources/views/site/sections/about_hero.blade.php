<section class="ab-hero">
  <div class="wrap grid">
    <h1 class="display h-xl">
      <span class="ln"><span>{{ tr($s['line1'] ?? null) }}</span></span>
      <span class="ln"><span class="l2 it">{{ tr($s['line2'] ?? null) }}</span></span>
    </h1>
    <p class="ab-intro" data-fade>{{ tr($s['intro'] ?? null) }}</p>
    @if (! empty($s['pics']))
    <div class="ab-pics">
      @foreach ($s['pics'] as $pic)
        <figure class="pic" data-reveal><img {!! media_attrs($pic['image'] ?? null, '(max-width: 860px) 100vw, 60vw', tr($pic['alt'] ?? null) ?: null) !!} loading="lazy"><figcaption>{{ tr($pic['caption'] ?? null) }}</figcaption></figure>
      @endforeach
    </div>
    @endif
  </div>
</section>
