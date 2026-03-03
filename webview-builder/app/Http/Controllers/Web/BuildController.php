<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBuildJob;
use App\Models\Build;
use App\Services\BuildConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BuildController extends Controller
{
    public function __construct(
        private BuildConfigService $buildConfigService
    ) {}

    public function step1(): View|RedirectResponse
    {
        return view('step1');
    }

    public function step1Store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'web_url' => ['required', 'url'],
            'app_type' => ['required', 'in:webview'],
            'app_icon' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'splash' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', 'in:android,ios'],
        ]);

        $iconPath = $request->file('app_icon')->store('uploads/' . Str::random(8), 'public');
        $splashPath = $request->hasFile('splash')
            ? $request->file('splash')->store('uploads/' . Str::random(8), 'public')
            : null;

        $platforms = array_values(array_unique(array_intersect($validated['platforms'] ?? [], ['android', 'ios'])));
        if (empty($platforms)) {
            return redirect()->back()->withErrors(['platforms' => '최소 1개 플랫폼을 선택해 주세요.'])->withInput();
        }

        session([
            'build_step1' => [
                'web_url' => $validated['web_url'],
                'app_type' => $validated['app_type'],
                'app_icon_path' => $iconPath,
                'splash_image_path' => $splashPath,
                'platforms' => $platforms,
            ],
        ]);

        return redirect()->route('build.step2');
    }

    public function step2(Request $request): View|RedirectResponse
    {
        $step1 = session('build_step1');
        if (! $step1) {
            return redirect()->route('build.step1')->with('error', '1단계 데이터가 없습니다. 처음부터 시작해 주세요.');
        }

        $generated = $this->buildConfigService->generateStep2FromWebUrl($step1['web_url']);
        $step2 = session('build_step2', [
            'app_name' => $generated['appName'],
            'package_id' => $generated['packageId'],
            'bundle_id' => $generated['bundleId'] ?? $generated['packageId'],
            'privacy_policy_url' => $generated['privacyPolicyUrl'],
            'support_url' => $generated['supportUrl'],
            'version_name' => $generated['versionName'],
            'version_code' => $generated['versionCode'],
            'extra_permissions' => [],
            'fcm_enabled' => false,
            'google_services_path' => null,
            'fcm_click_url_key' => 'action_url',
        ]);

        return view('step2', [
            'step1' => $step1,
            'step2' => $step2,
            'platforms' => $step1['platforms'] ?? ['android'],
        ]);
    }

    public function step2Store(Request $request): RedirectResponse
    {
        $step1 = session('build_step1');
        if (! $step1) {
            return redirect()->route('build.step1');
        }

        $platforms = $step1['platforms'] ?? ['android'];
        $rules = [
            'app_name' => ['required', 'string', 'max:255'],
            'package_id' => [in_array('android', $platforms) ? 'required' : 'nullable', 'string', 'max:255'],
            'privacy_policy_url' => ['required', 'url'],
            'support_url' => ['required', 'url'],
            'version_name' => ['required', 'string', 'max:50'],
            'version_code' => ['required', 'integer', 'min:1'],
            'extra_permissions' => ['nullable', 'array'],
            'extra_permissions.*' => ['string', 'in:CAMERA,RECORD_AUDIO,READ_CONTACTS,WRITE_CONTACTS,CALL_PHONE,READ_CALENDAR,WRITE_CALENDAR,SEND_SMS,RECEIVE_SMS,BLUETOOTH_CONNECT'],
            'fcm_enabled' => ['nullable'],
        ];
        if (in_array('ios', $platforms)) {
            $rules['bundle_id'] = ['required', 'string', 'max:255', 'regex:/^[a-zA-Z][a-zA-Z0-9]*(\.[a-zA-Z][a-zA-Z0-9]*)+$/'];
        }
        $prevStep2 = session('build_step2', []);
        $fcmEnabled = $request->boolean('fcm_enabled');
        $hasNewFile = $request->hasFile('google_services_json');
        $hasPrevPath = ! empty($prevStep2['google_services_path']);
        if ($fcmEnabled && ! $hasNewFile && ! $hasPrevPath) {
            $rules['google_services_json'] = ['required', 'file', 'mimes:json', 'max:1024'];
        } elseif ($fcmEnabled && $hasNewFile) {
            $rules['google_services_json'] = ['required', 'file', 'mimes:json', 'max:1024'];
        }
        $validated = $request->validate($rules);

        $validated['extra_permissions'] = $validated['extra_permissions'] ?? [];
        $validated['fcm_enabled'] = $fcmEnabled;
        $validated['bundle_id'] = in_array('ios', $platforms)
            ? ($validated['bundle_id'] ?? $validated['package_id'] ?? 'com.app.app')
            : ($validated['package_id'] ?? 'com.app.app');
        if (in_array('ios', $platforms) && ! in_array('android', $platforms)) {
            $validated['package_id'] = $validated['bundle_id'];
        } elseif (empty($validated['package_id'] ?? null) && in_array('ios', $platforms)) {
            $validated['package_id'] = $validated['bundle_id'];
        }
        $validated['google_services_path'] = null;
        $validated['fcm_click_url_key'] = $request->filled('fcm_click_url_key')
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $request->input('fcm_click_url_key'))
            : 'action_url';
        if (empty($validated['fcm_click_url_key'])) {
            $validated['fcm_click_url_key'] = 'action_url';
        }
        if ($validated['fcm_enabled']) {
            if ($hasNewFile) {
                $validated['google_services_path'] = $request->file('google_services_json')->store('uploads/' . Str::random(8), 'public');
            } elseif ($hasPrevPath) {
                $validated['google_services_path'] = $prevStep2['google_services_path'];
            }
        }

        unset($validated['google_services_json']);
        session(['build_step2' => $validated]);

        return redirect()->route('build.step3');
    }

    public function step3(Request $request): View|RedirectResponse
    {
        $step1 = session('build_step1');
        $step2 = session('build_step2');
        if (! $step1 || ! $step2) {
            return redirect()->route('build.step1')->with('error', '이전 단계를 먼저 완료해 주세요.');
        }

        return view('step3', [
            'step1' => $step1,
            'step2' => $step2,
        ]);
    }

    public function step3Store(Request $request): RedirectResponse
    {
        $step1 = session('build_step1');
        $step2 = session('build_step2');
        if (! $step1 || ! $step2) {
            return redirect()->route('build.step1');
        }

        set_time_limit(600);

        $build = Build::create([
            'status' => 'queued',
            'app_type' => $step1['app_type'],
            'web_url' => $step1['web_url'],
            'app_name' => $step2['app_name'],
            'package_id' => $step2['package_id'],
            'version_name' => $step2['version_name'],
            'version_code' => (int) $step2['version_code'],
            'privacy_policy_url' => $step2['privacy_policy_url'],
            'support_url' => $step2['support_url'],
            'app_icon_path' => $step1['app_icon_path'],
            'splash_image_path' => $step1['splash_image_path'],
            'config_json' => [
                'platforms' => $step1['platforms'] ?? ['android'],
                'bundle_id' => $step2['bundle_id'] ?? $step2['package_id'],
                'extra_permissions' => $step2['extra_permissions'] ?? [],
                'fcm_enabled' => $step2['fcm_enabled'] ?? false,
                'google_services_path' => $step2['google_services_path'] ?? null,
                'fcm_click_url_key' => $step2['fcm_click_url_key'] ?? 'action_url',
            ],
        ]);

        try {
            \Illuminate\Support\Facades\Bus::dispatchSync(new ProcessBuildJob($build));
        } catch (\Throwable $e) {
            $build->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('빌드 실패', ['build_id' => $build->id, 'error' => $e->getMessage()]);
            return redirect()->route('build.step3')->with('error', $e->getMessage());
        }

        session()->forget(['build_step1', 'build_step2']);

        return redirect()->route('build.show', $build->id);
    }

    public function show(string $id): View
    {
        $build = Build::find($id);
        if (! $build) {
            abort(404, '빌드를 찾을 수 없습니다.');
        }

        $progress = match ($build->status) {
            'queued' => 0,
            'building' => 50,
            'completed' => 100,
            'failed' => 0,
            default => 0,
        };

        $platforms = $build->config_json['platforms'] ?? ['android'];
        $buildLabel = count($platforms) > 1 ? 'Android·iOS' : (in_array('ios', $platforms) ? 'iOS' : 'Android');
        $message = match ($build->status) {
            'queued' => '대기 중...',
            'building' => "{$buildLabel} 빌드 중...",
            'completed' => '빌드가 완료되었습니다.',
            'failed' => $build->error_message ?? '빌드에 실패했습니다.',
            default => '',
        };

        $artifacts = [];
        if ($build->apk_path) {
            $artifacts['apk'] = url("/api/build/{$build->id}/download/apk");
        }
        if ($build->ipa_path) {
            $artifacts['ipa'] = url("/api/build/{$build->id}/download/ipa");
        }
        if ($build->keystore_path) {
            $artifacts['keystore'] = url("/api/build/{$build->id}/download/keystore");
        }

        return view('build.show', [
            'build' => $build,
            'status' => [
                'buildId' => $build->id,
                'status' => $build->status,
                'progress' => $progress,
                'message' => $message,
                'artifacts' => $artifacts,
                'createdAt' => $build->created_at?->toIso8601String(),
                'completedAt' => $build->completed_at?->toIso8601String(),
            ],
        ]);
    }
}
