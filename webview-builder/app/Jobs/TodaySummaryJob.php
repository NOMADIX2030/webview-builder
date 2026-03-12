<?php

namespace App\Jobs;

use App\Services\TodaySummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TodaySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TodaySummaryService $service): void
    {
        try {
            $result = $service->refresh();
            if ($result !== null) {
                Log::info('TodaySummaryJob: 캐시 갱신 완료');
            } else {
                Log::warning('TodaySummaryJob: 결과 없음');
            }
        } catch (\Throwable $e) {
            Log::error('TodaySummaryJob failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}
