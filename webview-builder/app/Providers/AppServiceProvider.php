<?php

namespace App\Providers;

use App\Services\LandingSectionService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // layouts.landing 을 상속하는 모든 뷰에 로고 데이터 자동 주입
        View::composer('layouts.landing', function ($view) {
            $svc = app(LandingSectionService::class);
            $view->with([
                'logoImage' => $svc->getLogoImage(),
                'logoText'  => $svc->getLogoText(),
            ]);
        });
    }
}
