<?php

return [
    'logo_image' => null,
    'logo_text' => '홈',

    'sections' => [
        [
            'id' => 'search-bar',
            'order' => 1,
            'visible' => true,
        ],
        [
            'id' => 'feature-grid',
            'order' => 2,
            'visible' => true,
        ],
        [
            'id' => 'today-summary',
            'order' => 3,
            'visible' => true,
        ],
        [
            'id' => 'ytn-youtube-section',
            'order' => 4,
            'visible' => true,
        ],
        [
            'id' => 'weather-section',
            'order' => 5,
            'visible' => true,
        ],
        [
            'id' => 'news-grid',
            'order' => 6,
            'visible' => true,
        ],
    ],

    'features' => [
        [
            'id' => 'webview-builder',
            'title' => '웹뷰 앱 빌더',
            'description' => '웹사이트를 Android·iOS 앱으로 쉽게 만들어보세요.',
            'url' => '/build/step1',
            'icon' => 'device-phone-mobile',
            'order' => 1,
            'visible' => true,
        ],
        [
            'id' => 'ai-chat',
            'title' => 'AI 채팅',
            'description' => 'ChatGPT 스타일 AI와 대화하세요.',
            'url' => '/chat',
            'icon' => 'chat-bubble-left-right',
            'order' => 2,
            'visible' => true,
        ],
    ],
];
