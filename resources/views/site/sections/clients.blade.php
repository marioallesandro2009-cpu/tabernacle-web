<section class="clients" id="clients">
  <div class="wrap">
    <div class="grid">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-m" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    </div>
    <ul class="logos" data-stagger>
      @foreach ($s['logos'] ?? [] as $l)
        <li><img {!! media_attrs($l['image'] ?? null, '220px', ($l['name'] ?? '') ?: null) !!} loading="lazy"></li>
      @endforeach
    </ul>
  </div>
</section>
