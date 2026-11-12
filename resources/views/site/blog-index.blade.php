@extends('site.layout', ['solidNav' => true])

@section('content')
@foreach ($page->sections as $section)
    @includeIf('site.sections.'.$section->type, ['s' => $section->data ?? [], 'section' => $section, 'page' => $page])
@endforeach

<section class="sec pk-list blog-sec">
  <div class="wrap">
    @if ($categories->where('posts_count', '>', 0)->isNotEmpty())
      <div class="pk-filter blog-cats" role="group">
        <a class="chip-link" href="{{ url('/blog') }}" @if (! $category) aria-current="true" @endif>{{ t('blog.all') }}</a>
        @foreach ($categories->where('posts_count', '>', 0) as $c)
          <a class="chip-link" href="{{ url('/blog') }}?category={{ $c->slug }}" @if ($category?->id === $c->id) aria-current="true" @endif>{{ $c->name }}</a>
        @endforeach
      </div>
    @endif
    @if ($posts->isEmpty())
      <p class="pk-empty">{{ t('blog.empty') }}</p>
    @else
      <div class="post-grid">
        @foreach ($posts as $post)
          <a class="post-card" href="{{ url('/blog/'.$post->slug) }}">
            <div class="pic">@if ($post->cover_id)<img {!! media_attrs($post->cover_id, '(max-width: 860px) 100vw, 33vw', $post->title) !!} loading="lazy">@endif</div>
            <p class="mono">@if ($post->category){{ $post->category->name }} · @endif{{ $post->published_at->locale(app()->getLocale())->translatedFormat('d M Y') }} · {{ t('blog.min_read', ['n' => $post->reading_time]) }}</p>
            <h3>{{ $post->title }}</h3>
            @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
          </a>
        @endforeach
      </div>
      {{ $posts->links('site.partials.pagination') }}
    @endif
  </div>
</section>
@endsection
