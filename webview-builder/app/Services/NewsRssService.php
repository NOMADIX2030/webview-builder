<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\TranslateService;

class NewsRssService
{
    private const CACHE_TTL = 600; // 10분
    private const PER_PAGE  = 12;

    private TranslateService $translate;

    public function __construct(TranslateService $translate)
    {
        $this->translate = $translate;
    }

    /** 발행처별 RSS 피드 정보 */
    private const RSS_FEEDS = [
        'yonhap' => [
            'url'    => 'https://www.yna.co.kr/rss/news.xml',
            'source' => '연합뉴스',
            'lang'   => 'ko',
        ],
        'techcrunch' => [
            'url'    => 'https://techcrunch.com/category/artificial-intelligence/feed/',
            'source' => 'TechCrunch',
            'lang'   => 'en',
        ],
        'venturebeat' => [
            'url'    => 'https://venturebeat.com/category/ai/feed/',
            'source' => 'VentureBeat',
            'lang'   => 'en',
        ],
        'mit' => [
            'url'    => 'https://www.technologyreview.com/feed/',
            'source' => 'MIT Review',
            'lang'   => 'en',
        ],
    ];

    public function getLatestNews(int $page = 1, string $category = 'yonhap', string $query = ''): Collection
    {
        return $this->getPaged($page, $category, $query);
    }

    public function getPaged(int $page, string $category = 'yonhap', string $query = ''): Collection
    {
        $page = max(1, $page);
        return $this->getFiltered($category, $query)
            ->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)
            ->values();
    }

    public function hasMore(int $page, string $category = 'yonhap', string $query = ''): bool
    {
        return $this->getFiltered($category, $query)->count() > $page * self::PER_PAGE;
    }

    public function total(string $category = 'yonhap', string $query = ''): int
    {
        return $this->getFiltered($category, $query)->count();
    }

    private function getFiltered(string $category, string $query): Collection
    {
        $items = $this->getAllCached($category);

        if ($query !== '') {
            $q = mb_strtolower(trim($query));
            $items = $items->filter(function ($item) use ($q) {
                $text = mb_strtolower(($item['title'] ?? '') . ' ' . ($item['description'] ?? ''));
                return mb_strpos($text, $q) !== false;
            })->values();
        }

        return $items;
    }

    private function getAllCached(string $category): Collection
    {
        $feed     = self::RSS_FEEDS[$category] ?? self::RSS_FEEDS['yonhap'];
        $cacheKey = 'landing_news_v2_' . $category;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($feed) {
            return $this->fetchAndParse($feed['url'], $feed['source'], $feed['lang']);
        });
    }

    private function fetchAndParse(string $url, string $sourceName, string $lang): Collection
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; RSSReader/1.0)',
        ])->timeout(15)->get($url);

        if (! $response->successful()) {
            return collect();
        }

        $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false || ! isset($xml->channel->item)) {
            return collect();
        }

        $items   = [];
        $mediaNs = 'http://search.yahoo.com/mrss/';

        foreach ($xml->channel->item as $item) {
            $title       = trim((string) $item->title);
            $link        = trim((string) $item->link);
            $description = trim(strip_tags((string) $item->description));
            $pubDate     = (string) $item->pubDate;
            $creator     = trim((string) ($item->children('dc', true)->creator ?? ''));

            // 이미지 추출: media:content → enclosure 순서로 시도
            $imageUrl = null;

            $media = $item->children($mediaNs);
            if (isset($media->content)) {
                $content = $media->content[0] ?? $media->content;
                $attrs   = $content->attributes();
                if (isset($attrs['url'])) {
                    $imageUrl = (string) $attrs['url'];
                }
            }

            if ($imageUrl === null && isset($item->enclosure)) {
                $encUrl = (string) $item->enclosure->attributes()['url'] ?? '';
                if ($encUrl !== '') {
                    $imageUrl = $encUrl;
                }
            }

            // 영어 뉴스는 제목/설명 한국어 번역
            $displayTitle = $title;
            $displayDesc  = mb_substr($description, 0, 120) . (mb_strlen($description) > 120 ? '…' : '');

            if ($lang === 'en') {
                $displayTitle = $this->translate->toKorean($title, 'en');
                $raw          = mb_substr($description, 0, 200);
                $displayDesc  = $this->translate->toKorean($raw, 'en');
            }

            $items[] = [
                'title'       => $displayTitle,
                'titleOrig'   => $title,
                'link'        => $link,
                'description' => $displayDesc,
                'pubDate'     => $pubDate,
                'timeAgo'     => $this->timeAgo($pubDate),
                'creator'     => $creator,
                'imageUrl'    => $imageUrl,
                'source'      => $sourceName,
                'lang'        => $lang,
                'isExternal'  => $sourceName !== '연합뉴스',
            ];
        }

        return collect($items);
    }

    private function timeAgo(string $pubDate): string
    {
        if (empty($pubDate)) {
            return '';
        }

        try {
            $pub  = \Carbon\Carbon::parse($pubDate);
            $diff = now()->timestamp - $pub->timestamp;

            if ($diff < 0)       return '방금';
            if ($diff < 60)      return $diff . '초 전';
            if ($diff < 3600)    return floor($diff / 60) . '분 전';
            if ($diff < 86400)   return floor($diff / 3600) . '시간 전';
            if ($diff < 604800)  return floor($diff / 86400) . '일 전';

            return $pub->format('m/d');
        } catch (\Throwable) {
            return '';
        }
    }
}
