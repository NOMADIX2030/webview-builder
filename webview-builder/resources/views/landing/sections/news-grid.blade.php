{{-- 뉴스 – 발행처별 탭 + 검색 + Bootstrap 5 Masonry --}}
<section class="mb-5">

    {{-- 섹션 헤더 --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center">
            <span class="section-bar-red"></span>
            <span class="fw-bold" style="font-size:0.9rem;">최신 뉴스</span>
        </div>
    </div>

    {{-- 탭 + 검색창 --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        {{-- 발행처 탭 --}}
        <div class="news-tab-bar">
            <button class="news-tab-btn active" data-category="yonhap">
                <span class="news-tab-dot" style="background:#e8002d;"></span>
                연합뉴스<span class="news-tab-count" id="count-yonhap"></span>
            </button>
            <button class="news-tab-btn" data-category="techcrunch">
                <span class="news-tab-dot" style="background:#0a8a3c;"></span>
                TechCrunch<span class="news-tab-count" id="count-techcrunch"></span>
            </button>
            <button class="news-tab-btn" data-category="venturebeat">
                <span class="news-tab-dot" style="background:#1e40af;"></span>
                VentureBeat<span class="news-tab-count" id="count-venturebeat"></span>
            </button>
            <button class="news-tab-btn" data-category="mit">
                <span class="news-tab-dot" style="background:#8b1a1a;"></span>
                MIT Review<span class="news-tab-count" id="count-mit"></span>
            </button>
        </div>

        {{-- 검색창 --}}
        <div class="news-search-wrap">
            <input type="search" id="news-search-input" class="news-search-input"
                placeholder="기사 검색…" autocomplete="off" maxlength="50">
            <svg class="news-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </div>
    </div>

    {{-- 검색 결과 없음 --}}
    <div id="news-empty-msg" class="text-center py-5 text-secondary" style="display:none; font-size:0.88rem;">
        검색 결과가 없습니다.
    </div>

    {{-- Masonry 그리드 --}}
    <div class="row g-3" id="news-masonry-grid"
        data-bs-masonry='{"percentPosition": true}'>
        @foreach($news ?? [] as $item)
            <div class="col-6 col-md-4 col-xl-3">
                @include('landing.sections.news-card', ['item' => $item])
            </div>
        @endforeach
    </div>

    {{-- 더보기 버튼 --}}
    <div class="text-center mt-4" id="news-load-more-wrap">
        <button type="button" id="news-load-more-btn"
            class="btn btn-outline-secondary px-5"
            data-page="1"
            style="font-size:0.85rem; border-radius:50px;">
            뉴스 더보기
        </button>
    </div>
</section>

@push('scripts')
<script>
(function () {
    const grid        = document.getElementById('news-masonry-grid');
    const btn         = document.getElementById('news-load-more-btn');
    const wrap        = document.getElementById('news-load-more-wrap');
    const searchInput = document.getElementById('news-search-input');
    const emptyMsg    = document.getElementById('news-empty-msg');
    const tabBtns     = document.querySelectorAll('.news-tab-btn');

    if (!grid) return;

    let currentCategory = 'yonhap';
    let currentQuery    = '';
    let searchTimer     = null;

    // URL 파라미터 news_q: 키워드 태그 클릭 시 검색
    const urlParams = new URLSearchParams(location.search);
    const initialQ = urlParams.get('news_q');
    if (initialQ) {
        currentQuery = initialQ;
        currentCategory = 'all';
        if (searchInput) searchInput.value = initialQ;
    }

    function loadNews(page, append) {
        const params = new URLSearchParams({
            page:     page,
            category: currentCategory,
            q:        currentQuery,
        });

        if (!append) {
            grid.innerHTML = '<div class="col-12 text-center py-5"><span class="spinner-border spinner-border-sm text-secondary"></span></div>';
            wrap.style.display = 'none';
            emptyMsg.style.display = 'none';
            const msnry = Masonry.data(grid);
            if (msnry) msnry.destroy();
        } else {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>불러오는 중…';
        }

        fetch('/api/landing/news?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (!append) grid.innerHTML = '';

            const temp = document.createElement('div');
            temp.innerHTML = data.html;
            const newItems = Array.from(temp.children);

            if (!append && newItems.length === 0) {
                emptyMsg.style.display = 'block';
                wrap.style.display = 'none';
                return;
            }

            newItems.forEach(item => grid.appendChild(item));

            const msnry = Masonry.data(grid);
            if (!append || !msnry) {
                new Masonry(grid, { percentPosition: true, itemSelector: '.col-6, .col-md-4, .col-xl-3' });
            } else {
                msnry.appended(newItems);
            }

            if (data.has_more) {
                wrap.style.display = 'block';
                btn.dataset.page   = data.page;
                btn.disabled       = false;
                btn.innerHTML      = '뉴스 더보기';
            } else {
                wrap.style.display = 'none';
            }
        })
        .catch(err => {
            console.error(err);
            if (append) {
                btn.disabled  = false;
                btn.innerHTML = '뉴스 더보기';
            }
        });
    }

    // 탭별 뉴스 카운트 비동기 로드
    fetch('/api/landing/news/counts', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(counts => {
            Object.entries(counts).forEach(([cat, n]) => {
                const el = document.getElementById('count-' + cat);
                if (el && n > 0) el.textContent = n;
            });
        })
        .catch(() => {});

    tabBtns.forEach(function (tabBtn) {
        tabBtn.addEventListener('click', function () {
            tabBtns.forEach(t => t.classList.remove('active'));
            tabBtn.classList.add('active');
            currentCategory = tabBtn.dataset.category;
            searchInput.value = '';
            currentQuery = '';
            loadNews(1, false);
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                currentQuery = searchInput.value.trim();
                loadNews(1, false);
            }, 300);
        });
    }

    if (btn) {
        btn.addEventListener('click', function () {
            const nextPage = parseInt(btn.dataset.page, 10) + 1;
            loadNews(nextPage, true);
        });
    }

    if (initialQ) loadNews(1, false);
})();
</script>
@endpush
