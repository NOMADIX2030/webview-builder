{{-- 날씨 섹션 본문 (API에서 HTML로 반환용) --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
        <span style="display:inline-block;width:4px;height:20px;border-radius:9999px;background:#0ea5e9;margin-right:8px;"></span>
        <span class="fw-bold" style="font-size:0.9rem;">오늘의 날씨</span>
    </div>
    @if(!empty($weatherData))
        <span class="text-secondary" style="font-size:0.7rem;">{{ $weatherData['location'] ?? '현재 위치' }} · {{ $weatherData['updated_at'] ?? '' }}</span>
    @endif
</div>

@if(!empty($weatherData))
        <div class="weather-current-block rounded-3 p-4 mb-4 d-flex align-items-center justify-content-between"
        style="background:#fff; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div class="weather-icon-wrap" style="width:64px;height:64px;" title="{{ $weatherData['current']['weather'] ?? '맑음' }}">
            @include('landing.sections.weather-icon', ['weather' => $weatherData['current']['weather'] ?? '맑음', 'size' => 64])
        </div>
        <div class="text-end">
            <div class="fw-bold mb-1" style="font-size:2rem; color:#0f172a; letter-spacing:-0.02em;">
                @if(isset($weatherData['current']['temp']))
                    {{ $weatherData['current']['temp'] }}°
                @else
                    -
                @endif
            </div>
            <div style="font-size:0.82rem; color:#64748b;">
                @if(isset($weatherData['current']['yesterday_diff']))
                    어제보다 {{ number_format($weatherData['current']['yesterday_diff'], 1) }}°
                    @if(($weatherData['current']['direction'] ?? 'up') === 'up')
                        ↑
                    @else
                        ↓
                    @endif
                @endif
                @if(isset($weatherData['current']['yesterday_diff']) && !empty($weatherData['current']['weather']))
                    /
                @endif
                {{ $weatherData['current']['weather'] ?? '' }}
            </div>
        </div>
    </div>

    @if(!empty($weatherData['hourly']))
        {{-- 6시 ~ 0시(자정) 19개 --}}
        <div class="weather-timeline-wrap overflow-auto" style="max-width:100%; -webkit-overflow-scrolling:touch;">
            <div class="weather-timeline-inner position-relative" style="min-width:max-content; padding:0.5rem 1rem;">
                <div class="weather-timeline-line" style="position:absolute;left:calc(1rem + 21px);right:calc(1rem + 21px);top:34px;height:2px;background:#e2e8f0;border-radius:1px;"></div>
                <div class="d-flex justify-content-between" style="gap:0.35rem;">
                    @foreach($weatherData['hourly'] as $h)
                        <div class="text-center position-relative" style="flex:0 0 42px;">
                            <div class="fw-semibold mb-2" style="font-size:0.8rem;color:#0f172a;">{{ $h['temp'] !== null ? $h['temp'] . '°' : '-' }}</div>
                            <div class="rounded-circle mx-auto mb-2" style="width:8px;height:8px;background:#94a3b8;position:relative;z-index:1;"></div>
                            <div class="d-flex justify-content-center mb-1" style="font-size:1.25rem;line-height:1;">@include('landing.sections.weather-icon', ['weather' => $h['weather'] ?? '', 'size' => 20])</div>
                            <div style="font-size:0.65rem;color:#64748b;">{{ $h['hour'] === 0 ? '자정' : $h['hour'] . '시' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if(!empty($weatherData['briefing']))
        <div class="weather-briefing mt-3 rounded-3 p-3" style="background:#f1f5f9; border:1px solid #e2e8f0;">
            <div class="d-flex align-items-start">
                <span class="me-2" style="font-size:1rem;">💬</span>
                <p class="mb-0" style="font-size:0.82rem; line-height:1.5; color:#334155;">{{ $weatherData['briefing'] }}</p>
            </div>
        </div>
    @endif
@else
    <div class="rounded-3 p-4 text-center" style="background:#f8fafc; border:1px solid #e2e8f0;">
        <span style="font-size:0.85rem; color:#94a3b8;">날씨 정보를 불러오는 중…</span>
    </div>
@endif
