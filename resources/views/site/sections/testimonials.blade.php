@php $items = \App\Support\SiteData::testimonials(); @endphp
@if ($items->isNotEmpty())
<section class="sec on-sand testi-sec">
  <div class="wrap">
    <div class="grid">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    </div>
    <div class="testi-grid" data-stagger>
      @foreach ($items as $t)
        <figure class="testi">
          <blockquote>{{ tr($t->content) }}</blockquote>
          <figcaption>
            @if ($t->photo_id)<img {!! media_attrs($t->photo_id, '64px', $t->name) !!} loading="lazy">@endif
            <span><b>{{ $t->name }}</b>@if ($t->trip)<small class="mono">{{ $t->trip }}</small>@endif</span>
            <span class="stars" aria-label="{{ $t->rating }}/5">{{ str_repeat('★', (int) $t->rating) }}{{ str_repeat('☆', max(0, 5 - (int) $t->rating)) }}</span>
          </figcaption>
        </figure>
      @endforeach
    </div>
  </div>
</section>
@endif
