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
    @if ($s['show_finder'] ?? true)
      @php $finderDests = \App\Support\SiteData::destinations()->filter(fn ($d) => $d->tours_count > 0)->sortBy(fn ($d) => tr($d->name))->values(); @endphp
      {{-- quick finder: hands off to the packages page filters (?country= / ?q=) --}}
      <form class="hero-find" action="{{ lurl('/packages') }}#list" method="get" role="search" aria-label="{{ t('hero.find_aria') }}">
        <label class="hf-field">
          <span class="mono">{{ t('hero.find_label') }}</span>
          <select name="country">
            <option value="">{{ t('hero.find_any') }}</option>
            @foreach ($finderDests as $d)<option value="{{ $d->slug }}">{{ tr($d->name) }} ({{ $d->tours_count }})</option>@endforeach
          </select>
        </label>
        <label class="hf-field">
          <span class="mono">{{ t('hero.find_q') }}</span>
          <input type="search" name="q" placeholder="{{ t('hero.find_q_ph') }}" autocomplete="off">
        </label>
        <button class="btn btn-amber" type="submit">{{ t('hero.find_btn') }} <svg aria-hidden="true"><use href="#arrow"/></svg></button>
      </form>
    @endif
  </div>
</section>
