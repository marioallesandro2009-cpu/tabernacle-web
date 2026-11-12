<section class="sec terms-sec">
  <div class="wrap">
    <div class="pkg-end">
      <div class="pkg-private on-navy">
        <p class="kicker mono">{{ tr($s['private_kicker'] ?? null) }}</p>
        <h3 class="display h-m">{{ tr($s['private_title_a'] ?? null) }} <span class="it">{{ tr($s['private_title_b'] ?? null) }}</span></h3>
        <p>{{ tr($s['private_text'] ?? null) }}</p>
        <a class="btn btn-amber" href="{{ wa_link(t('wa.plan')) }}" data-wa>{{ tr($s['private_btn'] ?? null) }} <svg aria-hidden="true"><use href="#arrow"/></svg></a>
      </div>
      <div class="pkg-terms">
        <h3>{{ tr($s['terms_title'] ?? null) }}</h3>
        <ul>
          @foreach ($s['terms'] ?? [] as $term)
            <li>{{ tr($term['text'] ?? null) }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</section>
