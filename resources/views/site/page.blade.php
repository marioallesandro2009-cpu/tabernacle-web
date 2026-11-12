@extends('site.layout', ['solidNav' => ! in_array($page->key, ['home'], true) && ! ($page->sections->first()?->type === 'hero')])

@section('content')
@foreach ($page->sections as $section)
    @includeIf('site.sections.'.$section->type, ['s' => $section->data ?? [], 'section' => $section, 'page' => $page])
@endforeach
@endsection
