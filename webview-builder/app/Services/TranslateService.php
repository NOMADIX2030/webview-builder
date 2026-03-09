<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateService
{
    private const CACHE_TTL = 86400; // 24시간

    private GoogleTranslate $translator;

    public function __construct()
    {
        $this->translator = new GoogleTranslate('ko');
    }

    /**
     * 텍스트를 한국어로 번역 (캐시 적용)
     * 이미 한국어이거나 비어 있으면 원문 그대로 반환
     */
    public function toKorean(string $text, string $sourceLang = 'en'): string
    {
        if (empty(trim($text)) || $sourceLang === 'ko') {
            return $text;
        }

        // 한글이 이미 포함된 경우 번역 생략
        if (preg_match('/[\x{AC00}-\x{D7A3}]/u', $text)) {
            return $text;
        }

        $cacheKey = 'translate_ko_' . md5($text);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($text) {
            try {
                $result = $this->translator->translate($text);
                return $result ?: $text;
            } catch (\Throwable) {
                return $text;
            }
        });
    }

    /**
     * HTML 본문을 단락(p, li) 단위로 나눠 번역 (긴 텍스트 대응)
     * HTML 태그 구조는 유지하고 텍스트만 번역
     */
    public function translateHtmlBody(string $html, string $sourceLang = 'en'): string
    {
        if ($sourceLang === 'ko' || empty(trim($html))) {
            return $html;
        }

        $cacheKey = 'translate_html_ko_' . md5($html);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($html) {
            try {
                libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                $dom->loadHTML('<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
                libxml_clear_errors();

                $xpath = new \DOMXPath($dom);

                // 번역할 텍스트 노드를 포함하는 블록 요소 (p, li, h2, h3, h4, blockquote)
                $blocks = $xpath->query('//*[@id="__root__"]//*[self::p or self::li or self::h2 or self::h3 or self::h4 or self::blockquote]');

                if ($blocks) {
                    foreach ($blocks as $block) {
                        $rawText = $block->textContent;
                        if (empty(trim($rawText))) continue;

                        // 이미 한글인 경우 skip
                        if (preg_match('/[\x{AC00}-\x{D7A3}]/u', $rawText)) continue;

                        $translated = $this->translateChunked($rawText);

                        if ($translated && $translated !== $rawText) {
                            // 텍스트 노드만 교체 (링크 등 하위 태그 유지 불가 → 단순 텍스트 교체)
                            $this->replaceTextInNode($block, $rawText, $translated);
                        }
                    }
                }

                $root = $xpath->query('//*[@id="__root__"]')->item(0);
                $result = '';
                foreach ($root->childNodes as $child) {
                    $result .= $dom->saveHTML($child);
                }
                return $result ?: $html;

            } catch (\Throwable) {
                return $html;
            }
        });
    }

    /**
     * 긴 텍스트를 4000자 단위로 청크 분할하여 번역
     */
    private function translateChunked(string $text): string
    {
        $maxLen = 4000;
        if (mb_strlen($text) <= $maxLen) {
            try {
                return $this->translator->translate($text) ?: $text;
            } catch (\Throwable) {
                return $text;
            }
        }

        // 문장 단위 분할
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $chunks    = [];
        $current   = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current) + mb_strlen($sentence) > $maxLen) {
                $chunks[] = $current;
                $current  = $sentence;
            } else {
                $current .= ($current ? ' ' : '') . $sentence;
            }
        }
        if ($current) {
            $chunks[] = $current;
        }

        $translated = '';
        foreach ($chunks as $chunk) {
            try {
                $translated .= ($this->translator->translate($chunk) ?: $chunk) . ' ';
                usleep(100000); // 100ms 대기 (과부하 방지)
            } catch (\Throwable) {
                $translated .= $chunk . ' ';
            }
        }

        return trim($translated);
    }

    /**
     * DOM 노드의 텍스트 노드를 번역된 텍스트로 교체
     */
    private function replaceTextInNode(\DOMNode $node, string $original, string $translated): void
    {
        // 하위에 자식 태그(링크 등)가 있으면 첫 번째 텍스트 노드만 교체
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && !empty(trim($child->nodeValue))) {
                // 해당 텍스트 노드의 비율만큼 번역 텍스트 할당
                $child->nodeValue = $translated;
                return;
            }
        }
    }
}
