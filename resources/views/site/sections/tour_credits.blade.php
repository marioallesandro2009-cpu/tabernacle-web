@php
    $credits = collect(\App\Support\SiteData::tourCards())->pluck('credit')->filter()->unique()->values();
@endphp
@if ($credits->isNotEmpty())
<section class="credits-sec">
  <div class="wrap">
    <details class="credits" id="credits"><summary class="mono">{{ t('pk.credits') }}</summary><p>
      @foreach ($credits as $cr)
        @php [$label, $link] = array_pad(explode(' | ', $cr, 2), 2, null); @endphp
        @if ($link)<a href="{{ $link }}" target="_blank" rel="noopener">{{ html_entity_decode($label) }}</a>@else{{ html_entity_decode($label) }}@endif{{ $loop->last ? '.' : '; ' }}
      @endforeach
      {{ t('pk.credits_note') }}
    </p></details>
  </div>
</section>
@endif
