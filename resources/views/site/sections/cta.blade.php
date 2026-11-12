<section class="sec ab-cta on-sand">
  <div class="wrap grid">
    <h2 class="display h-l" data-lines>{{ tr($s['title_a'] ?? null) }} <span class="it">{{ tr($s['title_b'] ?? null) }}</span></h2>
    <a class="btn btn-navy" {!! link_attrs($s['btn_url'] ?? '') !!}>{{ tr($s['btn_label'] ?? null) }} <svg aria-hidden="true"><use href="#arrow"/></svg></a>
  </div>
</section>
