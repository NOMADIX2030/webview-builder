@extends('layouts.landing')

@section('title', ($article['title'] ?? '뉴스') . ' — ' . ($article['source'] ?? '뉴스'))

@push('styles')
<style>
    .news-detail-wrap {
        max-width: 780px;
        margin: 0 auto;
        padding: 2rem 1rem 4rem;
    }

    /* 상단 브레드크럼 */
    .news-detail-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.78rem;
        color: #94a3b8;
        margin-bottom: 1.5rem;
    }
    .news-detail-breadcrumb a {
        color: #64748b;
        text-decoration: none;
    }
    .news-detail-breadcrumb a:hover { color: var(--brand-primary); }
    .news-detail-breadcrumb .sep { color: #cbd5e1; }

    /* 제목 */
    .news-detail-title {
        font-size: clamp(1.25rem, 3vw, 1.75rem);
        font-weight: 700;
        line-height: 1.45;
        color: #0f172a;
        margin-bottom: 1rem;
    }

    /* 메타 정보 */
    .news-detail-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 0;
        border-top: 2px solid #ef4444;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 1.75rem;
        flex-wrap: wrap;
    }
    .news-detail-meta .source {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: #374151;
    }
    .news-detail-meta .source .dot {
        width: 7px; height: 7px;
        border-radius: 50%;
        background: #ef4444;
        flex-shrink: 0;
    }
    .news-detail-meta .pubdate {
        font-size: 0.78rem;
        color: #94a3b8;
        margin-left: auto;
    }
    .news-detail-meta .author {
        font-size: 0.78rem;
        color: #64748b;
    }

    /* 대표 이미지 */
    .news-detail-thumb {
        width: 100%;
        border-radius: 10px;
        margin-bottom: 1.75rem;
        object-fit: cover;
        max-height: 420px;
    }

    /* 본문 */
    .news-detail-body {
        font-size: 1rem;
        line-height: 1.85;
        color: #1e293b;
    }
    .news-detail-body p {
        margin-bottom: 1rem;
    }
    .news-detail-body table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.88rem;
        margin-bottom: 1.2rem;
        overflow-x: auto;
        display: block;
    }
    .news-detail-body table td,
    .news-detail-body table th {
        border: 1px solid #e2e8f0;
        padding: 0.4rem 0.6rem;
        text-align: center;
    }
    .news-detail-body img {
        max-width: 100%;
        border-radius: 8px;
        margin: 0.5rem 0;
    }
    .news-detail-body a {
        color: var(--brand-primary);
    }

    /* 저작권 안내 */
    .news-detail-copyright {
        margin-top: 2.5rem;
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border-left: 3px solid #ef4444;
        border-radius: 4px;
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.6;
    }

    /* 원문 보기 버튼 */
    .news-detail-orig-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
        padding: 0.65rem 1.5rem;
        background: #ef4444;
        color: #fff;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.2s;
    }
    .news-detail-orig-btn:hover {
        background: #dc2626;
        color: #fff;
    }

    /* 뒤로가기 */
    .news-detail-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.82rem;
        color: #64748b;
        text-decoration: none;
        margin-bottom: 1.5rem;
        transition: color 0.2s;
    }
    .news-detail-back-btn:hover { color: var(--brand-primary); }
</style>
@endpush

@section('content')
<div class="news-detail-wrap">

    {{-- 뒤로가기 --}}
    <a href="{{ route('landing.index') }}" class="news-detail-back-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
        </svg>
        홈으로 돌아가기
    </a>

    {{-- 제목 --}}
    <h1 class="news-detail-title">{{ $article['title'] }}</h1>

    {{-- 메타 --}}
    <div class="news-detail-meta">
        <div class="source">
            <span class="dot"></span>
            {{ $article['source'] ?? '연합뉴스' }}
        </div>
        @php
            $authorLabel = $article['author'] ?? '';
            $sourceName  = $article['source'] ?? '연합뉴스';
            $showAuthor  = !empty($authorLabel) && $authorLabel !== $sourceName;
            $authorSuffix = $sourceName === '연합뉴스' ? ' 기자' : '';
        @endphp
        @if($showAuthor)
            <span class="author">{{ $authorLabel }}{{ $authorSuffix }}</span>
        @endif
        @if(!empty($article['pubDate']))
            <span class="pubdate">{{ $article['pubDate'] }}</span>
        @endif
    </div>

    {{-- 대표 이미지 --}}
    @if(!empty($article['imageUrl']))
        <img src="{{ $article['imageUrl'] }}" alt="{{ $article['title'] }}" class="news-detail-thumb" loading="lazy">
    @endif

    {{-- 본문 --}}
    <div class="news-detail-body">
        {!! $article['body'] !!}
    </div>

    {{-- 저작권 안내 --}}
    @php
        $src = $article['source'] ?? '연합뉴스';
        $copyrightMap = [
            '연합뉴스'   => '©2026 Yonhapnews Agency — 무단 전재·재배포, AI 학습 및 활용 금지',
            'TechCrunch' => '©TechCrunch. All rights reserved.',
        ];
        $copyright = $copyrightMap[$src] ?? '© ' . $src . '. All rights reserved.';
    @endphp
    <div class="news-detail-copyright">
        {{ $copyright }}<br>
        본 콘텐츠는 {{ $src }}에서 제공한 기사입니다.
    </div>

    {{-- 원문 보기 버튼 --}}
    <a href="{{ $article['url'] }}" target="_blank" rel="noopener noreferrer" class="news-detail-orig-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
            <path d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
        </svg>
        {{ $src }} 원문 보기
    </a>

</div>
@endsection
