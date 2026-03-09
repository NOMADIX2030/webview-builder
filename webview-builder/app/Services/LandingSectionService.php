<?php

namespace App\Services;

use App\Models\LandingFeature;
use Illuminate\Support\Collection;

class LandingSectionService
{
    public function __construct(
        private LandingSettingsService $settingsService
    ) {}

    public function getVisibleSections(): Collection
    {
        $config = config('landing.sections', []);

        return collect($config)
            ->where('visible', true)
            ->sortBy('order')
            ->values();
    }

    public function getVisibleFeatures(): Collection
    {
        if ($this->hasLandingFeaturesTable()) {
            return LandingFeature::where('visible', true)
                ->orderBy('order')
                ->get()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'title' => $f->title,
                    'description' => $f->description,
                    'url' => $f->url,
                    'icon' => $f->icon ?? 'device-phone-mobile',
                    'order' => $f->order,
                    'visible' => $f->visible,
                ]);
        }

        $config = config('landing.features', []);

        return collect($config)
            ->where('visible', true)
            ->sortBy('order')
            ->values();
    }

    public function getLogoImage(): ?string
    {
        if ($this->hasLandingSettingsTable()) {
            return $this->settingsService->getLogoImage();
        }

        return config('landing.logo_image');
    }

    public function getLogoText(): string
    {
        if ($this->hasLandingSettingsTable()) {
            return $this->settingsService->getLogoText();
        }

        return config('landing.logo_text', '홈') ?: '홈';
    }

    private function hasLandingSettingsTable(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('landing_settings');
    }

    private function hasLandingFeaturesTable(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('landing_features');
    }
}
