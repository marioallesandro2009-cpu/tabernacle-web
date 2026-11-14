@extends('site.layout', ['solidNav' => true])

@section('content')
<article class="post">
  <header class="post-head">
    <div class="wrap">
      <nav class="tr-crumb mono" aria-label="Breadcrumb"><a href="{{ lurl('/') }}">{{ t('common.home') }}</a> / <a href="{{ lurl('/blog') }}">{{ t('blog.title') }}</a>@if ($post->category) / <a href="{{ lurl('/blog') }}?category={{ $post->category->slug }}">{{ $post->category->name }}</a>@endif</nav>
      <h1 class="display h-l">{{ $post->title }}</h1>
      <p class="post-meta mono">{{ ($post->published_at ?? now())->locale(app()->getLocale())->translatedFormat('d F Y') }} · {{ t('blog.min_read', ['n' => $post->reading_time]) }}@if ($post->author) · {{ t('blog.by', ['name' => $post->author->name]) }}@endif</p>
      @if ($post->excerpt)<p class="lede post-lede">{{ $post->excerpt }}</p>@endif
    </div>
  </header>
  @if ($post->cover_id)
    <div class="wrap"><div class="post-cover pic"><img {!! media_attrs($post->cover_id, '(max-width: 1400px) 100vw, 1400px', $post->title) !!} fetchpriority="high"></div></div>
  @endif
  <div class="wrap post-body">
    @if (count($toc) > 2)
      <aside class="toc"><p class="mono">{{ t('blog.toc') }}</p><ol>@foreach ($toc as $h)<li class="lv{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol></aside>
    @endif
    <div class="prose post-content">{!! $html !!}</div>
  </div>

  @if (! empty($post->faq))
    <div class="wrap post-faq">
      <h2 class="display h-m">{{ t('blog.faq') }}</h2>
      <div class="faq-list">@foreach ($post->faq as $f)@if (! empty($f['q']))<details><summary>{{ $f['q'] }}</summary><div class="prose"><p>{{ $f['a'] ?? '' }}</p></div></details>@endif @endforeach</div>
    </div>
  @endif
  @if ($post->tags->isNotEmpty())
    <div class="wrap post-tags"><span class="mono">{{ t('blog.tags') }}</span> @foreach ($post->tags as $tag)<a class="chip-link" href="{{ lurl('/blog') }}?tag={{ $tag->slug }}">#{{ $tag->name }}</a>@endforeach</div>
  @endif
</article>

@if (count($tours))
<section class="sec on-sand">
  <div class="wrap">
    <div class="chapter"><b>{{ t('blog.related_packages') }}</b><a class="mono" href="{{ lurl('/packages') }}">{{ t('tour.all_packages') }}</a></div>
    <div class="pkgs">@foreach ($tours as $c)@include('site.partials.tour-tile', ['c' => $c, 'span' => 4])@endforeach</div>
  </div>
</section>
@endif
@if ($related->isNotEmpty())
<section class="sec on-paper">
  <div class="wrap">
    <div class="chapter"><b>{{ t('blog.related') }}</b><a class="mono" href="{{ lurl('/blog') }}">{{ t('blog.all') }}</a></div>
    <div class="post-grid">
      @foreach ($related as $p)
        <a class="post-card" href="{{ lurl('/blog/'.$p->slug) }}"><div class="pic">@if ($p->cover_id)<img {!! media_attrs($p->cover_id, '(max-width: 860px) 100vw, 33vw', $p->title) !!} loading="lazy">@endif</div><p class="mono">{{ $p->published_at->locale(app()->getLocale())->translatedFormat('d M Y') }}</p><h3>{{ $p->title }}</h3></a>
      @endforeach
    </div>
  </div>
</section>
@endif
@endsection
