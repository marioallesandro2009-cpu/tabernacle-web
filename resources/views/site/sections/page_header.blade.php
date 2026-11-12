@php
    $count = count(\App\Support\SiteData::tourCards());
    $regions = count(\App\Support\SiteData::regions());
    $intro = (string) tr($s['intro'] ?? null);
    $intro = str_replace('<b>:n</b>', '<b data-count="'.$count.'">'.$count.'</b>', $intro);
    $intro = nl($intro, ['n' => $count, 'r' => $regions]);
@endphp
<section class="pk-hero">
  <div class="wrap grid">
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h1 class="display h-xl"><span class="ln"><span>{{ tr($s['title_a'] ?? null) }}</span></span><span class="ln"><span class="l2 it">{{ tr($s['title_b'] ?? null) }}</span></span></h1>
    <p class="pk-intro" data-fade>{!! $intro !!}</p>
  </div>
</section>
