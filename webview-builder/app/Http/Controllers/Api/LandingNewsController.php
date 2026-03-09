<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NewsRssService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingNewsController extends Controller
{
    public function __construct(
        private NewsRssService $newsService
    ) {}

    public function counts(): JsonResponse
    {
        $categories = ['yonhap', 'techcrunch', 'venturebeat', 'mit'];
        $result = [];

        foreach ($categories as $cat) {
            $result[$cat] = $this->newsService->total($cat);
        }

        return response()->json($result);
    }

    public function index(Request $request): JsonResponse
    {
        $page     = max(1, (int) $request->query('page', 1));
        $category = $request->query('category', 'all');
        $query    = trim((string) $request->query('q', ''));

        // 허용된 카테고리만 통과
        $allowed = ['yonhap', 'techcrunch', 'venturebeat', 'mit'];
        if (! in_array($category, $allowed, true)) {
            $category = 'yonhap';
        }

        $news    = $this->newsService->getPaged($page, $category, $query);
        $hasMore = $this->newsService->hasMore($page, $category, $query);

        $html = view('landing.sections.news-items', ['news' => $news])->render();

        return response()->json([
            'html'     => $html,
            'has_more' => $hasMore,
            'page'     => $page,
            'total'    => $this->newsService->total($category, $query),
        ]);
    }
}
