<section class="sec text-sec">
  <div class="wrap grid">
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    <div class="prose" data-fade>{!! tr($s['body'] ?? null) !!}</div>
  </div>
</section>
