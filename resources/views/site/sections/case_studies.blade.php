@php($cases = \App\Models\CaseStudy::where('is_active', true)->orderBy('sort')->orderByDesc('id')->limit(max(1, (int) ($s['limit'] ?? 3)))->get())
@if ($cases->isNotEmpty())
<section class="sec on-paper">
  <div class="wrap">
    @if (tr($s['kicker'] ?? null))<p class="kicker mono">{{ tr($s['kicker'] ?? null) }}</p>@endif
    <div class="chapter"><b>{{ tr($s['title_a'] ?? null) ?: (app()->getLocale() === 'en' ? 'Case studies' : 'Studi kasus') }}</b><a class="mono" href="{{ lurl('/studi-kasus') }}">{{ app()->getLocale() === 'en' ? 'All case studies' : 'Semua studi kasus' }}</a></div>
    @include('site.partials.case-cards', ['cases' => $cases])
  </div>
</section>
@endif
