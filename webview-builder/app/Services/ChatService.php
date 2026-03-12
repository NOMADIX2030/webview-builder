<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatService
{
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

    private const SYSTEM_PROMPT = '당신은 친절하고 유용한 AI 어시스턴트입니다. 한국어로 자연스럽게 대화합니다. 필요 시 코드·표·목록을 마크다운으로 작성할 수 있습니다.';

    /**
     * 대화 히스토리와 새 사용자 메시지를 받아 AI 응답 반환
     *
     * @param  array<int, array{role: string, content: string}>  $history  [{role, content}, ...]
     * @return array{content: string, error?: string}|null
     */
    public function chat(array $history, string $userMessage): ?array
    {
        $apiKey = config('services.groq.api_key');
        if (empty($apiKey)) {
            return ['content' => '', 'error' => 'GROQ_API_KEY가 설정되지 않았습니다.'];
        }

        $messages = $this->buildMessages($history, $userMessage);
        if ($messages === null) {
            return ['content' => '', 'error' => '메시지 구성 실패'];
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(self::GROQ_URL, [
                'model' => config('services.groq.model', 'llama-3.1-70b-versatile'),
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 2048,
            ]);

        if (! $response->successful()) {
            $body = $response->json();
            $errMsg = $body['error']['message'] ?? 'API 요청 실패';
            Log::warning('ChatService Groq failed', ['status' => $response->status(), 'error' => $errMsg]);
            return ['content' => '', 'error' => $errMsg];
        }

        $content = $response->json('choices.0.message.content');
        return [
            'content' => is_string($content) ? trim($content) : '',
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>|null
     */
    private function buildMessages(array $history, string $userMessage): ?array
    {
        $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

        $maxHistory = 20;
        $recent = array_slice($history, -$maxHistory);
        foreach ($recent as $m) {
            $role = $m['role'] ?? '';
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '' || ! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => trim($userMessage)];

        return $messages;
    }
}
