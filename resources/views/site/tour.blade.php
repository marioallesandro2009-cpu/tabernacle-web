@extends('site.layout', ['solidNav' => true])

@push('head'){!! media_preload($tour->cover_id, '100vw') !!}@endpush

@php
    $kind = $tour->category?->kind ?? ($tour->flights_included ? 'group' : 'fit');
    $price = $tour->fromPrice();
    $fullName = trim(($tour->duration ? $tour->duration.' ' : '').$tour->displayName());
    $departures = collect($tour->upcomingDepartures());
    $spans = \App\Support\SiteData::SPANS;
    $gallery = collect($tour->gallery ?: [])->filter()->values();
    $trust = collect(setting('trust_points', []) ?: [])->map(fn ($r) => tr($r['text'] ?? null))->filter()->take(5)->values();
    $itinerary = $tour->localItinerary();
    $includes = $tour->localIncludes();
    $excludes = $tour->localExcludes();
    $terms = $tour->localTerms();
    $hasOverview = tr($tour->description) || ! empty($tour->highlights);
    $nav = array_filter([
        'overview' => $hasOverview ? t('tour.nav_overview') : null,
        'photos' => $gallery->isNotEmpty() ? t('tour.gallery') : null,
        'schedule' => $departures->isNotEmpty() ? t('tour.schedule') : null,
        'itinerary' => ! empty($itinerary) ? t('tour.itinerary') : null,
        'included' => (! empty($includes) || ! empty($excludes)) ? t('tour.included') : null,
        'terms' => $terms ? t('tour.terms') : null,
        'inquiry' => t('tour.nav_ask'),
    ]);
@endphp

@section('content')
<section class="tr-hero">
  <div class="tr-bg"><img {!! media_attrs($tour->cover_id, '100vw', $tour->displayName()) !!} fetchpriority="high"></div>
  <div class="wrap tr-hero-in">
    <p class="tr-crumb mono"><a href="{{ lurl('/packages') }}">{{ t('tour.crumb') }}</a> / <span>{{ t('region.'.$tour->region) }}</span></p>
    <p class="tr-tags mono"><span class="tag">{{ t('tour.type.'.$kind) }}</span>@if ($tour->duration)<span class="tag">{{ $tour->duration }}</span>@endif @foreach ($tour->destinations as $d)<a class="tag" href="{{ lurl('/packages') }}?country={{ $d->slug }}">{{ tr($d->name) }}</a>@endforeach
      @if (tr($tour->promo_label))<span class="tag tag-promo">{{ tr($tour->promo_label) }}</span>@endif</p>
    <h1 class="display h-l">{{ $tour->displayName() }}</h1>
    @if ($tour->route)<p class="tr-route">{{ $tour->route }}</p>@endif
  </div>
</section>

@if (count($nav) > 2)
<nav class="tr-nav" aria-label="{{ t('tour.nav_label') }}">
  <div class="wrap"><ul>
    @foreach ($nav as $id => $label)<li><a href="#{{ $id }}" class="mono">{{ $label }}</a></li>@endforeach
  </ul></div>
</nav>
@endif

<section class="sec tr-main">
  <div class="wrap grid">
    <div class="tr-content">
      @if ($hasOverview)<span id="overview" class="tr-anchor"></span>@endif
      @if (tr($tour->description))
        <div class="tr-block prose">{!! tr($tour->description) !!}</div>
      @endif
      @if (! empty($tour->highlights))
        <div class="tr-block"><p class="kicker mono">{{ t('tour.highlights') }}</p><ul class="chips">@foreach ($tour->highlights as $h)<li>{{ $h }}</li>@endforeach</ul></div>
      @endif

      @if ($gallery->isNotEmpty())
        <div class="tr-block" id="photos">
          <p class="kicker mono">{{ t('tour.gallery') }}</p>
          <ul class="tr-gallery">
            @foreach ($gallery as $mid)
              <li @class(['wide' => $loop->first])><button type="button" data-full="{{ media_url($mid, 1600) }}" data-title="{{ media_alt($mid) ?: $tour->displayName() }}" data-meta="{{ $loop->iteration }} / {{ $gallery->count() }}" aria-label="{{ t('tour.gallery') }} {{ $loop->iteration }}"><img {!! media_attrs($mid, $loop->first ? '(max-width: 860px) 100vw, 60vw' : '(max-width: 860px) 50vw, 25vw', $tour->displayName()) !!} loading="lazy"></button></li>
            @endforeach
          </ul>
        </div>
      @endif

      @if ($departures->isNotEmpty())
        <div class="tr-block" id="schedule">
          <p class="kicker mono">{{ t('tour.schedule') }}</p>
          <div class="dep-table" role="table">
            <div class="dep-head mono" role="row"><span>{{ t('tour.departure') }}</span><span>{{ t('tour.adult') }}</span><span>{{ t('tour.child') }}</span><span>{{ t('tour.single_supp') }}</span><span></span></div>
            @foreach ($departures as $dep)
              @php
                  $status = $dep['status'] ?? 'available';
                  $left = isset($dep['remaining']) && $dep['remaining'] !== '' ? (int) $dep['remaining'] : null;
              @endphp
              <div class="dep-row" role="row">
                <span><b>{{ \Illuminate\Support\Carbon::parse($dep['date'])->locale(app()->getLocale())->translatedFormat('d M Y') }}</b>@if (! empty($dep['note']))<small>{{ $dep['note'] }}</small>@endif</span>
                <span>{{ ! empty($dep['price_adult']) ? rupiah($dep['price_adult']) : '–' }}</span>
                <span>{{ ! empty($dep['price_child']) ? rupiah($dep['price_child']) : '–' }}</span>
                <span>{{ ! empty($dep['single_supp']) ? rupiah($dep['single_supp']) : '–' }}</span>
                <span class="seat seat-{{ $status }}">{{ t('seat.'.$status) }}@if ($left !== null && $status !== 'full')<small>{{ t('tour.seats_left', ['n' => $left]) }}</small>@endif</span>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @if (! empty($itinerary))
        <div class="tr-block" id="itinerary">
          <div class="tr-block-head"><p class="kicker mono">{{ t('tour.itinerary') }}</p>
            @if (count($itinerary) > 2)<button type="button" class="tr-toggle-days mono" data-open="{{ t('tour.expand_all') }}" data-close="{{ t('tour.collapse_all') }}" aria-expanded="false">{{ t('tour.expand_all') }}</button>@endif
          </div>
          @if (app()->getLocale() === 'en' && $tour->itineraryUntranslated() && t('tour.itinerary_note') !== '' && t('tour.itinerary_note') !== 'tour.itinerary_note')<p class="tr-lang mono">{{ t('tour.itinerary_note') }}</p>@endif
          <ol class="days">
            @foreach ($itinerary as $d)
              <li class="day">
                <details @if ($loop->index < 2) open @endif>
                <summary>
                  <span class="day-n"><span class="mono">{{ t('tour.day', ['n' => $d['n'] ?? $loop->iteration]) }}</span></span>
                  <h3>{{ $d['title'] ?? '' }}</h3>
                </summary>
                <div class="day-body">
                  @if (! empty($d['meals']))<p class="day-meals mono">@foreach (array_filter(explode(',', $d['meals'])) as $meal)@if (\App\Support\Ui::all()['meal.'.trim($meal)] ?? null)<span>{{ t('meal.'.trim($meal)) }}</span>@endif @endforeach</p>@endif
                  <p>{{ $d['text'] ?? '' }}</p>
                  @if (! empty($d['hotel']))<p class="day-hotel"><span class="mono">{{ t('tour.hotel') }}</span> {{ $d['hotel'] }}</p>@endif
                </div>
                </details>
              </li>
            @endforeach
          </ol>
        </div>
      @endif

      @if (! empty($includes) || ! empty($excludes))
        <div class="tr-block tr-incl" id="included">
          <div><h2 class="display h-m">{{ t('tour.included') }}</h2><ul class="ticks">@foreach ($includes as $x)<li>{{ $x }}</li>@endforeach</ul></div>
          <div><h2 class="display h-m">{{ t('tour.excluded') }}</h2><ul class="crosses">@foreach ($excludes as $x)<li>{{ $x }}</li>@endforeach</ul></div>
        </div>
      @endif

      @if ($terms)
        <div class="tr-block" id="terms"><p class="kicker mono">{{ t('tour.terms') }}</p><div class="prose">{!! $terms !!}</div></div>
      @endif
      @if ($tour->has_shopping)<p class="tr-note">{{ t('tour.shopping_note') }}</p>@endif
    </div>

    <aside class="tr-side">
      <div class="tr-card" id="price-card">
        <p class="mono">{{ t('tour.from_pp') }}</p>
        <p class="tr-price">{{ $price ? rupiah($price) : t('tour.on_request') }}</p>
        <dl>
          @if ($tour->duration)<div><dt class="mono">{{ t('tour.duration') }}</dt><dd>{{ $tour->duration }}</dd></div>@endif
          <div><dt class="mono">{{ t('tour.departure') }}</dt><dd>{{ $tour->localDatesLabel() ?: t('tour.on_request') }}</dd></div>
          @if ($tour->min_pax)<div><dt class="mono">{{ t('tour.minimum') }}</dt><dd>{{ t('tour.people', ['n' => $tour->min_pax]) }}</dd></div>@endif
          @if ($tour->dp_min)<div><dt class="mono">{{ t('tour.dp') }}</dt><dd>{{ rupiah($tour->dp_min) }}</dd></div>@endif
          <div><dt class="mono">{{ t('tour.type') }}</dt><dd>{{ t('tour.type.'.$kind) }}, {{ mb_strtolower($tour->flights_included ? t('tour.flights_included') : t('tour.flights_excluded')) }}</dd></div>
        </dl>
        <a class="btn btn-amber" href="{{ wa_link(t('wa.pkg', ['name' => $fullName])) }}" data-pkg="{{ $fullName }}" data-tour-id="{{ $tour->id }}">{{ t('tour.book_wa') }} <svg aria-hidden="true"><use href="#arrow"/></svg></a>
        <a class="btn btn-ghost" href="#inquiry">{{ t('tour.ask_form') }}</a>
        @if ($tour->pdf_id)<a class="btn btn-ghost" href="{{ media_url($tour->pdf_id) }}" download>{{ t('tour.download_pdf') }}</a>@endif
        @if ($tour->dp_min || $tour->pdf_id || $trust->isNotEmpty())
          <ul class="tr-trust">
            @if ($tour->dp_min)<li>{{ t('tour.dp_note', ['amount' => rupiah($tour->dp_min)]) }}</li>@endif
            @if ($tour->pdf_id)<li>{{ t('tour.pdf_note') }}</li>@endif
            @foreach ($trust as $point)<li>{{ $point }}</li>@endforeach
          </ul>
        @endif
        <p class="tr-small">{{ t('tour.price_note') }}</p>
      </div>
    </aside>
  </div>
</section>

<section class="sec on-sand lead-sec" id="inquiry">
  <div class="wrap grid">
    <p class="kicker mono">{{ t('tour.inquiry') }}</p>
    <h2 class="display h-m lead-title">{{ $fullName }}</h2>
    <div class="lead-box">@include('site.partials.lead-form', ['tourId' => $tour->id])</div>
  </div>
</section>

<div class="tr-bar" aria-hidden="true" hidden>
  <div class="tr-bar-price"><span class="mono">{{ t('tour.from') }}</span><b>{{ $price ? rupiah($price) : t('tour.on_request') }}</b></div>
  <a class="btn btn-amber" href="{{ wa_link(t('wa.pkg', ['name' => $fullName])) }}" data-pkg="{{ $fullName }}" data-tour-id="{{ $tour->id }}" tabindex="-1">{{ t('tour.book_wa') }}</a>
</div>

<section class="sec tr-more on-paper">
  <div class="wrap">
    <div class="chapter"><b>{{ t('tour.related') }}</b><a class="mono" href="{{ lurl('/packages') }}">{{ t('tour.all_packages') }}</a></div>
    <div class="pkgs">
      @foreach ($related as $c)
        @include('site.partials.tour-tile', ['c' => $c, 'span' => 4])
      @endforeach
    </div>
  </div>
</section>
@endsection
