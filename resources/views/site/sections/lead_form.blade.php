<section class="sec on-paper lead-sec" id="inquiry">
  <div class="wrap grid">
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    <p class="lead-intro" data-fade>{{ tr($s['intro'] ?? null) }}</p>
    <div class="lead-box">@include('site.partials.lead-form', ['tourId' => null])</div>
  </div>
</section>
