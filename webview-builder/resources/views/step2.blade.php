@extends('layouts.app')

@section('title', '2단계 — 웹뷰 앱 빌드')

@section('content')
<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">2단계 — 자동 생성 + 수정</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">도메인 기반으로 자동 생성된 값입니다. 필요 시 수정해 주세요.</p>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('build.step2.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-6">
        @csrf

        <div>
            <label for="app_name" class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                앱 이름
                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400">자동 생성됨</span>
            </label>
            <input type="text" name="app_name" id="app_name" value="{{ old('app_name', $step2['app_name'] ?? '') }}"
                placeholder="Myplatform"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                required>
            @error('app_name')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @if(in_array('android', $platforms ?? []))
        <div>
            <label for="package_id" class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                패키지 ID (Android)
                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400">자동 생성됨</span>
            </label>
            <input type="text" name="package_id" id="package_id" value="{{ old('package_id', $step2['package_id'] ?? '') }}"
                placeholder="com.myplatform.app"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono"
                required>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Android 패키지 식별자</p>
            @error('package_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        @endif

        @if(in_array('ios', $platforms ?? []))
        <div>
            <label for="bundle_id" class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                Bundle ID (iOS)
                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400">자동 생성됨</span>
            </label>
            <input type="text" name="bundle_id" id="bundle_id" value="{{ old('bundle_id', $step2['bundle_id'] ?? $step2['package_id'] ?? '') }}"
                placeholder="com.myplatform.app"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono"
                required>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">iOS 앱 식별자 (패키지 ID와 동일하게 사용 가능)</p>
            @error('bundle_id')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        @endif

        <div>
            <label for="privacy_policy_url" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">개인정보처리방침 URL</label>
            <input type="url" name="privacy_policy_url" id="privacy_policy_url" value="{{ old('privacy_policy_url', $step2['privacy_policy_url'] ?? '') }}"
                placeholder="https://example.com/privacy"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                required>
            @error('privacy_policy_url')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="support_url" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">지원/문의 URL</label>
            <input type="url" name="support_url" id="support_url" value="{{ old('support_url', $step2['support_url'] ?? '') }}"
                placeholder="https://example.com/contact"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                required>
            @error('support_url')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @if(in_array('android', $platforms ?? []))
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">FCM 푸시 알림 (Android, 선택)</label>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">앱이 백그라운드일 때도 알림을 받으려면 Firebase 프로젝트의 google-services.json을 등록하세요. 패키지 ID와 Firebase 등록 패키지가 일치해야 합니다.</p>
            <label class="mb-3 flex items-center gap-2">
                <input type="checkbox" name="fcm_enabled" value="1"
                    {{ old('fcm_enabled', $step2['fcm_enabled'] ?? false) ? 'checked' : '' }}
                    id="fcm_enabled"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                <span class="text-sm text-gray-700 dark:text-gray-300">푸시 알림 사용</span>
            </label>
            <div id="fcm-file-wrap" class="{{ old('fcm_enabled', $step2['fcm_enabled'] ?? false) ? '' : 'hidden' }}">
                @if(!empty($step2['google_services_path'] ?? null))
                    <p class="mb-2 text-xs text-green-600 dark:text-green-400">✓ 이미 등록됨. 변경하려면 새 파일을 선택하세요.</p>
                @endif
                <label for="google_services_json" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">google-services.json</label>
                <input type="file" name="google_services_json" id="google_services_json" accept=".json"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-300">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Firebase Console → 프로젝트 설정 → Android 앱 → google-services.json 다운로드</p>
                <div class="mt-3">
                    <label for="fcm_click_url_key" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">푸시 탭 시 URL 키 (선택)</label>
                    <input type="text" name="fcm_click_url_key" id="fcm_click_url_key"
                        value="{{ old('fcm_click_url_key', $step2['fcm_click_url_key'] ?? 'action_url') }}"
                        placeholder="action_url"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white font-mono">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">푸시 data에서 URL을 읽을 키. 서버가 <code>data.action_url</code> 등으로 보내면 해당 키 입력. 기본: action_url</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">추가 권한 (Android, 선택)</label>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">웹에서 사용하는 기능에 따라 필요한 권한을 선택하세요.</p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach([
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
                ] as $perm => $label)
                <label class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-600">
                    <input type="checkbox" name="extra_permissions[]" value="{{ $perm }}"
                        {{ in_array($perm, old('extra_permissions', $step2['extra_permissions'] ?? [])) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                    <span class="text-gray-700 dark:text-gray-300">{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="version_name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">버전 이름</label>
                <input type="text" name="version_name" id="version_name" value="{{ old('version_name', $step2['version_name'] ?? '1.0.0') }}"
                    placeholder="1.0.0"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required>
                @error('version_name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="version_code" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">버전 코드</label>
                <input type="number" name="version_code" id="version_code" value="{{ old('version_code', $step2['version_code'] ?? 1) }}"
                    min="1"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required>
                @error('version_code')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('build.step1') }}"
                class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                이전
            </a>
            <button type="submit"
                class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                다음 (3단계)
            </button>
        </div>
    </form>
</div>

@if(in_array('android', $platforms ?? []))
@push('scripts')
<script>
(function() {
    var fcmCb = document.getElementById('fcm_enabled');
    var fileWrap = document.getElementById('fcm-file-wrap');
    var fileInput = document.getElementById('google_services_json');
    var hasPrevFile = {{ !empty($step2['google_services_path'] ?? null) ? 'true' : 'false' }};
    if (fcmCb && fileWrap) {
        function update() {
            var checked = fcmCb.checked;
            fileWrap.classList.toggle('hidden', !checked);
            if (fileInput) fileInput.required = checked && !hasPrevFile;
        }
        fcmCb.addEventListener('change', update);
        update();
    }
})();
</script>
@endpush
@endif
@endsection
