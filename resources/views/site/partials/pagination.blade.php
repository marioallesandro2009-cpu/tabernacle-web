@if ($paginator->hasPages())
<nav class="pagination" aria-label="Pagination">
  @if ($paginator->onFirstPage())<span class="mono" aria-disabled="true">‹</span>@else<a class="mono" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>@endif
  @foreach ($elements as $element)
    @if (is_string($element))<span class="mono">{{ $element }}</span>@endif
    @if (is_array($element))
      @foreach ($element as $page => $url)
        @if ($page == $paginator->currentPage())<span class="mono current" aria-current="page">{{ $page }}</span>@else<a class="mono" href="{{ $url }}">{{ $page }}</a>@endif
      @endforeach
    @endif
  @endforeach
  @if ($paginator->hasMorePages())<a class="mono" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>@else<span class="mono" aria-disabled="true">›</span>@endif
</nav>
@endif
