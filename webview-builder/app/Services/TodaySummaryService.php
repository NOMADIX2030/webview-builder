<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TodaySummaryService
{
    private const CACHE_TTL = 3600; // 1시간
    private const CACHE_KEY_PREFIX = 'today_summary_';
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const NEWS_LIMIT = 25;

    public function __construct(
        private NewsRssService $newsRssService
    ) {}

    /**
     * 오늘의 뉴스 요약 반환 (summary, top3, keywords).
     * 캐시 우선, 미스 시 Groq 병렬 호출 후 캐시.
     */
    public function get(): ?array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . date('Y-m-d');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return $this->fetchFromGroq();
        });
    }

    /**
     * 캐시 무시하고 새로 생성 (Job 등에서 사용).
     */
    public function refresh(): ?array
    {
        $result = $this->fetchFromGroq();
        if ($result !== null) {
            Cache::put(self::CACHE_KEY_PREFIX . date('Y-m-d'), $result, self::CACHE_TTL);
        }
        return $result;
    }

    private function fetchFromGroq(): ?array
    {
        $apiKey = config('services.groq.api_key');
        if (empty($apiKey)) {
            Log::warning('TodaySummary: GROQ_API_KEY not set');
            return null;
        }

        $news = $this->newsRssService->getAggregatedForToday(self::NEWS_LIMIT);
        if ($news->isEmpty()) {
            return null;
        }

        $context = $this->buildContext($news);

        $responses = Http::pool(fn ($pool) => [
            $pool->as('summary')->withToken($apiKey)
                ->timeout(12)
                ->post(self::GROQ_URL, $this->summaryPayload($context)),
            $pool->as('top3')->withToken($apiKey)
                ->timeout(12)
                ->post(self::GROQ_URL, $this->top3Payload($context)),
            $pool->as('keywords')->withToken($apiKey)
                ->timeout(12)
                ->post(self::GROQ_URL, $this->keywordsPayload($context)),
        ]);

        $summary = $this->parseSummary($responses['summary']);
        $top3 = $this->parseTop3($responses['top3'], $news);
        $keywords = $this->parseKeywords($responses['keywords']);

        return [
            'summary'  => $summary,
            'top3'     => $top3, // 6건 (키는 기존 호환)
            'keywords' => $keywords,
            'updated_at' => now()->format('H:i'),
        ];
    }

    private function buildContext($news): string
    {
        $lines = [];
        foreach ($news as $i => $item) {
            $lines[] = ($i + 1) . '. ' . ($item['title'] ?? '') . ' | ' . ($item['source'] ?? '') . ' | ' . ($item['link'] ?? '');
        }
        return implode("\n", $lines);
    }

    private function summaryPayload(string $context): array
    {
        return [
            'model' => config('services.groq.model', 'llama-3.1-8b-instant'),
            'messages' => [
                ['role' => 'system', 'content' => '당신은 뉴스 요약 전문가입니다. 주어진 뉴스 제목들을 바탕으로 오늘의 핵심 이슈를 한 문장으로 한국어 요약하세요.'],
                ['role' => 'user', 'content' => "다음 오늘의 뉴스 목록을 한 문장으로 요약해주세요:\n\n" . $context],
            ],
            'temperature' => 0.3,
            'max_tokens' => 150,
        ];
    }

    private function top3Payload(string $context): array
    {
        return [
            'model' => config('services.groq.model', 'llama-3.1-8b-instant'),
            'messages' => [
                ['role' => 'system', 'content' => '주어진 뉴스 중 한국 사용자에게 가장 중요한 6건을 골라, 반드시 아래 JSON 형식으로만 답하세요. link는 반드시 목록에 있는 원본 URL 그대로 사용하세요. 추가 설명 없이 JSON만 출력하세요.'],
                ['role' => 'user', 'content' => "다음 뉴스 중 가장 중요한 6건을 골라 JSON으로 답하세요. 반드시 6개 항목을 포함하세요. 형식: {\"top6\":[{\"title\":\"제목\",\"link\":\"URL\",\"source\":\"출처\"}, ...6개]}\n\n" . $context],
            ],
            'temperature' => 0.2,
            'max_tokens' => 1024,
            'response_format' => ['type' => 'json_object'],
        ];
    }

    private function keywordsPayload(string $context): array
    {
        return [
            'model' => config('services.groq.model', 'llama-3.1-8b-instant'),
            'messages' => [
                ['role' => 'system', 'content' => '주어진 뉴스에서 오늘의 핵심 키워드 6개를 뽑아 JSON으로만 답하세요. 한국어로.'],
                ['role' => 'user', 'content' => "다음 뉴스의 핵심 키워드 6개를 JSON 배열로: {\"keywords\":[\"키워드1\",\"키워드2\",...]}\n\n" . $context],
            ],
            'temperature' => 0.2,
            'max_tokens' => 128,
            'response_format' => ['type' => 'json_object'],
        ];
    }

    private function parseSummary($response): ?string
    {
        if (! $response->successful()) {
            Log::warning('TodaySummary summary failed', ['status' => $response->status()]);
            return null;
        }
        $content = $response->json('choices.0.message.content');
        return is_string($content) ? trim($content) : null;
    }

    private function parseTop3($response, $news): array
    {
        if (! $response->successful()) {
            Log::warning('TodaySummary top3 failed', ['status' => $response->status()]);
            return [];
        }
        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            return [];
        }
        $decoded = json_decode($content, true);
        $items = $decoded['top6'] ?? $decoded['top3'] ?? [];
        if (! is_array($items) || count($items) === 0) {
            return $this->fallbackTop6($news);
        }
        $internalSources = ['연합뉴스', 'TechCrunch'];
        return array_slice(array_map(function ($item) use ($internalSources) {
            $link = $item['link'] ?? '#';
            $source = $item['source'] ?? '';
            $internal = in_array($source, $internalSources) && $link !== '#';
            return [
                'title'   => $item['title'] ?? '',
                'link'    => $link,
                'source'  => $source,
                'internal' => $internal,
                'detail_param' => $internal ? base64_encode($link) : null,
            ];
        }, $items), 0, 6);
    }

    private function fallbackTop6($news): array
    {
        $internalSources = ['연합뉴스', 'TechCrunch'];
        return $news->take(6)->map(function ($item) use ($internalSources) {
            $link = $item['link'] ?? '#';
            $source = $item['source'] ?? '';
            $internal = in_array($source, $internalSources) && $link !== '#';
            return [
                'title'   => $item['title'] ?? '',
                'link'    => $link,
                'source'  => $source,
                'internal' => $internal,
                'detail_param' => $internal ? base64_encode($link) : null,
            ];
        })->values()->toArray();
    }

    private function parseKeywords($response): array
    {
        if (! $response->successful()) {
            Log::warning('TodaySummary keywords failed', ['status' => $response->status()]);
            return [];
        }
        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            return [];
        }
        $decoded = json_decode($content, true);
        $kw = $decoded['keywords'] ?? [];
        return is_array($kw) ? array_slice(array_filter($kw, 'is_string'), 0, 8) : [];
    }
}
