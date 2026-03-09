<?php

namespace App\Services;

use App\Services\TranslateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NewsScraperService
{
    private const CACHE_TTL = 1800; // 30분

    /** 허용 도메인 목록 */
    public const ALLOWED_DOMAINS = [
        'www.yna.co.kr',
        'techcrunch.com',
    ];

    private TranslateService $translate;

    public function __construct(TranslateService $translate)
    {
        $this->translate = $translate;
    }

    public function fetch(string $url): ?array
    {
        $cacheKey = 'news_detail_' . md5($url);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($url) {
            return $this->scrape($url);
        });
    }

    private function scrape(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        $response = Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language' => 'ko-KR,ko;q=0.9,en;q=0.8',
            'Accept'          => 'text/html,application/xhtml+xml',
        ])->timeout(15)->get($url);

        if (! $response->successful()) {
            return null;
        }

        $html = $response->body();

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // 도메인 기반 파서 분기
        if (str_contains($host, 'techcrunch.com')) {
            return $this->parseTechCrunch($url, $dom, $xpath, $html);
        }

        return $this->parseYonhap($url, $dom, $xpath);
    }

    // ─── 연합뉴스 파서 ────────────────────────────────────────────────────────

    private function parseYonhap(string $url, \DOMDocument $dom, \DOMXPath $xpath): ?array
    {
        $title = $this->xpathText($xpath, '//h1[contains(@class,"tit01")]')
            ?: $this->xpathText($xpath, '//title');

        $pubDate = '';
        $timeNode = $xpath->query('//*[contains(@class,"update-time")]');
        if ($timeNode && $timeNode->length > 0) {
            $pubDate = trim($timeNode->item(0)->getAttribute('data-published-time') ?? '');
        }

        $author = $this->xpathText($xpath, '//*[contains(@class,"tit-name")]//a') ?: '연합뉴스';

        $tags = [];
        $tagNodes = $xpath->query('//*[contains(@class,"keyword-zone")]//a');
        if ($tagNodes) {
            foreach ($tagNodes as $tag) {
                $tags[] = trim($tag->textContent);
            }
        }

        $articleNode = $xpath->query('//div[contains(@class,"story-news") and contains(@class,"article")]');
        if (! $articleNode || $articleNode->length === 0) {
            return null;
        }

        $article = $articleNode->item(0);

        $this->removeNodes($xpath, $article, [
            './/aside',
            './/*[contains(@class,"writer-zone")]',
            './/*[contains(@class,"keyword-zone")]',
            './/*[contains(@class,"txt-copyright")]',
            './/*[@id="newsWriterCarousel01"]',
            './/yna-ad-script',
            './/script',
            './/style',
            './/*[contains(@class,"label-box03")]',
            './/*[contains(@class,"ico-type03-zoom")]',
        ]);

        $bodyHtml = '';
        foreach ($article->childNodes as $child) {
            $bodyHtml .= $dom->saveHTML($child);
        }

        $imageUrl = null;
        $imgNodes = $xpath->query('.//img', $article);
        if ($imgNodes && $imgNodes->length > 0) {
            $src = $imgNodes->item(0)->getAttribute('src');
            if ($src && str_starts_with($src, 'http')) {
                $imageUrl = $src;
            }
        }

        return [
            'title'    => trim($title),
            'pubDate'  => $pubDate,
            'body'     => $this->cleanBodyHtml($bodyHtml),
            'imageUrl' => $imageUrl,
            'author'   => $author,
            'source'   => '연합뉴스',
            'tags'     => array_filter(array_unique($tags)),
            'url'      => $url,
        ];
    }

    // ─── TechCrunch 파서 ──────────────────────────────────────────────────────

    private function parseTechCrunch(string $url, \DOMDocument $dom, \DOMXPath $xpath, string $rawHtml): ?array
    {
        // 제목: h1.article-hero__title
        $title = $this->xpathText($xpath, '//h1[contains(@class,"article-hero__title")]')
            ?: $this->xpathText($xpath, '//h1[contains(@class,"wp-block-post-title")]')
            ?: $this->xpathText($xpath, '//title');

        // 발행시간: meta[property="article:published_time"]
        $pubDate = '';
        $pubMeta = $xpath->query('//meta[@property="article:published_time"]');
        if ($pubMeta && $pubMeta->length > 0) {
            $pubDate = $pubMeta->item(0)->getAttribute('content');
            // ISO8601 → 읽기 좋은 형식
            try {
                $pubDate = (new \DateTime($pubDate))->format('Y-m-d H:i');
            } catch (\Throwable) {}
        }

        // OG 이미지: meta[property="og:image"]
        $imageUrl = null;
        $ogImg = $xpath->query('//meta[@property="og:image"]');
        if ($ogImg && $ogImg->length > 0) {
            $imageUrl = $ogImg->item(0)->getAttribute('content');
        }

        // 작성자: JSON-LD 또는 author 링크
        $author = 'TechCrunch';
        $authorNode = $xpath->query('//*[contains(@class,"tc23-author-card-name")]//a');
        if ($authorNode && $authorNode->length > 0) {
            $author = trim($authorNode->item(0)->textContent) ?: 'TechCrunch';
        }
        // JSON-LD 폴백
        if ($author === 'TechCrunch') {
            if (preg_match('/"author":\s*\[?\s*\{[^}]*"name"\s*:\s*"([^"]+)"/', $rawHtml, $m)) {
                $candidate = $m[1];
                // 제목이 들어간 경우 제외
                if (mb_strlen($candidate) < 60 && !str_contains($candidate, '|')) {
                    $author = $candidate;
                }
            }
        }

        // 본문: div.entry-content
        $articleNode = $xpath->query('//div[contains(@class,"entry-content")]');
        if (! $articleNode || $articleNode->length === 0) {
            return null;
        }

        $article = $articleNode->item(0);

        // 불필요 요소 제거 (광고, 프로모션, 이벤트 배너, 작성자 카드 등)
        $this->removeNodes($xpath, $article, [
            './/script',
            './/style',
            './/iframe',
            './/*[contains(@class,"tc23-podcast-player")]',
            './/*[contains(@class,"promo-banner")]',
            './/*[contains(@class,"newsletter")]',
            './/*[contains(@class,"ad-unit")]',
            './/*[contains(@class,"related-article")]',
            './/*[contains(@class,"wp-block-tc23-post-related")]',
            './/*[contains(@class,"wp-block-tc23-author")]',
            './/*[contains(@class,"tc-ad")]',
            './/*[@data-module="AdUnit"]',
            // 이벤트/프로모션 CTA 배너
            './/*[contains(@class,"wp-block-techcrunch-event-cta")]',
            './/*[contains(@class,"wp-block-techcrunch-promo")]',
            './/*[contains(@class,"inline-cta")]',
            './/*[contains(@class,"rightrail-promo")]',
            './/*[contains(@class,"promo-banner-countdown")]',
            './/*[contains(@class,"wp-block-tc-ads")]',
            // 구독/등록 버튼
            './/*[contains(@class,"register")]',
        ]);

        $bodyHtml = '';
        foreach ($article->childNodes as $child) {
            $bodyHtml .= $dom->saveHTML($child);
        }

        $cleanedBody = $this->cleanBodyHtmlGlobal($bodyHtml);

        // 제목 번역
        $translatedTitle = $this->translate->toKorean(trim($title), 'en');

        // 본문 번역 (HTML 구조 유지)
        $translatedBody = $this->translate->translateHtmlBody($cleanedBody, 'en');

        return [
            'title'      => $translatedTitle,
            'titleOrig'  => trim($title),
            'pubDate'    => $pubDate,
            'body'       => $translatedBody,
            'imageUrl'   => $imageUrl,
            'author'     => $author,
            'source'     => 'TechCrunch',
            'tags'       => [],
            'url'        => $url,
        ];
    }

    // ─── 공통 유틸 ────────────────────────────────────────────────────────────

    private function xpathText(\DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return '';
    }

    private function removeNodes(\DOMXPath $xpath, \DOMNode $context, array $queries): void
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query, $context);
            if (! $nodes) continue;
            $toRemove = iterator_to_array($nodes);
            foreach ($toRemove as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    /** 연합뉴스 본문 클린업 */
    private function cleanBodyHtml(string $html): string
    {
        $html = preg_replace('/ target=["\'][^"\']*["\']/', '', $html);
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html);
        $html = preg_replace('/ (data-stat-code|data-pop-open|onclick|onload)=["\'][^"\']*["\']/', '', $html);
        return trim($html);
    }

    /** 글로벌 뉴스 본문 클린업 */
    private function cleanBodyHtmlGlobal(string $html): string
    {
        // 빈 태그 제거
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html);
        $html = preg_replace('/<div[^>]*>\s*<\/div>/i', '', $html);
        // 이벤트 핸들러 제거
        $html = preg_replace('/ on\w+=["\'][^"\']*["\']/', '', $html);
        // data-* 속성 정리
        $html = preg_replace('/ data-[a-z\-]+=["\'][^"\']*["\']/', '', $html);
        // class 중 wp-block 등 불필요한 클래스 포함 태그 정리 (class는 유지)
        return trim($html);
    }
}
