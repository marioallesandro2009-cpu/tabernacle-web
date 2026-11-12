@php $videoUrl = media_url($s['video'] ?? null); @endphp
@if ($videoUrl)
<section class="film" id="film">
  <div class="film-frame">
    <video src="{{ $videoUrl }}" @if (! empty($s['poster'])) poster="{{ media_url($s['poster'], 1600) }}" @endif muted loop playsinline preload="metadata" aria-label="{{ tr($s['aria'] ?? null) }}"></video>
  </div>
  <div class="film-cap">
    <div class="wrap">
      <div>
        <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
        <h2 class="display h-m">{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
      </div>
      <button class="play" type="button" data-film="{{ $videoUrl }}"><span class="play-ico"><svg aria-hidden="true"><use href="#play"/></svg></span><span class="mono">{{ tr($s['play_label'] ?? null) }}</span></button>
    </div>
  </div>
</section>
@endif
