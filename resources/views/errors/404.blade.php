@php($seo = \App\Support\Seo::make(t('e404.title'), t('e404.text'), noindex: true))
@extends('site.layout', ['solidNav' => true, 'bodyClass' => 'page-404'])

@section('content')
<section class="pk-hero e404">
  <div class="wrap grid">
    <p class="kicker mono">404</p>
    <h1 class="display h-xl"><span class="ln"><span>{{ t('e404.title') }}</span></span></h1>
    <p class="pk-intro">{{ t('e404.text') }}<br><br><a class="btn btn-navy" href="{{ url('/') }}">{{ t('e404.back') }} <svg aria-hidden="true"><use href="#arrow"/></svg></a></p>
  </div>
</section>
@endsection
