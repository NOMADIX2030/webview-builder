{{-- YTN 유튜브 뉴스 (넷플릭스 스타일 슬라이딩, 모달 재생) --}}
<section class="mb-5" id="ytn-youtube-section">
    <div class="d-flex align-items-center mb-3">
        <span class="section-bar-red"></span>
        <span class="fw-bold" style="font-size:0.9rem;">YTN 뉴스 영상</span>
        <a href="https://www.youtube.com/@ytnnews24" target="_blank" rel="noopener noreferrer" class="ms-2 text-decoration-none" style="font-size:0.75rem; color:#64748b;">채널 보기 →</a>
    </div>

    @if(!empty($ytnVideos) && $ytnVideos->isNotEmpty())
        <div class="ytn-carousel-wrap position-relative">
            <div class="ytn-carousel" id="ytnCarousel">
                @foreach($ytnVideos as $video)
                    <div class="ytn-card" data-video-id="{{ $video['id'] }}" data-video-title="{{ e($video['title']) }}" role="button" tabindex="0">
                        <div class="ytn-card-thumb">
                            <img src="{{ $video['thumbnail'] }}" alt="" loading="lazy" decoding="async" width="320" height="180">
                            <div class="ytn-card-play" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:48px;height:48px;filter:drop-shadow(0 2px 8px rgba(0,0,0,0.5));">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ytn-card-body">
                            <div class="ytn-card-title">{{ Str::limit($video['title'], 50) }}</div>
                            @if(!empty($video['description']))
                                <div class="ytn-card-desc">{{ Str::limit($video['description'], 80) }}</div>
                            @endif
                            @if(!empty($video['published']))
                                <div class="ytn-card-time">{{ \Carbon\Carbon::parse($video['published'])->locale('ko')->diffForHumans() }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="ytn-carousel-fade ytn-carousel-fade-left" aria-hidden="true"></div>
            <div class="ytn-carousel-fade ytn-carousel-fade-right" aria-hidden="true"></div>
        </div>

        {{-- 모달: 클릭 시 iframe 로드 (Facade 패턴 - 초기 로드 없음) --}}
        <div class="modal fade" id="ytnVideoModal" tabindex="-1" aria-labelledby="ytnVideoModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
                <div class="modal-content bg-dark">
                    <div class="modal-header border-secondary py-2">
                        <h5 class="modal-title text-white text-truncate flex-grow-1" id="ytnVideoModalLabel"></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body p-0 position-relative" style="aspect-ratio: 16/9;">
                        <div id="ytnVideoEmbed" class="ratio ratio-16x9 w-100 h-100">
                            {{-- iframe은 모달 열릴 때만 삽입 (lazy) --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="rounded-3 p-4 text-center" style="background:#f8fafc; border:1px solid #e2e8f0;">
            <span style="font-size:0.85rem; color:#94a3b8;">YTN 뉴스 영상을 불러올 수 없습니다.</span>
        </div>
    @endif
</section>

@push('styles')
<style>
/* YTN 넷플릭스 스타일 캐러셀 */
.ytn-carousel-wrap {
    margin-left: calc(-1 * var(--bs-gutter-x, 0.75rem));
    margin-right: calc(-1 * var(--bs-gutter-x, 0.75rem));
    padding-left: var(--bs-gutter-x, 0.75rem);
    padding-right: var(--bs-gutter-x, 0.75rem);
}
.ytn-carousel {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 8px;
}
.ytn-carousel::-webkit-scrollbar {
    height: 6px;
}
.ytn-carousel::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}
.ytn-carousel::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 3px;
}
.ytn-card {
    flex: 0 0 min(180px, 45vw);
    scroll-snap-align: start;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
}
.ytn-card:hover {
    transform: scale(1.03);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
.ytn-card:focus-visible {
    outline: 2px solid var(--brand-red, #ef4444);
    outline-offset: 2px;
}
.ytn-card-thumb {
    position: relative;
    aspect-ratio: 16/9;
    overflow: hidden;
}
.ytn-card-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.ytn-card-play {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.95);
    opacity: 0.85;
}
.ytn-card:hover .ytn-card-play { opacity: 1; }
.ytn-card-body {
    padding: 12px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 0;
}
.ytn-card-title {
    font-size: 0.85rem;
    font-weight: 600;
    line-height: 1.35;
    color: #0f172a;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.ytn-card-desc {
    font-size: 0.75rem;
    line-height: 1.4;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.ytn-card-time {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: auto;
    padding-top: 4px;
}
.ytn-carousel-fade {
    position: absolute;
    top: 0;
    bottom: 28px;
    width: 40px;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
}
.ytn-carousel-wrap:hover .ytn-carousel-fade { opacity: 1; }
.ytn-carousel-fade-left {
    left: 0;
    background: linear-gradient(to right, rgba(248,250,252,0.95), transparent);
}
.ytn-carousel-fade-right {
    right: 0;
    background: linear-gradient(to left, rgba(248,250,252,0.95), transparent);
}
</style>
@endpush

@if(!empty($ytnVideos) && $ytnVideos->isNotEmpty())
@push('scripts')
<script>
(function() {
    const modal = document.getElementById('ytnVideoModal');
    const embedEl = document.getElementById('ytnVideoEmbed');
    const titleEl = document.getElementById('ytnVideoModalLabel');

    function openVideo(videoId, title) {
        if (!modal || !embedEl) return;
        titleEl.textContent = title || '';
        embedEl.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1&rel=0&start=1" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="w-100 h-100" loading="lazy" title="YTN 뉴스 영상"></iframe>';
        new bootstrap.Modal(modal).show();
    }

    function closeVideo() {
        if (embedEl) embedEl.innerHTML = '';
    }

    modal.addEventListener('hidden.bs.modal', closeVideo);

    document.querySelectorAll('.ytn-card').forEach(function(card) {
        card.addEventListener('click', function() {
            const id = this.dataset.videoId;
            const title = this.dataset.videoTitle || '';
            if (id) openVideo(id, title);
        });
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
})();
</script>
@endpush
@endif
