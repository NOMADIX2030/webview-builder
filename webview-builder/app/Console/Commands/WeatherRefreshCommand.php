<?php

namespace App\Console\Commands;

use App\Services\WeatherExtractService;
use Illuminate\Console\Command;

class WeatherRefreshCommand extends Command
{
    protected $signature = 'weather:refresh';
    protected $description = '날씨 섹션 AI 추출 캐시를 즉시 갱신합니다.';

    public function handle(WeatherExtractService $service): int
    {
        $this->info('날씨 조회 중 (Open-Meteo → Groq 폴백)...');

        $result = $service->refresh();

        if ($result === null) {
            $this->error('날씨 조회에 실패했습니다.');
            return self::FAILURE;
        }

        $source = $result['source'] ?? 'unknown';
        $this->info('캐시 갱신 완료. [소스: ' . $source . ']');
        $cur = $result['current'] ?? [];
        $this->line('현재: ' . ($cur['temp'] ?? '-') . '° / 어제비교 ' . ($cur['yesterday_diff'] ?? '-') . '° ' . ($cur['direction'] ?? '') . ' / ' . ($cur['weather'] ?? '-'));
        $this->line('시간별: ' . count($result['hourly'] ?? []) . '건');

        return self::SUCCESS;
    }
}
