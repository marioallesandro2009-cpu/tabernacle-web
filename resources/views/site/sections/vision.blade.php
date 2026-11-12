<section class="sec on-navy vision" id="vision">
  <div class="wrap grid">
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    <p class="vision-copy" data-fade>{{ tr($s['text'] ?? null) }}</p>
    <ol class="mission" data-stagger>
      @foreach ($s['mission'] ?? [] as $i => $m)
        <li class="m{{ $i + 1 }}"><span class="n mono">{{ sprintf('%02d', $i + 1) }}</span><span class="t">{{ tr($m['title'] ?? null) }}</span><span class="d">{{ tr($m['text'] ?? null) }}</span></li>
        @if ($i === 1 && ! empty($s['image']))
          <li class="m-pic pic"><img {!! media_attrs($s['image'], '(max-width: 860px) 100vw, 25vw', tr($s['image_alt'] ?? null) ?: null) !!} loading="lazy"></li>
        @endif
      @endforeach
    </ol>
  </div>
</section>
