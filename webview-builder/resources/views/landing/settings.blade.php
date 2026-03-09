@extends('layouts.landing')

@section('title', '설정')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8 col-xl-7">

        {{-- 페이지 헤더 --}}
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="fw-bold mb-0" style="font-size:1.2rem;">설정</h1>
            <a href="{{ route('landing.index') }}"
                class="btn btn-light btn-sm d-flex align-items-center gap-1 text-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:14px;height:14px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                홈으로
            </a>
        </div>

        {{-- 로고 설정 카드 --}}
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <span style="display:inline-block;width:4px;height:18px;border-radius:9999px;background:#2563eb;margin-right:8px;"></span>
                    <h2 class="fw-semibold mb-0" style="font-size:0.95rem;">로고</h2>
                </div>

                <form id="landing-settings-logo-form">
                    <div class="mb-3">
                        <label class="form-label text-secondary" style="font-size:0.83rem;">텍스트 로고</label>
                        <input type="text" name="logo_text" id="settings-logo-text"
                            maxlength="100" placeholder="홈"
                            class="form-control form-control-sm"
                            value="{{ old('logo_text') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary" style="font-size:0.83rem;">이미지 로고 URL</label>
                        <input type="text" name="logo_image" id="settings-logo-image-url"
                            placeholder="https://..."
                            class="form-control form-control-sm mb-2">
                        <input type="file" name="logo_image_file" id="settings-logo-image-file"
                            accept="image/*"
                            class="form-control form-control-sm">
                        <div class="form-text">이미지가 있으면 텍스트 대신 표시됩니다.</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        로고 저장
                    </button>
                </form>
            </div>
        </div>

        {{-- 기능 목록 카드 --}}
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center">
                        <span style="display:inline-block;width:4px;height:18px;border-radius:9999px;background:#2563eb;margin-right:8px;"></span>
                        <h2 class="fw-semibold mb-0" style="font-size:0.95rem;">바로가기 기능 목록</h2>
                    </div>
                    <button type="button" id="landing-add-feature-btn"
                        class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        추가
                    </button>
                </div>

                <div id="landing-features-list" class="d-flex flex-column gap-3">
                    @foreach($features as $f)
                    <div class="border rounded-3 p-3 bg-light" data-feature-id="{{ $f->id }}">
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-secondary mb-1" style="font-size:0.75rem;">제목</label>
                                <input type="text" name="title" placeholder="제목" value="{{ $f->title }}" required
                                    class="form-control form-control-sm">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-secondary mb-1" style="font-size:0.75rem;">URL</label>
                                <input type="text" name="url" placeholder="/build/step1" value="{{ $f->url }}" required
                                    class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary mb-1" style="font-size:0.75rem;">설명</label>
                            <input type="text" name="description" placeholder="설명 (선택)" value="{{ $f->description ?? '' }}"
                                class="form-control form-control-sm">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="feature-save-btn btn btn-primary btn-sm px-3">저장</button>
                            <button type="button" class="feature-delete-btn btn btn-sm px-3"
                                style="background:#fee2e2;color:#b91c1c;border:none;">삭제</button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const API = '/api';
    const logoForm = document.getElementById('landing-settings-logo-form');
    const featuresList = document.getElementById('landing-features-list');
    const addFeatureBtn = document.getElementById('landing-add-feature-btn');

    function getBox(el) { return el.closest('[data-feature-id]'); }
    function getId(box) { return parseInt(box.dataset.featureId, 10); }

    featuresList?.addEventListener('click', function(e) {
        if (e.target.classList.contains('feature-save-btn')) {
            const box = getBox(e.target);
            if (!box) return;
            const title = box.querySelector('[name=title]').value.trim();
            const url   = box.querySelector('[name=url]').value.trim();
            if (!title || !url) return;
            fetch(API + '/landing/features/' + getId(box), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ title, description: box.querySelector('[name=description]').value.trim(), url })
            }).then(function() { window.location.reload(); }).catch(function(err) { console.error(err); });
        }
        if (e.target.classList.contains('feature-delete-btn')) {
            if (!confirm('삭제할까요?')) return;
            const box = getBox(e.target);
            if (!box) return;
            fetch(API + '/landing/features/' + getId(box), {
                method: 'DELETE',
                headers: { 'Accept': 'application/json' }
            }).then(function() { window.location.reload(); }).catch(function(err) { console.error(err); });
        }
    });

    addFeatureBtn?.addEventListener('click', function() {
        const title = prompt('제목을 입력하세요');
        if (!title) return;
        const url = prompt('URL을 입력하세요 (예: /build/step1)', '/build/step1');
        if (!url) return;
        fetch(API + '/landing/features', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ title, description: '', url })
        }).then(function() { window.location.reload(); }).catch(function(err) { console.error(err); });
    });

    logoForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('logo_text', document.getElementById('settings-logo-text').value || '홈');
        formData.append('logo_image', document.getElementById('settings-logo-image-url').value || '');
        const file = document.getElementById('settings-logo-image-file').files[0];
        if (file) formData.append('logo_image_file', file);
        fetch(API + '/landing/settings/logo', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        }).then(function() { window.location.reload(); }).catch(function(err) { console.error(err); });
    });
})();
</script>
@endpush
