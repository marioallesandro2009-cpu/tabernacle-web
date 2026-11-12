@extends('site.layout', ['solidNav' => true])

@section('content')
@foreach ($page->sections as $section)
    @includeIf('site.sections.'.$section->type, ['s' => $section->data ?? [], 'section' => $section, 'page' => $page])
@endforeach

<section class="sec pk-list dest-sec">
  <div class="wrap">
    @foreach (\App\Support\SiteData::destinations()->groupBy('region') as $region => $items)
      <div class="chapter"><b>{{ t('region.'.$region) }}</b><span class="mono">{{ $items->count() }}</span></div>
      <div class="dest-grid">
        @foreach ($items as $d)
          <a class="dest" href="{{ url('/destinations/'.$d->slug) }}"><b>{{ tr($d->name) }}</b><span class="mono">{{ $d->tours_count }} {{ trim(str_replace(':n', '', t('pk.count'))) }}</span></a>
        @endforeach
      </div>
    @endforeach
  </div>
</section>
@endsection
