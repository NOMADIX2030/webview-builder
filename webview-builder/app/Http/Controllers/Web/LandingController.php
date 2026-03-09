<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\LandingSectionService;
use App\Services\NewsRssService;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __construct(
        private LandingSectionService $sectionService,
        private NewsRssService $newsRssService
    ) {}

    public function index(): View
    {
        return view('landing.index', [
            'sections' => $this->sectionService->getVisibleSections(),
            'features' => $this->sectionService->getVisibleFeatures(),
            'news' => $this->newsRssService->getLatestNews(1, 'yonhap'),
            'logoImage' => $this->sectionService->getLogoImage(),
            'logoText' => $this->sectionService->getLogoText(),
        ]);
    }

    public function settings(): View
    {
        $logoImage = $this->sectionService->getLogoImage();
        $logoText = $this->sectionService->getLogoText();
        $features = \App\Models\LandingFeature::orderBy('order')->get();

        return view('landing.settings', [
            'logoImage' => $logoImage,
            'logoText' => $logoText,
            'features' => $features,
        ]);
    }
}
