@php $team = \App\Support\SiteData::team(); @endphp
@if ($team->isNotEmpty())
<section class="sec team-sec">
  <div class="wrap">
    <div class="grid">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    </div>
    <div class="team-grid" data-stagger>
      @foreach ($team as $m)
        <article class="member">
          @if ($m->photo_id)<div class="pic"><img {!! media_attrs($m->photo_id, '(max-width: 860px) 50vw, 25vw', $m->name) !!} loading="lazy"></div>@endif
          <h3>{{ $m->name }}</h3><p class="mono">{{ tr($m->role) }}</p><div class="prose">{!! tr($m->bio) !!}</div>
        </article>
      @endforeach
    </div>
  </div>
</section>
@endif
