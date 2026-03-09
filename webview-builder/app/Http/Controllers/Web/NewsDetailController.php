<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\NewsScraperService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsDetailController extends Controller
{
    public function __construct(private NewsScraperService $scraper) {}

    public function show(Request $request)
    {
        $encoded = $request->query('u', '');

        if (empty($encoded)) {
            abort(404);
        }

        $url = base64_decode($encoded, strict: true);

        // 허용 도메인 검증
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $allowed = array_map(
            fn($d) => $d,
            NewsScraperService::ALLOWED_DOMAINS
        );
        $isAllowed = collect($allowed)->contains(fn($d) => $host === $d || str_ends_with($host, '.' . $d));

        if ($url === false || ! $isAllowed) {
            abort(403, '허용되지 않는 URL입니다.');
        }

        $article = $this->scraper->fetch($url);

        if (! $article) {
            return redirect($url)->with('error', '기사를 불러올 수 없습니다. 원문 페이지로 이동합니다.');
        }

        return view('landing.news-detail', compact('article'));
    }
}
