<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LandingSettingsService
{
    public function get(string $key, ?string $default = null): ?string
    {
        $row = DB::table('landing_settings')->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        DB::table('landing_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }

    public function getLogoImage(): ?string
    {
        $value = $this->get('logo_image');

        return $value !== null && $value !== '' ? $value : null;
    }

    public function getLogoText(): string
    {
        return $this->get('logo_text', '홈') ?: '홈';
    }
}
