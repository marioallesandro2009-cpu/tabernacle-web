@php($list = $pillarList ?? \App\Models\Pillar::where('is_active', true)->orderBy('sort')->get())
@if ($list->isNotEmpty())
  @if (! isset($pillarList))<section class="sec"><div class="wrap">@endif
  @if (tr($s['title_a'] ?? null))
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h2 class="display h-l">{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
  @endif
  <div class="pillar-grid">
    @foreach ($list as $p)
      <a class="pillar-card" href="{{ lurl('/layanan/'.$p->slug) }}">
        @if ($p->cover_id)<div class="pic"><img {!! media_attrs($p->cover_id, '(max-width: 860px) 100vw, 25vw', tr($p->title)) !!} loading="lazy"></div>@endif
        <h3>{{ tr($p->title) }}</h3>
        @if (tr($p->intro))<p>{{ \Illuminate\Support\Str::limit(trim(strip_tags(tr($p->intro))), 140) }}</p>@endif
      </a>
    @endforeach
  </div>
  @if (! isset($pillarList))</div></section>@endif
@endif
