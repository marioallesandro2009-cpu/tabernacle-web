<section class="on-navy nums">
  <div class="wrap">
    <ul data-stagger>
      @foreach ($s['items'] ?? [] as $it)
        @php
            $val = (int) ($it['value'] ?? 0);
            $suffix = (string) ($it['suffix'] ?? '');
            $shown = ! empty($it['comma']) ? number_format($val) : (string) $val;
        @endphp
        <li><b data-count="{{ $val }}" @if (! empty($it['comma'])) data-fmt="comma" @endif @if ($suffix !== '') data-suffix="{{ $suffix }}" @endif>{{ $shown }}{{ $suffix }}</b><span class="mono">{{ tr($it['label'] ?? null) }}</span></li>
      @endforeach
    </ul>
  </div>
</section>
