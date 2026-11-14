{{-- $cases: case studies as cards --}}
<div class="post-grid case-grid">
  @foreach ($cases as $c)
    <a class="post-card case-card" href="{{ lurl('/studi-kasus/'.$c->slug) }}">
      <div class="pic">@if ($c->cover_id)<img {!! media_attrs($c->cover_id, '(max-width: 860px) 100vw, 33vw', tr($c->title)) !!} loading="lazy">@endif</div>
      <p class="mono">{{ collect([$c->client, tr($c->period), $c->participants ? $c->participants.' '.(app()->getLocale() === 'en' ? 'participants' : 'peserta') : null])->filter()->implode(' · ') }}</p>
      <h3>{{ tr($c->title) }}</h3>
    </a>
  @endforeach
</div>
