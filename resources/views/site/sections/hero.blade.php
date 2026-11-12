<section class="hero">
  <div class="hero-bg"><img {!! media_attrs($s['image'] ?? null, '100vw', tr($s['image_alt'] ?? null) ?: null) !!} fetchpriority="high"></div>
  <div class="wrap hero-in">
    <h1 class="display">
      <span class="ln"><span>{{ tr($s['line1'] ?? null) }}</span></span>
      @if (tr($s['line2'] ?? null))<span class="ln"><span class="l2 it">{{ tr($s['line2'] ?? null) }}</span></span>@endif
    </h1>
    <div class="hero-foot">
      <p>{{ tr($s['text'] ?? null) }}</p>
      <div class="hero-actions">
        @if (tr($s['btn1_label'] ?? null))<a class="btn btn-amber" {!! link_attrs($s['btn1_url'] ?? '') !!}>{{ tr($s['btn1_label']) }} <svg aria-hidden="true"><use href="#arrow"/></svg></a>@endif
        @if (tr($s['btn2_label'] ?? null))<a class="btn" {!! link_attrs($s['btn2_url'] ?? '') !!}>{{ tr($s['btn2_label']) }}</a>@endif
      </div>
    </div>
  </div>
</section>
