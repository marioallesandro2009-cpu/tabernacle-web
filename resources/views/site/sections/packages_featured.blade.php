@php
    $featured = \App\Support\SiteData::featuredCards();
    $total = count(\App\Support\SiteData::tourCards());
    $spans = \App\Support\SiteData::SPANS;
@endphp
<section class="sec packages" id="packages">
  <div class="wrap">
    <div class="grid jr-head">
      <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
      <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
      <p data-fade>{{ nl(tr($s['intro'] ?? null), ['n' => $total]) }}</p>
    </div>
    <div class="pkgs" style="margin-top:clamp(30px,4vw,56px)">
      @foreach ($featured as $i => $c)
        @include('site.partials.tour-tile', ['c' => $c, 'span' => $spans[$i % count($spans)]])
      @endforeach
    </div>
    <p class="pk-all"><a class="btn btn-navy" href="{{ url('/packages') }}">{{ nl(tr($s['all_label'] ?? null), ['n' => $total]) }} <svg aria-hidden="true"><use href="#arrow"/></svg></a></p>
  </div>
</section>
