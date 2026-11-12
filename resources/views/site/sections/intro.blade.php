<section class="sec intro" id="welcome">
  <div class="wrap grid">
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h2 data-fade>{!! tr($s['heading'] ?? null) !!}</h2>
    <div class="intro-copy" data-fade>
      {!! tr($s['body'] ?? null) !!}
      @if (tr($s['link_label'] ?? null))<p><a class="link" {!! link_attrs($s['link_url'] ?? '') !!}>{{ tr($s['link_label']) }} <svg aria-hidden="true"><use href="#arrow"/></svg></a></p>@endif
    </div>
    @if (! empty($s['image']))
    <div class="intro-pic pic" data-reveal><img {!! media_attrs($s['image'], '(max-width: 860px) 100vw, 40vw', tr($s['image_alt'] ?? null) ?: null) !!} loading="lazy"></div>
    @endif
    @if (! empty($s['facts']))
    <dl class="facts">
      @foreach ($s['facts'] as $f)
        @php
            $v = (string) ($f['value'] ?? '');
            $out = (! empty($f['count']) && preg_match('/^(\d+)(.*)$/u', $v, $m))
                ? '<span data-count="'.e($m[1]).'">'.e($m[1]).'</span>'.e($m[2])
                : str_replace('/', '<span>/</span>', e($v));
        @endphp
        <div><dt class="mono">{{ tr($f['label'] ?? null) }}</dt><dd>{!! $out !!}</dd></div>
      @endforeach
    </dl>
    @endif
  </div>
</section>
