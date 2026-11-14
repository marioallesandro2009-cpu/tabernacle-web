@extends('site.layout', ['solidNav' => true])

@section('content')
<section class="pk-hero">
  <div class="wrap grid">
    <h1 class="display h-xl"><span class="ln"><span>{{ $title }}</span></span></h1>
  </div>
</section>
<section class="sec pk-list">
  <div class="wrap">
    @include('site.partials.case-cards', ['cases' => $cases])
  </div>
</section>
@endsection
