@extends('layouts.website')

@section('content')
    <section class="page-hero">
        <div class="container">
            <p class="page-hero__eyebrow">{{ $appName }}</p>
            <h1 class="page-hero__title">{{ $page->title }}</h1>
            @if ($page->updated_at)
                <p class="page-hero__meta">Last updated {{ $page->updated_at->format('d F Y') }}</p>
            @endif
        </div>
    </section>

    <section class="page-content">
        <div class="container">
            <article class="page-content__card reveal visible">
                <div class="prose">
                    {!! $page->content !!}
                </div>
            </article>
        </div>
    </section>
@endsection
