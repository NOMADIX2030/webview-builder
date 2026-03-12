@php
    $weather = $weather ?? '';
    $size = $size ?? 32;
    $icon = '☁️'; // 기본: 흐림

    // WMO 기반 한글 매핑 (우선순위: 구체적 → 일반)
    if (str_contains($weather, '맑') && (str_contains($weather, '밤') || str_contains($weather, '야간'))) {
        $icon = '🌙';  // 맑은밤
    } elseif (str_contains($weather, '뇌우')) {
        $icon = '⛈️';  // 뇌우, 뇌우와 소나기, 강한 뇌우
    } elseif (str_contains($weather, '소나기') && str_contains($weather, '눈')) {
        $icon = '❄️';  // 눈 소나기
    } elseif (str_contains($weather, '소나기')) {
        $icon = '🌦️';  // 비 소나기
    } elseif (str_contains($weather, '안개') || str_contains($weather, '서리')) {
        $icon = '🌫️';  // 안개, 서리안개
    } elseif (str_contains($weather, '눈')) {
        $icon = '❄️';  // 눈, 진눈깨비, 강한 눈 등
    } elseif (str_contains($weather, '비') || str_contains($weather, '이슬비')) {
        $icon = '🌧️';  // 비, 이슬비
    } elseif (str_contains($weather, '대체로 맑') || str_contains($weather, '약간 흐림')) {
        $icon = '⛅';  // 구름 조금 (맑음보다 먼저 체크)
    } elseif (str_contains($weather, '맑')) {
        $icon = '☀️';  // 맑음
    } elseif (str_contains($weather, '구름') && str_contains($weather, '많음')) {
        $icon = '☁️';  // 구름많음
    } elseif (str_contains($weather, '흐림')) {
        $icon = '☁️';  // 흐림, 약간 흐림(위에서 처리 안 됐으면)
    } elseif (str_contains($weather, '구름')) {
        $icon = '⛅';  // 구름조금
    }
@endphp
<span class="d-inline-flex align-items-center justify-content-center" style="font-size:{{ $size }}px; line-height:1;" title="{{ $weather }}">{{ $icon }}</span>
