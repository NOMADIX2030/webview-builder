<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeatherExtractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LandingWeatherController extends Controller
{
    public function __construct(
        private WeatherExtractService $weatherService
    ) {}

    /**
     * 위치 기반 날씨 조회 (lat, lon 쿼리)
     * Accept: text/html → HTML partial (날씨 섹션 교체용)
     */
    public function show(Request $request): Response|JsonResponse
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');

        if ($lat === null || $lon === null) {
            return response()->json(['error' => 'lat, lon are required'], 400);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        try {
            $data = $this->weatherService->get($lat, $lon);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Invalid coordinates'], 400);
        }

        if ($data === null) {
            return response()->json(['error' => '날씨를 불러올 수 없습니다.'], 503);
        }

        $wantsHtml = str_contains($request->header('Accept', ''), 'text/html');

        if ($wantsHtml) {
            $html = view('landing.sections.weather-section-body', [
                'weatherData' => $data,
            ])->render();

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return response()->json(['weatherData' => $data]);
    }
}
