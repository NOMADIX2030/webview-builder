<?php

namespace App\Console\Commands;

use App\Services\TodaySummaryService;
use Illuminate\Console\Command;

class TodaySummaryRefreshCommand extends Command
{
    protected $signature = 'today-summary:refresh';
    protected $description = '오늘의 뉴스 AI 요약 캐시를 즉시 갱신합니다.';

    public function handle(TodaySummaryService $service): int
    {
        $this->info('Groq API 호출 중...');

        $result = $service->refresh();

        if ($result === null) {
            $this->error('요약 생성에 실패했습니다. GROQ_API_KEY를 확인하세요.');
            return self::FAILURE;
        }

        $this->info('캐시 갱신 완료.');
        $this->line('요약: ' . ($result['summary'] ?? '(없음)'));
        $this->line('TOP6: ' . count($result['top3'] ?? []) . '건');
        $this->line('키워드: ' . implode(', ', $result['keywords'] ?? []));

        return self::SUCCESS;
    }
}
