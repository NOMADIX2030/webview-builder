<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YtnYoutubeService
{
    private const CACHE_TTL = 900; // 15분
    private const CACHE_KEY = 'ytn_youtube_videos_';
    private const RSS_URL = 'https://www.youtube.com/feeds/videos.xml';
    private const CHANNEL_ID = 'UChlgI3UHCOnwUGzWzbJ3H5w'; // YTN @ytnnews24
    private const MAX_VIDEOS = 20;

    /**
     * YTN 유튜브 채널 최신 영상 목록 (캐시 사용)
     */
    public function getLatestVideos(): Collection
    {
        $cacheKey = self::CACHE_KEY . now()->format('Y-m-d-H') . '_' . (int) (now()->format('i') / 15);
        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->fetchFromRss());
    }

    private function fetchFromRss(): Collection
    {
        $response = Http::timeout(10)->get(self::RSS_URL, [
            'channel_id' => self::CHANNEL_ID,
        ]);

        if (! $response->successful()) {
            Log::warning('YtnYoutubeService RSS fetch failed', ['status' => $response->status()]);
            return collect();
        }

        return $this->parseRss($response->body());
    }

    private function parseRss(string $xml): Collection
    {
        $videos = collect();
        if (! preg_match_all('/<entry[^>]*>(.*?)<\/entry>/s', $xml, $entries)) {
            return $videos;
        }

        foreach ($entries[1] as $entryXml) {
            $videoId = $this->extractVideoIdFromXml($entryXml);
            if (! $videoId) {
                continue;
            }

            $title = $this->extractTagContent($entryXml, 'title');
            $description = $this->extractMediaDescription($entryXml);
            $link = $this->extractLinkHref($entryXml) ?: "https://www.youtube.com/watch?v={$videoId}";
            $published = $this->extractTagContent($entryXml, 'published');

            $videos->push([
                'id' => $videoId,
                'title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'description' => $description,
                'thumbnail' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
                'link' => $link,
                'published' => $published,
            ]);

            if ($videos->count() >= self::MAX_VIDEOS) {
                break;
            }
        }

        return $videos->sortByDesc(fn ($v) => \Carbon\Carbon::parse($v['published'])->timestamp)->values();
    }

    private function extractVideoIdFromXml(string $xml): ?string
    {
        if (preg_match('/<yt:videoId>([a-zA-Z0-9_-]{11})<\/yt:videoId>/', $xml, $m)) {
            return $m[1];
        }
        if (preg_match('/yt:video:([a-zA-Z0-9_-]{11})/', $xml, $m)) {
            return $m[1];
        }
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/', $xml, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractTagContent(string $xml, string $tag): string
    {
        $tagQ = preg_quote($tag, '/');
        if (preg_match('/<' . $tagQ . '(?:\s[^>]*)?>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/' . $tagQ . '>/s', $xml, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '';
    }

    private function extractLinkHref(string $xml): ?string
    {
        if (preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]*\/?>/', $xml, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractMediaDescription(string $xml): string
    {
        if (preg_match('/<media:description(?:\s[^>]*)?>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/media:description>/s', $xml, $m)) {
            $desc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return preg_replace('/\s+/', ' ', strip_tags($desc));
        }
        return '';
    }
}
