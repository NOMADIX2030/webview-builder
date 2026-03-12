<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TodaySummaryService;
use Illuminate\Http\JsonResponse;

class LandingTodaySummaryController extends Controller
{
    public function __construct(
        private TodaySummaryService $todaySummaryService
    ) {}

    public function show(): JsonResponse
    {
        $data = $this->todaySummaryService->get();

        if ($data === null) {
            return response()->json(['error' => '요약을 불러올 수 없습니다.'], 503);
        }

        return response()->json($data);
    }
}
