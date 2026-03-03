@extends('layouts.app')

@section('title', '빌드 상태 — 웹뷰 앱 빌드')

@section('content')
<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-6">
        <h1 class="flex items-center gap-2 text-xl font-semibold text-gray-900 dark:text-white">
            빌드 상태
            @if (in_array($status['status'], ['queued', 'building']))
                <span class="inline-block size-5 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></span>
            @endif
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            @switch($status['status'])
                @case('queued')
                    대기 중입니다. 곧 빌드가 시작됩니다.
                    @break
                @case('building')
                    Android APK 빌드 중입니다. (보통 2~5분 소요)
                    @break
                @case('completed')
                    빌드가 완료되었습니다.
                    @break
                @case('failed')
                    빌드에 실패했습니다.
                    @break
                @default
                    —
            @endswitch
        </p>
    </div>

    @if (in_array($status['status'], ['queued', 'building']))
        <p class="mb-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400" id="elapsed">
            <span class="inline-block size-2 animate-pulse rounded-full bg-blue-500"></span>
            <span id="elapsed-text">시작 중...</span>
            <span class="text-gray-400 dark:text-gray-500" id="poll-count"></span>
        </p>
    @endif

    <div class="mb-4 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
        <div class="h-full rounded-full bg-blue-600 transition-all duration-300" style="width: {{ $status['progress'] }}%"></div>
    </div>

    @if ($status['message'])
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ $status['message'] }}</p>
    @endif

    @if ($status['status'] === 'failed')
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
            <p class="font-medium">빌드 실패</p>
            <p class="mt-1">{{ $status['message'] }}</p>
            <p class="mt-2 text-sm">다시 시도하려면 아래 "새 빌드 시작"을 클릭해 1단계부터 진행하세요.</p>
        </div>
    @endif

    @if ($status['status'] === 'completed' && count($status['artifacts']) > 0)
        <div class="mb-4 space-y-2">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white">다운로드</h4>
            <div class="flex flex-col gap-2">
                @if (!empty($status['artifacts']['apk']))
                    <a href="{{ $status['artifacts']['apk'] }}" download
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        APK 다운로드
                    </a>
                @endif
                @if (!empty($status['artifacts']['ipa']))
                    <a href="{{ $status['artifacts']['ipa'] }}" download
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        iOS 앱 (시뮬레이터, ZIP)
                    </a>
                @endif
                @if (!empty($status['artifacts']['keystore']))
                    <a href="{{ $status['artifacts']['keystore'] }}" download
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Keystore 다운로드
                    </a>
                @endif
            </div>
        </div>
    @endif

    <a href="{{ route('build.step1') }}"
        class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
        새 빌드 시작
    </a>
</div>

@if (in_array($status['status'], ['queued', 'building']))
@push('scripts')
<script>
(function() {
    const buildId = '{{ $status['buildId'] }}';
    const createdAt = '{{ $status['createdAt'] }}';
    let pollCount = 0;

    function updateElapsed() {
        if (!createdAt) return;
        const start = new Date(createdAt).getTime();
        const now = Date.now();
        const sec = Math.floor((now - start) / 1000);
        const el = document.getElementById('elapsed-text');
        if (el) el.textContent = sec > 0 ? '경과 ' + Math.floor(sec/60) + '분 ' + (sec%60) + '초' : '시작 중...';
    }

    function poll() {
        pollCount++;
        const pc = document.getElementById('poll-count');
        if (pc) pc.textContent = '· ' + pollCount + '회 확인';

        fetch('/api/build/' + buildId)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'completed' || data.status === 'failed') {
                    window.location.reload();
                }
            })
            .catch(() => {});
    }

    updateElapsed();
    setInterval(updateElapsed, 1000);
    poll();
    setInterval(poll, 3000);
})();
</script>
@endpush
@endif
@endsection
