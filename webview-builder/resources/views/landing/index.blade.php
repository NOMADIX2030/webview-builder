@extends('layouts.landing')

@section('title', '홈')

@section('content')
    <div class="flex flex-col gap-8">
        @foreach($sections as $section)
            @include("landing.sections.{$section['id']}", [
                'section' => $section,
                'features' => $section['id'] === 'feature-grid' ? $features : null,
                'news' => $section['id'] === 'news-grid' ? ($news ?? []) : null,
            ])
        @endforeach
    </div>
@endsection
