@extends('layouts.app')

@section('title', '1단계 — 웹뷰 앱 빌드')

@section('content')
<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">1단계 — 최소 정보 수집</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">웹 URL과 앱 아이콘을 입력해 주세요. (MVP: 웹뷰만 지원)</p>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('build.step1.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-6">
        @csrf

        <div>
            <label for="web_url" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">웹 URL</label>
            <input type="url" name="web_url" id="web_url" value="{{ old('web_url') }}"
                placeholder="https://example.com"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                required>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">로드할 웹사이트 주소 (https 권장)</p>
            @error('web_url')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-900/20">
            <p class="text-sm font-medium text-blue-800 dark:text-blue-300">기본 권한 포함</p>
            <p class="mt-1 text-xs text-blue-700 dark:text-blue-400">앱 실행 시 다음 권한이 요청됩니다: 저장소(파일·사진), 알림. 2단계에서 위치, 카메라, 마이크 등 추가 권한을 선택할 수 있습니다.</p>
        </div>

        <input type="hidden" name="app_type" value="webview">

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">앱 아이콘 *</label>
            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">512×512 px 이상 권장 (정사각형 PNG/JPEG)</p>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="flex flex-col items-center gap-2">
                    <div id="icon-preview" class="size-20 overflow-hidden rounded-[22%] bg-white shadow-sm ring-1 ring-gray-200 dark:ring-gray-600"
                        style="box-shadow: 0 1px 2px rgba(0,0,0,0.08)">
                        <div class="flex size-full items-center justify-center text-xs text-gray-400 dark:text-gray-500">미리보기</div>
                    </div>
                    <p class="text-center text-xs text-gray-500 dark:text-gray-400">앱에서 보이는 모습</p>
                </div>
                <div class="flex-1">
                    <input type="file" name="app_icon" id="app_icon" accept="image/png,image/jpeg,image/jpg,image/webp"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-300"
                        required>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PNG/JPG, 512×512 이상 권장</p>
                </div>
            </div>
            @error('app_icon')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="splash" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">스플래시 이미지 (선택)</label>
            <input type="file" name="splash" id="splash" accept="image/png,image/jpeg,image/jpg"
                class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium dark:file:bg-gray-700 dark:file:text-gray-300">
        </div>

        <button type="submit"
            class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
            다음 (2단계)
        </button>
    </form>
</div>

@push('scripts')
<script>
document.getElementById('app_icon').addEventListener('change', function(e) {
    const file = e.target.files?.[0];
    const preview = document.getElementById('icon-preview');
    preview.innerHTML = '';
    if (file) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = '앱 아이콘 미리보기';
        img.className = 'size-full object-cover';
        img.onload = () => URL.revokeObjectURL(img.src);
        preview.appendChild(img);
    } else {
        const div = document.createElement('div');
        div.className = 'flex size-full items-center justify-center text-xs text-gray-400 dark:text-gray-500';
        div.textContent = '미리보기';
        preview.appendChild(div);
    }
});
</script>
@endpush
@endsection
