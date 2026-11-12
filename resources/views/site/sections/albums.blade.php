@php $albums = \App\Support\SiteData::albums(); @endphp
@if ($albums->isNotEmpty())
<section class="sec albums-sec">
  <div class="wrap">
    <div class="grid jr-head">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    </div>
    @foreach ($albums as $album)
      <div class="chapter"><b>{{ tr($album->title) }}</b><span class="mono">{{ $album->trip }}</span></div>
      <div class="gallery gallery-auto">
        @foreach ($album->items ?? [] as $i => $item)
          <button class="tile {{ ['g-a', 'g-b', 'g-c', 'g-b', 'g-b', 'g-b'][$i % 6] }}" type="button" data-full="{{ media_url($item['media_id'] ?? null, 1600) }}" data-title="{{ tr($item['caption'] ?? null) ?: tr($album->title) }}" data-meta="{{ $album->trip }}"><img {!! media_attrs($item['media_id'] ?? null, '(max-width: 860px) 100vw, 50vw') !!} loading="lazy"><span class="cap">{{ tr($item['caption'] ?? null) }}</span></button>
        @endforeach
      </div>
    @endforeach
  </div>
</section>
@endif
