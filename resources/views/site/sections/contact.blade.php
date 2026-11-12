@php
    $digits = preg_replace('/\D/', '', (string) setting('wa_number', ''));
    $ig = (string) setting('social_instagram', '');
    $handle = $ig ? '@'.trim((string) basename(rtrim((string) parse_url($ig, PHP_URL_PATH), '/')), '@') : '';
    $others = array_filter(['TikTok' => setting('social_tiktok'), 'Facebook' => setting('social_facebook'), 'YouTube' => setting('social_youtube')]);
@endphp
<section class="sec on-navy contact" id="contact">
  <div class="wrap grid">
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="indent it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    <div class="contact-info" data-stagger>
      <div><span class="mono">{{ t('contact.office') }}</span><p>{{ tr(setting('address')) }}</p>@if (setting('maps_url'))<p style="margin-top:10px"><a href="{{ setting('maps_url') }}" target="_blank" rel="noopener">{{ t('contact.maps') }}</a></p>@endif</div>
      <div>
        <span class="mono">{{ t('contact.phone') }}</span><p><a href="tel:+{{ $digits }}">{{ setting('wa_display') }}</a></p>
        <span class="mono" style="margin-top:22px">{{ t('contact.email') }}</span><p><a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a></p>
      </div>
      <div>
        @if ($handle)<span class="mono">{{ t('contact.instagram') }}</span><p><a href="{{ $ig }}" target="_blank" rel="noopener">{{ $handle }}</a></p>@endif
        @foreach ($others as $name => $link)<p style="margin-top:8px"><a href="{{ $link }}" target="_blank" rel="noopener">{{ $name }}</a></p>@endforeach
        @if (tr(setting('hours')))<span class="mono" style="margin-top:22px">{{ t('contact.hours') }}</span><p>{{ tr(setting('hours')) }}</p>@endif
      </div>
    </div>
    <div class="contact-actions">
      <a class="btn btn-amber" href="{{ wa_link(t('wa.plan')) }}" data-wa>{{ tr($s['btn_wa'] ?? null) }} <svg aria-hidden="true"><use href="#arrow"/></svg></a>
      <a class="btn" href="mailto:{{ setting('contact_email') }}">{{ tr($s['btn_email'] ?? null) }}</a>
    </div>
    @if (! empty($s['show_form']))
      <div class="contact-form-wrap">@include('site.partials.lead-form', ['tourId' => null])</div>
    @endif
    @if (! empty($s['image']))
    <div class="contact-pic pic" data-reveal><img {!! media_attrs($s['image'], '(max-width: 1400px) 100vw, 1400px', tr($s['image_alt'] ?? null) ?: null) !!} loading="lazy"></div>
    @endif
  </div>
</section>
