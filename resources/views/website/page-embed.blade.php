@extends('layouts.website-embed')

@section('content')
    <main class="embed-page">
        <article class="prose">
            {!! $page->content !!}
        </article>
    </main>
@endsection
