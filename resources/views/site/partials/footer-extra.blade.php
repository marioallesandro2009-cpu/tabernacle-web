@php
    $en = app()->getLocale() === 'en';
    $pillarLinks = \App\Support\Content::remember('footer:pillars', fn () => \App\Models\Pillar::where('is_active', true)->orderBy('sort')->get(['slug', 'title']));
    // trust pages: listed only once the admin has filled them in and switched them on
    $trust = \App\Support\Content::remember('footer:trust', fn () => \App\Models\Page::where('is_active', true)->where('is_system', false)
        ->whereIn('slug', ['kebijakan-privasi', 'syarat-ketentuan', 'karier', 'media', 'mitra'])->get(['slug', 'title']));
    $hasCases = \App\Support\Content::remember('footer:cases', fn () => \App\Models\CaseStudy::where('is_active', true)->exists());
    $social = array_filter(['Instagram' => setting('social_instagram'), 'TikTok' => setting('social_tiktok'), 'Facebook' => setting('social_facebook'), 'YouTube' => setting('social_youtube')]);
    $legal = array_filter([
        setting('legal_nib') ? 'NIB '.setting('legal_nib') : null,
        setting('legal_ahu') ? 'AHU '.setting('legal_ahu') : null,
        setting('legal_license'),
    ]);
    $founded = preg_match('/^(19|20)\d{2}$/', (string) setting('company_founded')) ? (string) setting('company_founded') : null;
@endphp
<div class="foot-cols">
  @if ($pillarLinks->isNotEmpty())
    <div class="foot-col">
      <p class="foot-h mono">{{ $en ? 'Services' : 'Layanan' }}</p>
      @foreach ($pillarLinks as $p)<a href="{{ lurl('/layanan/'.$p->slug) }}">{{ tr($p->title) }}</a>@endforeach
    </div>
  @endif
  @if ($trust->isNotEmpty() || $hasCases)
    <div class="foot-col">
      <p class="foot-h mono">{{ $en ? 'Company' : 'Perusahaan' }}</p>
      @if ($hasCases)<a href="{{ lurl('/studi-kasus') }}">{{ $en ? 'Case studies' : 'Studi kasus' }}</a>@endif
      @foreach ($trust as $p)<a href="{{ lurl('/'.$p->slug) }}">{{ tr($p->title) }}</a>@endforeach
    </div>
  @endif
  <div class="foot-col">
    <p class="foot-h mono">{{ $en ? 'Contact' : 'Kontak' }}</p>
    @if (setting('wa_number'))<a href="{{ wa_link() }}" data-wa>WhatsApp {{ setting('wa_display') }}</a>@endif
    @if (setting('contact_email'))<a href="mailto:{{ setting('contact_email') }}">{{ setting('contact_email') }}</a>@endif
    @foreach ($social as $name => $url)<a href="{{ $url }}" target="_blank" rel="noopener">{{ $name }}</a>@endforeach
  </div>
</div>
<div class="foot-legal mono">
  <span>© {{ now()->year }} {{ setting('company_name') ?: setting('site_name') }}</span>
  @if ($founded)<span>{{ $en ? 'Since' : 'Berdiri sejak' }} {{ $founded }}</span>@endif
  @foreach ($legal as $l)<span>{{ $l }}</span>@endforeach
</div>
