@php $faqs = \App\Support\SiteData::faqs(); @endphp
@if ($faqs->isNotEmpty())
@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => tr($f->question), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags(tr($f->answer))]])->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush
<section class="sec faq-sec">
  <div class="wrap grid">
    <p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>
    <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    <div class="faq-list">
      @foreach ($faqs as $f)
        <details><summary>{{ tr($f->question) }}</summary><div class="prose">{!! tr($f->answer) !!}</div></details>
      @endforeach
    </div>
  </div>
</section>
@endif
