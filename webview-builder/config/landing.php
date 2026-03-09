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
            'id' => 'news-grid',
            'order' => 3,
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
    ],
];
