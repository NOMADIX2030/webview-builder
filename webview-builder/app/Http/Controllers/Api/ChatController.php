<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService
    ) {}

    /**
     * 채팅 메시지 전송 → AI 응답 반환
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
            'history' => 'nullable|array',
            'history.*.role' => 'required|string|in:user,assistant',
            'history.*.content' => 'required|string',
        ]);

        $history = $validated['history'] ?? [];
        $message = $validated['message'];

        $result = $this->chatService->chat($history, $message);

        if ($result === null) {
            return response()->json(['error' => '처리 실패'], 500);
        }

        if (! empty($result['error'])) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json([
            'content' => $result['content'],
        ]);
    }
}
