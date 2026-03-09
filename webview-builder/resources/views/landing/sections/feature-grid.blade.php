{{-- 바로가기 섹션 --}}
<section class="mb-5">
    <div class="d-flex align-items-center mb-3">
        <span class="section-bar-blue"></span>
        <span class="fw-bold" style="font-size:0.9rem;">바로가기</span>
    </div>
    <div class="row row-cols-3 row-cols-sm-4 row-cols-md-5 row-cols-lg-6 g-2" id="feature-grid">
        @foreach($features ?? [] as $feature)
            @include('landing.sections.feature-card', ['feature' => $feature])
        @endforeach
    </div>
</section>
