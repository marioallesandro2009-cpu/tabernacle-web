<section class="sec on-paper" id="services">
  <div class="wrap">
    <div class="grid svc-head">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
      <p data-fade>{{ tr($s['intro'] ?? null) }}</p>
    </div>
    @if (! empty($s['image']))
    <div class="svc-pic pic" data-reveal><img {!! media_attrs($s['image'], '(max-width: 1400px) 100vw, 1400px', tr($s['image_alt'] ?? null) ?: null) !!} loading="lazy"></div>
    @endif
    <ol class="svc-list" data-stagger>
      @foreach ($s['items'] ?? [] as $i => $it)
        <li><span class="mono">{{ sprintf('%02d', $i + 1) }}</span><h3>{{ tr($it['title'] ?? null) }}</h3><p>{{ tr($it['text'] ?? null) }}</p></li>
      @endforeach
    </ol>
    @if (tr($s['note'] ?? null))<p class="svc-note">{!! tr($s['note']) !!}</p>@endif
  </div>
</section>
