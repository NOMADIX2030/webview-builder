@extends('layouts.app')

@section('title', '3단계 — 웹뷰 앱 빌드')

@section('content')
<div class="flex w-full max-w-md flex-col gap-6">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">3단계 — 최종 확인</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">요약 정보를 확인한 뒤 빌드를 시작해 주세요.</p>
        </div>

        @php $platforms = $step1['platforms'] ?? ['android']; @endphp
        <div class="mb-6 border-t border-gray-200 pt-6 dark:border-gray-600">
            <h4 class="mb-2 text-sm font-medium text-gray-900 dark:text-white">
                @if(in_array('android', $platforms) && in_array('ios', $platforms))
                    APK·iOS 앱에 적용될 아이콘
                @elseif(in_array('ios', $platforms))
                    iOS 앱에 적용될 아이콘
                @else
                    APK에 적용될 앱 아이콘
                @endif
            </h4>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">앱에서 보이는 모습과 동일합니다. 확인 후 빌드를 시작하세요.</p>
            <div class="flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-700/50">
                <div class="size-20 overflow-hidden rounded-[22%] bg-white shadow-sm ring-1 ring-gray-200 dark:ring-gray-600"
                    style="box-shadow: 0 1px 2px rgba(0,0,0,0.08)">
                    <img src="{{ asset('storage/' . $step1['app_icon_path']) }}" alt="앱 아이콘"
                        class="size-full object-cover"
                        onerror="this.parentElement.innerHTML='<div class=\'flex size-full items-center justify-center text-xs text-gray-400\'>미리보기 없음</div>'">
                </div>
                <div class="flex-1 text-sm">
                    <p class="font-medium text-gray-900 dark:text-white">등록된 아이콘</p>
                    <p class="text-gray-500 dark:text-gray-400">앱 런처에 이 모양으로 표시됩니다.</p>
                </div>
            </div>
        </div>

        <div class="mb-6 border-t border-gray-200 pt-6 dark:border-gray-600">
            <h4 class="mb-3 text-sm font-medium text-gray-900 dark:text-white">요약 정보</h4>
            @if(count($platforms) > 0)
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">빌드 플랫폼: {{ implode(', ', array_map(fn($p) => $p === 'android' ? 'Android APK' : 'iOS IPA', $platforms)) }}</p>
            @endif
            <dl class="grid gap-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">앱 이름</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $step2['app_name'] }}</dd>
                </div>
                @if(in_array('android', $platforms))
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">패키지 ID</dt>
                    <dd class="break-all font-mono text-xs text-gray-900 dark:text-white">{{ $step2['package_id'] }}</dd>
                </div>
                @endif
                @if(in_array('ios', $platforms))
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Bundle ID</dt>
                    <dd class="break-all font-mono text-xs text-gray-900 dark:text-white">{{ $step2['bundle_id'] ?? $step2['package_id'] }}</dd>
                </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">웹 URL</dt>
                    <dd class="break-all text-xs text-gray-900 dark:text-white">{{ $step1['web_url'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">버전</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $step2['version_name'] }} ({{ $step2['version_code'] }})</dd>
                </div>
                @if(in_array('android', $platforms))
                <div class="flex flex-col gap-1 border-t border-gray-200 pt-2 dark:border-gray-600" style="grid-column: 1 / -1;">
                    <dt class="text-gray-500 dark:text-gray-400">권한 (Android)</dt>
                    <dd class="text-gray-900 dark:text-white text-xs">
                        <p>기본: 저장소, 알림</p>
                        @if(!empty($step2['extra_permissions'] ?? []))
                            @php
                                $labels = [
                                    'ACCESS_FINE_LOCATION' => '위치',
                                    'CAMERA' => '카메라',
                                    'RECORD_AUDIO' => '마이크',
                                    'READ_CONTACTS' => '연락처 읽기',
                                    'WRITE_CONTACTS' => '연락처 쓰기',
                                    'CALL_PHONE' => '전화 걸기',
                                    'READ_CALENDAR' => '캘린더 읽기',
                                    'WRITE_CALENDAR' => '캘린더 쓰기',
                                    'SEND_SMS' => 'SMS 발송',
                                    'RECEIVE_SMS' => 'SMS 수신',
                                    'BLUETOOTH_CONNECT' => '블루투스 연결',
                                ];
                            @endphp
                            <p class="mt-1">추가: {{ implode(', ', array_map(fn($p) => $labels[$p] ?? $p, $step2['extra_permissions'])) }}</p>
                        @endif
                        @if(!empty($step2['fcm_enabled'] ?? false))
                            <p class="mt-1">푸시: FCM 사용 (탭 시 URL 키: {{ $step2['fcm_click_url_key'] ?? 'action_url' }})</p>
                        @endif
                    </dd>
                </div>
                @endif
            </dl>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                <p class="font-medium">빌드 실패</p>
                <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap break-words rounded bg-red-100 p-2 text-xs dark:bg-red-900/30">{{ session('error') }}</pre>
                <p class="mt-2 text-sm">위 메시지를 확인한 뒤 환경 설정을 점검하거나 다시 시도해 주세요.</p>
            </div>
        @endif

        <form id="build-form" action="{{ route('build.step3.store') }}" method="POST">
            @csrf
            <button type="submit" id="build-btn"
                class="mb-3 w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-70 dark:focus:ring-offset-gray-800">
                빌드 시작
            </button>
        </form>

        <a href="{{ route('build.step2') }}"
            class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
            이전
        </a>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('build-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('build-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '빌드 중... (2~5분 소요, 잠시만 기다려 주세요)';
    }
});
</script>
@endpush
@endsection
