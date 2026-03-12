{{-- 오늘의 주요 뉴스 (Groq AI 복합형) --}}
<section class="mb-5" id="today-summary-section">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center">
            <span class="section-bar-blue"></span>
            <span class="fw-bold" style="font-size:0.9rem;">오늘의 주요 뉴스</span>
        </div>
        @if(!empty($todaySummary['updated_at']))
            <span class="text-secondary" style="font-size:0.7rem;">{{ $todaySummary['updated_at'] }} 업데이트</span>
        @endif
    </div>

    @if(!empty($todaySummary))
        {{-- 한 줄 요약 --}}
        @if(!empty($todaySummary['summary']))
            <div class="rounded-3 p-3 mb-3" style="background:linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); border:1px solid #e2e8f0;">
                <p class="mb-0" style="font-size:0.9rem; line-height:1.5; color:#1e293b;">{{ $todaySummary['summary'] }}</p>
            </div>
        @endif

        {{-- AI 선정 TOP 6 --}}
        @if(!empty($todaySummary['top3']) && count($todaySummary['top3']) > 0)
            <div class="row g-2 mb-3">
                @foreach($todaySummary['top3'] as $idx => $item)
                    @php
                        $title  = $item['title'] ?? '';
                        $link   = $item['link'] ?? '#';
                        $source = $item['source'] ?? '';
                        $useInternal = !empty($item['internal']);
                        $cardUrl = $useInternal && !empty($item['detail_param'])
                            ? route('news.detail', ['u' => $item['detail_param']])
                            : $link;
                    @endphp
                    <div class="col-12 col-md-4">
                        <a href="{{ $cardUrl }}" {{ !$useInternal ? 'target="_blank" rel="noopener noreferrer"' : '' }}
                            class="d-flex align-items-start gap-2 rounded-3 p-3 text-decoration-none text-dark border shadow-sm bg-white"
                            style="min-height:72px; transition: transform 0.15s, box-shadow 0.15s;">
                            <span class="badge rounded-pill bg-primary" style="font-size:0.65rem; min-width:22px;">{{ $idx + 1 }}</span>
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="mb-0 fw-semibold" style="font-size:0.8rem; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">{{ $title }}</p>
                                @if($source)
                                    <span style="font-size:0.68rem; color:#94a3b8;">{{ $source }}</span>
                                @endif
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- 키워드 태그 --}}
        @if(!empty($todaySummary['keywords']) && count($todaySummary['keywords']) > 0)
            <div class="d-flex flex-wrap gap-2">
                @foreach($todaySummary['keywords'] as $kw)
                    <a href="{{ url('/') }}?news_q={{ urlencode($kw) }}#news-masonry-grid"
                        class="badge rounded-pill text-decoration-none"
                        style="font-size:0.72rem; padding:0.35rem 0.65rem; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;">
                        {{ $kw }}
                    </a>
                @endforeach
            </div>
        @endif
    @else
        {{-- 캐시 미스: 로딩 플레이스홀더 + JS로 API 호출 --}}
        <div id="today-summary-placeholder" class="rounded-3 p-4 text-center" style="background:#f8fafc; border:1px solid #e2e8f0;">
            <span class="spinner-border spinner-border-sm text-secondary me-2"></span>
            <span style="font-size:0.85rem; color:#64748b;">오늘의 요약을 불러오는 중…</span>
        </div>
        <div id="today-summary-content" style="display:none;"></div>
    @endif
</section>

@if(empty($todaySummary))
@push('scripts')
<script>
(function() {
    const el = document.getElementById('today-summary-placeholder');
    const contentEl = document.getElementById('today-summary-content');
    if (!el) return;

    fetch('/api/landing/today-summary', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (data.error) throw new Error(data.error);
            renderTodaySummary(data);
        })
        .catch(() => {
            el.innerHTML = '<span style="font-size:0.85rem; color:#94a3b8;">요약을 불러올 수 없습니다.</span>';
        });

    function renderTodaySummary(d) {
        let html = '';
        if (d.summary) {
            html += '<div class="rounded-3 p-3 mb-3" style="background:linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); border:1px solid #e2e8f0;">';
            html += '<p class="mb-0" style="font-size:0.9rem; line-height:1.5; color:#1e293b;">' + escapeHtml(d.summary) + '</p></div>';
        }
        if (d.top3 && d.top3.length) {
            html += '<div class="row g-2 mb-3">';
            d.top3.forEach((item, i) => {
                const internal = item.internal === true;
                const url = internal && item.detail_param ? '/news/detail?u=' + item.detail_param : (item.link || '#');
                const target = internal ? '' : ' target="_blank" rel="noopener noreferrer"';
                html += '<div class="col-12 col-md-4"><a href="' + url + '"' + target + ' class="d-flex align-items-start gap-2 rounded-3 p-3 text-decoration-none text-dark border shadow-sm bg-white" style="min-height:72px;"><span class="badge rounded-pill bg-primary" style="font-size:0.65rem;">' + (i+1) + '</span><div class="flex-grow-1 overflow-hidden"><p class="mb-0 fw-semibold" style="font-size:0.8rem; line-height:1.35; -webkit-line-clamp:2; overflow:hidden;">' + escapeHtml(item.title || '') + '</p><span style="font-size:0.68rem; color:#94a3b8;">' + escapeHtml(item.source || '') + '</span></div></a></div>';
            });
            html += '</div>';
        }
        if (d.keywords && d.keywords.length) {
            html += '<div class="d-flex flex-wrap gap-2">';
            d.keywords.forEach(k => {
                html += '<a href="/?news_q=' + encodeURIComponent(k) + '#news-masonry-grid" class="badge rounded-pill text-decoration-none" style="font-size:0.72rem; padding:0.35rem 0.65rem; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;">' + escapeHtml(k) + '</a>';
            });
            html += '</div>';
        }
        if (html) {
            el.style.display = 'none';
            contentEl.innerHTML = html;
            contentEl.style.display = 'block';
        }
    }
    function escapeHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
})();
</script>
@endpush
@endif
