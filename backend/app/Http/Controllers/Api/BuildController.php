<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBuildJob;
use App\Models\Build;
use App\Services\BuildConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuildController extends Controller
{
    public function __construct(
        private BuildConfigService $buildConfigService
    ) {}

    /**
     * POST /api/build/generate-step2
     */
    public function generateStep2(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'webUrl' => ['required', 'string', 'url'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $data = $this->buildConfigService->generateStep2FromWebUrl($request->input('webUrl'));

        return response()->json($data);
    }

    /**
     * POST /api/build
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'step1' => ['required', 'array'],
            'step1.webUrl' => ['required', 'string', 'url'],
            'step1.appType' => ['required', 'string', 'in:webview,hybrid'],
            'step1.appIconPath' => ['required', 'string'],
            'step1.splashImagePath' => ['nullable', 'string'],
            'step2' => ['required', 'array'],
            'step2.appName' => ['required', 'string', 'max:255'],
            'step2.packageId' => ['required', 'string', 'max:255'],
            'step2.privacyPolicyUrl' => ['required', 'string', 'url'],
            'step2.supportUrl' => ['required', 'string', 'url'],
            'step2.versionName' => ['required', 'string', 'max:50'],
            'step2.versionCode' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $step1 = $request->input('step1');
        $step2 = $request->input('step2');

        set_time_limit(600);

        $build = Build::create([
            'status' => 'queued',
            'app_type' => $step1['appType'],
            'web_url' => $step1['webUrl'],
            'app_name' => $step2['appName'],
            'package_id' => $step2['packageId'],
            'version_name' => $step2['versionName'],
            'version_code' => (int) $step2['versionCode'],
            'privacy_policy_url' => $step2['privacyPolicyUrl'],
            'support_url' => $step2['supportUrl'],
            'app_icon_path' => $step1['appIconPath'],
            'splash_image_path' => $step1['splashImagePath'] ?? null,
            'config_json' => $request->input('step1.pushConfig') ?? null,
        ]);

        try {
            \Illuminate\Support\Facades\Bus::dispatchSync(new ProcessBuildJob($build));
        } catch (\Throwable $e) {
            $build->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('빌드 실패', [
                'build_id' => $build->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'buildId' => $build->id,
            'status' => $build->status,
            'message' => '빌드가 완료되었습니다.',
        ], 201);
    }

    /**
     * GET /api/build/{buildId}
     */
    public function show(string $buildId): JsonResponse
    {
        $build = Build::find($buildId);

        if (! $build) {
            return response()->json(['error' => '빌드를 찾을 수 없습니다.'], 404);
        }

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

        $progress = match ($build->status) {
            'queued' => 0,
            'building' => 50,
            'completed' => 100,
            'failed' => 0,
            default => 0,
        };

        $message = match ($build->status) {
            'queued' => '대기 중...',
            'building' => 'Android 빌드 중...',
            'completed' => '빌드가 완료되었습니다.',
            'failed' => $build->error_message ?? '빌드에 실패했습니다.',
            default => '',
        };

        return response()->json([
            'buildId' => $build->id,
            'status' => $build->status,
            'progress' => $progress,
            'message' => $message,
            'artifacts' => $artifacts,
            'createdAt' => $build->created_at?->toIso8601String(),
            'completedAt' => $build->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/build/{buildId}/download/{type}
     */
    public function download(string $buildId, string $type): StreamedResponse|JsonResponse
    {
        $build = Build::find($buildId);

        if (! $build) {
            return response()->json(['error' => '빌드를 찾을 수 없습니다.'], 404);
        }

        $path = match ($type) {
            'apk' => $build->apk_path,
            'ipa' => $build->ipa_path,
            'keystore' => $build->keystore_path,
            default => null,
        };

        if (! $path) {
            return response()->json(['error' => '다운로드할 파일이 없습니다.'], 404);
        }

        $fullPath = storage_path('app/' . $path);

        if (! file_exists($fullPath)) {
            return response()->json(['error' => '파일을 찾을 수 없습니다.'], 404);
        }

        $filename = basename($path);

        return response()->streamDownload(function () use ($fullPath) {
            echo file_get_contents($fullPath);
        }, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
