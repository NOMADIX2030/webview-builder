@extends('layouts.landing')

@section('title', '홈')

@section('content')
    <div class="flex flex-col gap-8">
        @foreach($sections as $section)
            @include("landing.sections.{$section['id']}", [
                'section' => $section,
                'features' => $section['id'] === 'feature-grid' ? $features : null,
                'news' => $section['id'] === 'news-grid' ? ($news ?? []) : null,
                'todaySummary' => $section['id'] === 'today-summary' ? ($todaySummary ?? null) : null,
                'ytnVideos' => $section['id'] === 'ytn-youtube-section' ? ($ytnVideos ?? collect()) : null,
                'weatherData' => $section['id'] === 'weather-section' ? ($weatherData ?? null) : null,
            ])
        @endforeach
    </div>
@endsection
