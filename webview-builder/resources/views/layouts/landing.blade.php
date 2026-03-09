<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $logoText ?? '홈')</title>
    <meta name="description" content="비즈니스 업무에 도움되는 유용한 기능들을 한곳에서">

    {{-- Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">

    @stack('styles')
    <style>
        :root {
            --brand-primary: #2563eb;
            --brand-red: #ef4444;
        }
        body {
            font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        /* 헤더 글래스모피즘 */
        .landing-navbar {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            background-color: rgba(255,255,255,0.85) !important;
            border-bottom: 1px solid rgba(0,0,0,0.07);
        }
        /* 기능 카드 호버 */
        .feature-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: inherit;
        }
        .feature-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.10) !important;
            border-color: #bfdbfe;
            color: inherit;
        }
        .feature-card .icon-wrap {
            transition: background-color 0.15s;
        }
        .feature-card:hover .icon-wrap {
            background-color: #dbeafe !important;
        }
        /* 뉴스 카드 */
        .news-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: inherit;
            display: block;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .news-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.10) !important;
            color: inherit;
        }
        .news-card .news-thumb {
            aspect-ratio: 16/9;
            width: 100%;
            object-fit: cover;
            display: block;
            background: #f1f5f9;
        }
        .news-card .news-thumb-placeholder {
            aspect-ratio: 16/9;
            width: 100%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #f1f5f9;
        }
        .news-card .news-thumb-placeholder::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }
        .news-card .news-thumb-watermark {
            position: relative;
            font-size: clamp(1rem, 3vw, 1.5rem);
            font-weight: 900;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(220, 38, 38, 0.38);
            user-select: none;
            line-height: 1;
        }
        .news-card .news-thumb-watermark span {
            display: block;
            font-size: 0.55em;
            letter-spacing: 0.3em;
            margin-top: 6px;
            text-align: center;
            color: rgba(220, 38, 38, 0.28);
        }
        .news-card .card-body { padding: 12px; }
        .news-card .card-title {
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 6px;
            color: #0f172a;
        }
        .news-card .card-text {
            font-size: 0.72rem;
            color: #64748b;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }
        /* 섹션 강조바 */
        .section-bar-blue { display:inline-block; width:4px; height:20px; border-radius:9999px; background:#2563eb; margin-right:8px; vertical-align:middle; }
        .section-bar-red  { display:inline-block; width:4px; height:20px; border-radius:9999px; background:#ef4444; margin-right:8px; vertical-align:middle; }
        /* 검색창 */
        .search-group {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
        }
        .search-group .form-control {
            border: none;
            box-shadow: none;
            font-size: 0.9rem;
        }
        .search-group .form-control:focus { box-shadow: none; }
        .search-group .input-group-text { border: none; background: #fff; }
        .search-group .btn-search {
            border-radius: 0 12px 12px 0;
            padding: 0 20px;
            background: #2563eb;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .search-group .btn-search:hover { background: #1d4ed8; }

        /* ── 뉴스 발행처 탭 ── */
        .news-tab-bar {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .news-tab-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
            display: inline-block;
        }
        .news-tab-bar { gap: 4px; }
        .news-tab-btn {
            padding: 0.3rem 0.75rem;
            border-radius: 50px;
            border: 1px solid #e2e8f0;
            background: #fff;
            font-size: 0.78rem;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .news-tab-btn:hover {
            border-color: #94a3b8;
            color: #374151;
        }
        .news-tab-btn.active {
            background: #ef4444;
            border-color: #ef4444;
            color: #fff;
            font-weight: 600;
        }
        .news-tab-badge {
            font-size: 0.62rem;
            font-weight: 700;
            background: #f97316;
            color: #fff;
            padding: 0 4px;
            border-radius: 3px;
            line-height: 1.4;
        }
        .news-tab-btn.active .news-tab-badge {
            background: rgba(255,255,255,0.35);
            color: #fff;
        }
        .news-tab-count {
            display: inline-block;
            min-width: 18px;
            padding: 0 4px;
            height: 16px;
            line-height: 16px;
            border-radius: 8px;
            font-size: 0.62rem;
            font-weight: 700;
            background: rgba(0,0,0,0.08);
            color: #64748b;
            text-align: center;
            margin-left: 4px;
            vertical-align: middle;
        }
        .news-tab-btn.active .news-tab-count {
            background: rgba(255,255,255,0.25);
            color: #fff;
        }

        /* ── 뉴스 검색창 ── */
        .news-search-wrap {
            position: relative;
            flex-shrink: 0;
        }
        .news-search-input {
            padding: 0.32rem 0.75rem 0.32rem 2rem;
            border: 1px solid #e2e8f0;
            border-radius: 50px;
            font-size: 0.78rem;
            color: #374151;
            outline: none;
            width: 170px;
            transition: border-color 0.15s, width 0.2s;
            background: #fff;
        }
        .news-search-input:focus {
            border-color: #94a3b8;
            width: 220px;
        }
        .news-search-input::placeholder { color: #cbd5e1; }
        .news-search-icon {
            position: absolute;
            left: 0.55rem;
            top: 50%;
            transform: translateY(-50%);
            width: 13px; height: 13px;
            color: #94a3b8;
            pointer-events: none;
        }
    </style>
</head>
<body>

    {{-- 헤더 --}}
    <nav class="navbar fixed-top landing-navbar py-0">
        <div class="container-xl d-flex align-items-center justify-content-between" style="height:56px;">
            <a href="{{ route('landing.index') }}" class="navbar-brand d-flex align-items-center gap-2 mb-0 py-0">
                @if(!empty($logoImage))
                    <img src="{{ str_starts_with($logoImage, 'http') ? $logoImage : asset($logoImage) }}"
                        alt="" style="height:32px; width:auto; max-width:120px; object-fit:contain; flex-shrink:0;">
                @endif
                <span class="fw-semibold fs-6 text-dark">{{ $logoText ?? '홈' }}</span>
            </a>
            <a href="{{ route('landing.settings') }}" class="btn btn-link text-secondary p-2" aria-label="설정">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </a>
        </div>
    </nav>

    {{-- 메인 --}}
    <main class="pt-5 pb-5" style="margin-top:56px;">
        <div class="container-xl">
            @yield('content')
        </div>
    </main>

    {{-- Masonry.js (Bootstrap 5 공식 optional dep) --}}
    <script src="https://cdn.jsdelivr.net/npm/masonry-layout@4.2.2/dist/masonry.pkgd.min.js"></script>
    {{-- Bootstrap 5 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
