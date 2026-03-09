{{-- 히어로 검색 섹션 --}}
<section class="text-center py-5 py-lg-6">
    <p class="text-primary fw-semibold mb-2" style="font-size:0.72rem; letter-spacing:0.1em; text-transform:uppercase;">WORKSPACE HUB</p>
    <h1 class="fw-bold mb-2" style="font-size:clamp(1.6rem, 4vw, 2.4rem); letter-spacing:-0.02em;">
        {{ $logoText ?? '홈' }}
    </h1>
    <p class="text-secondary mb-4" style="font-size:0.88rem;">비즈니스 업무에 도움되는 유용한 기능들을 한곳에서</p>

    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <form action="https://www.google.com/search" method="GET" target="_blank" id="landing-search-form">
                <div class="input-group search-group" style="height:50px;">
                    <span class="input-group-text ps-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#94a3b8" style="width:18px;height:18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </span>
                    <input type="text" name="q" id="landing-search-input"
                        class="form-control bg-white"
                        placeholder="Google 검색..."
                        autocomplete="off">
                    <button type="submit" class="btn btn-search text-white">검색</button>
                </div>
            </form>
        </div>
    </div>
</section>
