<section class="sec legal on-sand" id="legal">
  <div class="wrap grid">
    <h2 class="display h-m" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    <dl>
      @foreach ($s['rows'] ?? [] as $r)
        <div><dt>{{ $r['label'] ?? '' }}</dt><dd @if (! empty($r['scramble'])) data-scramble @endif>{{ $r['value'] ?? '' }}</dd></div>
      @endforeach
    </dl>
  </div>
</section>
