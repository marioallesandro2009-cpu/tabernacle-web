{{-- $posts: articles as cards --}}
<div class="post-grid">
  @foreach ($posts as $p)
    <a class="post-card" href="{{ lurl('/blog/'.$p->slug) }}"><div class="pic">@if ($p->cover_id)<img {!! media_attrs($p->cover_id, '(max-width: 860px) 100vw, 33vw', $p->title) !!} loading="lazy">@endif</div><p class="mono">{{ $p->published_at?->locale(app()->getLocale())->translatedFormat('d M Y') }}</p><h3>{{ $p->title }}</h3></a>
  @endforeach
</div>
