@php
    $title      = $item['title'] ?? '';
    $link       = $item['link'] ?? '#';
    $description = $item['description'] ?? '';
    $imageUrl   = $item['imageUrl'] ?? null;
    $timeAgo    = $item['timeAgo'] ?? '';
    $source     = $item['source'] ?? '연합뉴스';
    $isExternal = $item['isExternal'] ?? false;

    // 소스별 브랜드 색상 + 워터마크 텍스트
    $sourceMeta = [
        '연합뉴스'    => ['dot' => '#e8002d', 'wm1' => 'YONHAP', 'wm2' => 'NEWS AGENCY'],
        'TechCrunch'  => ['dot' => '#0a8a3c', 'wm1' => 'TC',     'wm2' => 'TECHCRUNCH'],
        'VentureBeat' => ['dot' => '#1e40af', 'wm1' => 'VB',     'wm2' => 'VENTUREBEAT'],
        'MIT Review'  => ['dot' => '#8b1a1a', 'wm1' => 'MIT',    'wm2' => 'TECH REVIEW'],
    ];
    $meta      = $sourceMeta[$source] ?? ['dot' => '#94a3b8', 'wm1' => strtoupper($source), 'wm2' => ''];
    $sourceDot = $meta['dot'];
    $wm1       = $meta['wm1'];
    $wm2       = $meta['wm2'];

    // 내부 상세 페이지 지원 소스 목록 (연합뉴스 + TechCrunch)
    $internalSources = ['연합뉴스', 'TechCrunch'];
    $useInternal = in_array($source, $internalSources) && $link !== '#';

    $cardUrl = $useInternal
        ? route('news.detail', ['u' => base64_encode($link)])
        : $link;
    $isExternalLink = ! $useInternal;
@endphp
<a href="{{ $cardUrl }}" {{ $isExternalLink ? 'target="_blank" rel="noopener noreferrer"' : '' }} class="news-card shadow-sm">

    {{-- 이미지 / 워터마크 --}}
    @if($imageUrl)
        <img src="{{ $imageUrl }}" alt="" loading="lazy" class="news-thumb"
            onerror="this.outerHTML='<div class=\'news-thumb-placeholder\'><p class=\'news-thumb-watermark\'>{{ $wm1 }}<span>{{ $wm2 }}</span></p></div>'">
    @else
        <div class="news-thumb-placeholder">
            <p class="news-thumb-watermark">{{ $wm1 }}<span>{{ $wm2 }}</span></p>
        </div>
    @endif

    {{-- 텍스트 --}}
    <div class="card-body">
        <p class="card-title">{{ $title }}</p>
        @if($description)
            <p class="card-text">{{ $description }}</p>
        @endif
        <div class="d-flex align-items-center justify-content-between mt-2">
            <div class="d-flex align-items-center gap-1">
                <span style="width:6px;height:6px;border-radius:50%;background:{{ $sourceDot }};display:inline-block;flex-shrink:0;"></span>
                <span style="font-size:0.7rem;color:#94a3b8;">{{ $source }}</span>
            </div>
            @if($timeAgo)
                <span style="font-size:0.7rem;color:#94a3b8;">{{ $timeAgo }}</span>
            @endif
        </div>
    </div>
</a>
