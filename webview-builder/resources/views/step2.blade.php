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

    <form action="{{ route('build.step2.store') }}" method="POST" class="flex flex-col gap-6">
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

        <div>
            <label for="package_id" class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                패키지 ID
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
@endsection
