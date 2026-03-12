<?php

use App\Jobs\TodaySummaryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 오늘의 뉴스 AI 요약 — 매 30분마다 사전 캐시
Schedule::job(new TodaySummaryJob)->everyThirtyMinutes();

// 날씨 섹션 AI 추출 — 매 30분마다
Schedule::command('weather:refresh')->everyThirtyMinutes();
