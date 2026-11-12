@php
    $cards = \App\Support\SiteData::tourCards();
    $spans = \App\Support\SiteData::SPANS;
    $kinds = collect($cards)->pluck('kind')->unique()->values();
    $regionKeys = \App\Support\SiteData::regions();
    $dests = \App\Support\SiteData::destinations()->filter(fn ($d) => $d->tours_count > 0)->sortBy(fn ($d) => tr($d->name))->values();
@endphp
<section class="sec pk-list" id="list">
  <div class="wrap">
    <div class="pk-find">
      <label class="pk-q"><span class="mono">{{ t('pk.search') }}</span><input type="search" placeholder="{{ t('pk.search_ph') }}" aria-label="{{ t('pk.search_aria') }}" autocomplete="off"></label>
      <label class="pk-country"><span class="mono">{{ t('pk.country') }}</span><select aria-label="{{ t('pk.country') }}">
        <option value="all">{{ t('pk.all_countries') }}</option>
        @foreach ($dests as $d)<option value="{{ $d->slug }}">{{ tr($d->name) }}</option>@endforeach
      </select></label>
    </div>
    <div class="pk-filters">
      <div class="pk-filter" role="group" aria-label="{{ t('pk.type_aria') }}" data-key="type">
        <button type="button" data-v="all" aria-pressed="true">{{ t('pk.all') }}</button>
        @foreach ($kinds as $k)<button type="button" data-v="{{ $k }}" aria-pressed="false">{{ t('tour.type.'.$k) }}</button>@endforeach
      </div>
      <div class="pk-filter" role="group" aria-label="{{ t('pk.region_aria') }}" data-key="region">
        <button type="button" data-v="all" aria-pressed="true">{{ t('pk.all_regions') }}</button>
        @foreach ($regionKeys as $r)<button type="button" data-v="{{ $r }}" aria-pressed="false">{{ t('region.'.$r) }}</button>@endforeach
      </div>
      <p class="pk-count mono" aria-live="polite"><span>{{ count($cards) }}</span> <span>{{ nl(trim(str_replace(':n', '', t('pk.count'))), []) }}</span></p>
    </div>
    <div class="pkgs pk-grid">
      @foreach ($cards as $i => $c)
        @include('site.partials.tour-tile', ['c' => $c, 'span' => $spans[$i % count($spans)]])
      @endforeach
    </div>
    <p class="pk-empty" hidden>{{ t('pk.empty') }}</p>
  </div>
</section>
