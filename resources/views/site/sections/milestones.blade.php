<section class="sec">
  <div class="wrap">
    <div class="grid miles-head">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    </div>
    <ol class="miles" data-stagger>
      @foreach ($s['items'] ?? [] as $it)
        <li class="mile"><div class="mile-year">{{ $it['year'] ?? '' }}</div><b>{{ tr($it['title'] ?? null) }}</b><p>{{ tr($it['text'] ?? null) }}</p></li>
      @endforeach
    </ol>
  </div>
</section>
