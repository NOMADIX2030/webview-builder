<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingFeature;
use App\Services\LandingSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class LandingSettingsController extends Controller
{
    public function __construct(
        private LandingSettingsService $settingsService
    ) {}

    public function show(): JsonResponse
    {
        $logoImage = null;
        $logoText = '홈';

        if (Schema::hasTable('landing_settings')) {
            $logoImage = $this->settingsService->getLogoImage();
            $logoText = $this->settingsService->getLogoText();
        }

        $features = [];
        if (Schema::hasTable('landing_features')) {
            $features = LandingFeature::orderBy('order')->get();
        }

        return response()->json([
            'logo_image' => $logoImage,
            'logo_text' => $logoText,
            'features' => $features,
        ]);
    }

    public function updateLogo(Request $request): JsonResponse
    {
        if (! Schema::hasTable('landing_settings')) {
            return response()->json(['error' => '테이블이 없습니다.'], 500);
        }

        $request->validate([
            'logo_image' => ['nullable'],
            'logo_image_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'logo_text' => ['nullable', 'string', 'max:100'],
        ]);

        if ($request->hasFile('logo_image_file')) {
            /** @var UploadedFile $file */
            $file = $request->file('logo_image_file');
            $dir = 'uploads/' . Str::random(8);
            $filename = 'logo-' . Str::random(4) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($dir, $filename, 'public');
            $this->settingsService->set('logo_image', '/storage/' . $path);
        } elseif ($request->has('logo_image')) {
            $value = $request->input('logo_image');
            $this->settingsService->set('logo_image', is_string($value) && $value !== '' ? $value : null);
        }

        if ($request->has('logo_text')) {
            $this->settingsService->set('logo_text', $request->input('logo_text') ?: '홈');
        }

        return response()->json(['success' => true]);
    }

    public function storeFeature(Request $request): JsonResponse
    {
        if (! Schema::hasTable('landing_features')) {
            return response()->json(['error' => '테이블이 없습니다.'], 500);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'url' => ['required', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $maxOrder = LandingFeature::max('order') ?? 0;

        $feature = LandingFeature::create([
            ...$validated,
            'order' => $maxOrder + 1,
            'visible' => true,
        ]);

        return response()->json($feature, 201);
    }

    public function updateFeature(Request $request, int $id): JsonResponse
    {
        $feature = LandingFeature::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'url' => ['sometimes', 'required', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:100'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'visible' => ['sometimes', 'boolean'],
        ]);

        $feature->update($validated);

        return response()->json($feature);
    }

    public function destroyFeature(int $id): JsonResponse
    {
        $feature = LandingFeature::findOrFail($id);
        $feature->delete();

        return response()->json(['success' => true]);
    }
}
