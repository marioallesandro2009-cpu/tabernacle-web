<section class="sec visa" id="visa">
  <div class="wrap grid">
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    <p class="visa-note" data-fade>{{ tr($s['note'] ?? null) }}<span class="mono">{{ tr($s['note_small'] ?? null) }}</span></p>
    <div class="board">
      <div class="board-head mono"><span>{{ tr($s['head_a'] ?? null) }}</span><span>{{ tr($s['head_b'] ?? null) }}</span></div>
      @foreach ($s['rows'] ?? [] as $row)
        <div class="board-row"><img src="{{ media_url($row['flag'] ?? null, 480) }}" alt="" loading="lazy"><div class="flaps" data-flaps="{{ tr($row['country'] ?? null) }}"></div><span class="board-svc mono">{{ tr($s['doc_label'] ?? null) }}</span></div>
      @endforeach
    </div>
  </div>
</section>
