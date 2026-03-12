<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherExtractService
{
    private const CACHE_TTL = 1800; // 30분
    private const CACHE_KEY = 'weather_extract_';
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const OPEN_METEO_URL = 'https://api.open-meteo.com/v1/forecast';
    private const TIMEZONE = 'Asia/Seoul';
    private const SEOUL_LAT = 37.5665;
    private const SEOUL_LON = 126.9780;

    /** WMO weathercode → 한글 (Open-Meteo) */
    private const WMO_WEATHER = [
        0 => '맑음',
        1 => '대체로 맑음',
        2 => '약간 흐림',
        3 => '흐림',
        45 => '안개',
        48 => '서리안개',
        51 => '이슬비',
        53 => '이슬비',
        55 => '이슬비',
        56 => '얼어붙는 이슬비',
        57 => '얼어붙는 이슬비',
        61 => '비',
        63 => '비',
        65 => '강한 비',
        66 => '얼어붙는 비',
        67 => '강한 얼어붙는 비',
        71 => '눈',
        73 => '눈',
        75 => '강한 눈',
        77 => '진눈깨비',
        80 => '소나기',
        81 => '소나기',
        82 => '강한 소나기',
        85 => '눈 소나기',
        86 => '강한 눈 소나기',
        95 => '뇌우',
        96 => '뇌우와 소나기',
        99 => '강한 뇌우',
    ];

    private function seoulNow(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * 날씨 조회 (기본: 서울)
     *
     * @param  float|null  $lat  위도 (미설정 시 서울)
     * @param  float|null  $lon  경도
     */
    public function get(?float $lat = null, ?float $lon = null): ?array
    {
        $lat = $lat ?? self::SEOUL_LAT;
        $lon = $lon ?? self::SEOUL_LON;
        $this->validateCoords($lat, $lon);

        $now = $this->seoulNow();
        $coordKey = $this->coordCacheKey($lat, $lon);
        $cacheKey = self::CACHE_KEY . $now->format('Y-m-d-H') . '_' . $coordKey;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lat, $lon) {
            return $this->fetchHybrid($lat, $lon);
        });
    }

    public function refresh(?float $lat = null, ?float $lon = null): ?array
    {
        $lat = $lat ?? self::SEOUL_LAT;
        $lon = $lon ?? self::SEOUL_LON;
        $this->validateCoords($lat, $lon);

        $result = $this->fetchHybrid($lat, $lon);
        if ($result !== null) {
            $now = $this->seoulNow();
            $cacheKey = self::CACHE_KEY . $now->format('Y-m-d-H') . '_' . $this->coordCacheKey($lat, $lon);
            Cache::put($cacheKey, $result, self::CACHE_TTL);
        }
        return $result;
    }

    private function validateCoords(float $lat, float $lon): void
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException('Invalid coordinates');
        }
    }

    /** 좌표 → 캐시 키 (소수 2자리 ≈ 1km 격자) */
    private function coordCacheKey(float $lat, float $lon): string
    {
        return round($lat, 2) . '_' . round($lon, 2);
    }

    /**
     * 하이브리드: 1순위 Open-Meteo → 2순위 Groq AI (서울만)
     */
    private function fetchHybrid(float $lat, float $lon): ?array
    {
        $now = $this->seoulNow();

        // 1순위: Open-Meteo
        $openMeteo = $this->fetchOpenMeteo($lat, $lon);
        if ($openMeteo !== null) {
            return $openMeteo;
        }

        // 2순위: Groq AI (서울 좌표일 때만 - 프롬프트가 서울 기준)
        if (abs($lat - self::SEOUL_LAT) < 0.01 && abs($lon - self::SEOUL_LON) < 0.01) {
            $apiKey = config('services.groq.api_key');
            if (! empty($apiKey)) {
                $groq = $this->fetchFromGroq();
                if ($groq !== null) {
                    return $groq;
                }
            }
        }

        return $this->emptyResult($lat, $lon);
    }

    /**
     * Open-Meteo API
     */
    private function fetchOpenMeteo(float $lat, float $lon): ?array
    {
        $now = $this->seoulNow();

        $response = Http::timeout(10)->get(self::OPEN_METEO_URL, [
            'latitude' => $lat,
            'longitude' => $lon,
            'timezone' => self::TIMEZONE,
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weathercode,cloud_cover,wind_speed_10m,wind_direction_10m,visibility,pressure_msl',
            'hourly' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation_probability,precipitation,weathercode,cloud_cover,wind_speed_10m,uv_index',
            'daily' => 'temperature_2m_max,temperature_2m_min,uv_index_max,precipitation_sum,precipitation_probability_max,weathercode',
        ]);

        if (! $response->successful()) {
            Log::warning('WeatherExtract Open-Meteo failed', ['status' => $response->status()]);
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        $result = $this->normalizeOpenMeteo($data, $now, $lat, $lon);
        if ($result !== null) {
            $result['briefing'] = $this->generateBriefing($data, $now, $result['location'], $lat, $lon);
        }
        return $result;
    }

    /** OpenStreetMap Nominatim 역지오코딩 (한국어 우선) */
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/reverse';

    private function fetchLocationName(float $lat, float $lon): string
    {
        $res = Http::timeout(5)
            ->withHeaders(['User-Agent' => 'WebviewBuilder/1.0 (Landing-Weather)', 'Accept-Language' => 'ko'])
            ->get(self::NOMINATIM_URL, [
                'lat' => $lat,
                'lon' => $lon,
                'format' => 'json',
                'addressdetails' => 1,
            ]);
        if (! $res->successful()) {
            return $this->formatCoordLabel($lat, $lon);
        }
        $data = $res->json();
        if (! is_array($data)) {
            return $this->formatCoordLabel($lat, $lon);
        }
        $addr = $data['address'] ?? [];
        $city = $addr['city'] ?? $addr['town'] ?? $addr['county'] ?? '';
        $borough = $addr['borough'] ?? $addr['district'] ?? $addr['municipality'] ?? '';
        $suburb = $addr['suburb'] ?? $addr['village'] ?? $addr['neighbourhood'] ?? $addr['quarter'] ?? '';
        $state = $addr['state'] ?? $addr['region'] ?? '';

        $cityShort = preg_replace('/특별시|광역시|특별자치시|도$/', '', $city);
        $parts = array_values(array_filter([$borough ?: $suburb, $cityShort ?: $city, $state], fn ($v) => $v !== ''));
        if (! empty($parts)) {
            $short = implode(' ', array_slice(array_unique($parts), 0, 2));
            return mb_substr($short, 0, 24) . (mb_strlen($short) > 24 ? '…' : '');
        }
        $display = $data['display_name'] ?? '';
        if ($display !== '') {
            $parts = array_map('trim', explode(',', $display));
            return mb_substr(implode(', ', array_slice($parts, 0, 2)), 0, 24) . (mb_strlen($display) > 50 ? '…' : '');
        }
        return $this->formatCoordLabel($lat, $lon);
    }

    private function formatCoordLabel(float $lat, float $lon): string
    {
        return sprintf('%.2f°N %.2f°E', $lat, $lon);
    }

    private function normalizeOpenMeteo(array $data, Carbon $now, float $lat, float $lon): array
    {
        $current = $data['current'] ?? [];
        $hourly = $data['hourly'] ?? [];
        $times = $hourly['time'] ?? [];
        $temps = $hourly['temperature_2m'] ?? [];
        $codes = $hourly['weathercode'] ?? [];

        $currentTemp = isset($current['temperature_2m']) ? (int) round((float) $current['temperature_2m']) : null;
        $currentWeather = $this->wmoToKorean((int) ($current['weathercode'] ?? 0));
        $h = (int) $now->format('G');
        if (($h >= 18 || $h < 6) && in_array($currentWeather, ['맑음', '대체로 맑음'], true)) {
            $currentWeather = '맑은밤';
        }

        $hourlyData = $this->alignHourlyFromOpenMeteo($times, $temps, $codes, $now);

        return [
            'current' => [
                'temp' => $currentTemp,
                'yesterday_diff' => null,
                'direction' => 'up',
                'weather' => $currentWeather,
            ],
            'hourly' => $hourlyData,
            'updated_at' => $now->format('H:i'),
            'location' => $this->fetchLocationName($lat, $lon),
            'source' => 'open-meteo',
        ];
    }

    private function alignHourlyFromOpenMeteo(array $times, array $temps, array $codes, Carbon $now): array
    {
        $today = $now->format('Y-m-d');
        $tomorrow = $now->copy()->addDay()->format('Y-m-d');
        $byKey = [];
        foreach ($times as $i => $iso) {
            $dt = Carbon::parse($iso, self::TIMEZONE);
            $d = $dt->format('Y-m-d');
            $h = (int) $dt->format('G');
            $byKey[$d . '-' . $h] = $i;
        }

        $expectedHours = $this->getExpectedHours();
        $result = [];
        foreach ($expectedHours as $h) {
            $date = $h === 0 ? $tomorrow : $today;
            $key = $date . '-' . $h;
            $idx = $byKey[$key] ?? null;
            $weather = '흐림';
            $temp = null;
            if ($idx !== null) {
                $weather = $this->wmoToKorean((int) ($codes[$idx] ?? 0));
                if (($h >= 18 || $h < 6) && in_array($weather, ['맑음', '대체로 맑음'], true)) {
                    $weather = '맑은밤';
                }
                $temp = isset($temps[$idx]) ? (int) round((float) $temps[$idx]) : null;
            }
            $result[] = ['hour' => $h, 'temp' => $temp, 'weather' => $weather];
        }
        return $result;
    }

    /**
     * Open-Meteo 상세 데이터 기반 AI 날씨 브리핑 생성
     */
    private function generateBriefing(array $openMeteoData, Carbon $now, string $location, float $lat, float $lon): string
    {
        $apiKey = config('services.groq.api_key');
        if (empty($apiKey)) {
            return '';
        }

        $context = $this->buildBriefingContext($openMeteoData, $now, $location);
        if ($context === '') {
            return '';
        }

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->post(self::GROQ_URL, [
                'model' => config('services.groq.model', 'llama-3.1-8b-instant'),
                'messages' => [
                    ['role' => 'system', 'content' => self::BRIEFING_SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $context],
                ],
                'temperature' => 0.4,
                'max_tokens' => 600,
            ]);

        if (! $response->successful()) {
            Log::warning('WeatherExtract briefing Groq failed', ['status' => $response->status()]);
            return '';
        }

        $content = $response->json('choices.0.message.content');
        return is_string($content) ? trim($content) : '';
    }

    private const BRIEFING_SYSTEM_PROMPT = <<<'PROMPT'
당신은 전문 기상 해설자입니다. Open-Meteo 날씨 데이터를 기반으로 **한국어**로 짧고 자연스러운 하루 날씨 브리핑을 작성합니다.

## 필수 포함 항목 (2~4문장으로 압축)

1. **옷차림**: 체감기온·바람·습도·UV 지수에 따라 실용적인 추천
   - 예: "체감이 꽤 서늘하니 얇은 겉옷이 좋고, 오후엔 햇볕에 따뜻해지니 가벼운 점퍼 준비."
   - UV 5 이상: "자외선이 강하니 선글라스·선크림 권장"

2. **기상 변동 예측**: 오전/오후/저녁별 날씨 변화
   - 강수 확률·강수량이 있으면 언제쯤 비/눈 올 가능성 있는지
   - 흐림→맑음 또는 맑음→흐림 같은 전환 시점

3. **주의사항**: 필요한 경우만 간단히
   - 바람 강할 때, 미세먼지(데이터 있을 때), 한파·폭염 구분 등

## 규칙
- 반드시 일반 텍스트만 출력 (JSON, 마크다운, 불릿 기호 금지)
- 2~4문장, 150자 내외로 간결하게
- 과장하지 않고 데이터에 근거해 작성
- 사용자에게 직접 말하듯 자연스럽게
PROMPT;

    private function buildBriefingContext(array $data, Carbon $now, string $location): string
    {
        $current = $data['current'] ?? [];
        $hourly = $data['hourly'] ?? [];
        $daily = $data['daily'] ?? [];

        $today = $now->format('Y-m-d');
        $lines = [
            "지역: {$location}",
            "기준 시각: {$now->format('n월 j일 H시')}",
            '',
            '[현재]',
            '기온: ' . ($current['temperature_2m'] ?? '-') . '℃',
            '체감기온: ' . ($current['apparent_temperature'] ?? '-') . '℃',
            '습도: ' . ($current['relative_humidity_2m'] ?? '-') . '%',
            '바람: ' . ($current['wind_speed_10m'] ?? '-') . 'km/h (' . $this->windDirectionLabel((int) ($current['wind_direction_10m'] ?? 0)) . ')',
            '시정: ' . ($current['visibility'] ?? '-') . 'km',
            '기압: ' . ($current['pressure_msl'] ?? '-') . 'hPa',
            '전운량: ' . ($current['cloud_cover'] ?? '-') . '%',
            '강수: ' . ($current['precipitation'] ?? 0) . 'mm',
            '날씨: ' . $this->wmoToKorean((int) ($current['weathercode'] ?? 0)),
        ];

        $dailyTime = $daily['time'] ?? [];
        $dailyIdx = null;
        foreach ($dailyTime as $i => $d) {
            if (str_starts_with($d, $today)) {
                $dailyIdx = $i;
                break;
            }
        }

        if ($dailyIdx !== null) {
            $lines[] = '';
            $lines[] = '[오늘 요약]';
            $lines[] = '최저: ' . ($daily['temperature_2m_min'][$dailyIdx] ?? '-') . '℃ / 최고: ' . ($daily['temperature_2m_max'][$dailyIdx] ?? '-') . '℃';
            $lines[] = 'UV 최대: ' . ($daily['uv_index_max'][$dailyIdx] ?? '-');
            $lines[] = '강수 확률 최대: ' . ($daily['precipitation_probability_max'][$dailyIdx] ?? '-') . '%';
            $lines[] = '강수량: ' . ($daily['precipitation_sum'][$dailyIdx] ?? 0) . 'mm';
        }

        $hTimes = $hourly['time'] ?? [];
        $hTemps = $hourly['temperature_2m'] ?? [];
        $hApparent = $hourly['apparent_temperature'] ?? [];
        $hPrecipProb = $hourly['precipitation_probability'] ?? [];
        $hPrecip = $hourly['precipitation'] ?? [];
        $hCodes = $hourly['weathercode'] ?? [];
        $hUv = $hourly['uv_index'] ?? [];
        $hWind = $hourly['wind_speed_10m'] ?? [];

        $hourlyLines = [];
        foreach ($hTimes as $i => $iso) {
            $dt = Carbon::parse($iso, self::TIMEZONE);
            if ($dt->format('Y-m-d') !== $today) {
                continue;
            }
            $h = (int) $dt->format('G');
            if ($h < 6) {
                continue;
            }
            $temp = $hTemps[$i] ?? null;
            $feels = $hApparent[$i] ?? null;
            $prob = $hPrecipProb[$i] ?? null;
            $precip = $hPrecip[$i] ?? null;
            $code = (int) ($hCodes[$i] ?? 0);
            $uv = $hUv[$i] ?? null;
            $wind = $hWind[$i] ?? null;

            $parts = ["{$h}시: {$temp}℃"];
            if ($feels !== null && $feels != $temp) {
                $parts[] = "체감{$feels}℃";
            }
            $parts[] = $this->wmoToKorean($code);
            if ($prob !== null && $prob > 0) {
                $parts[] = "강수확률{$prob}%";
            }
            if ($precip !== null && (float) $precip > 0) {
                $parts[] = "{$precip}mm";
            }
            if ($uv !== null && (float) $uv >= 5) {
                $parts[] = "UV{$uv}";
            }
            if ($wind !== null && (float) $wind >= 20) {
                $parts[] = "바람{$wind}km/h";
            }
            $hourlyLines[] = implode(', ', $parts);
        }

        if (! empty($hourlyLines)) {
            $lines[] = '';
            $lines[] = '[시간별 예보 (오늘 6시~자정)]';
            $lines[] = implode("\n", $hourlyLines);
        }

        $lines[] = '';
        $lines[] = '위 데이터를 바탕으로 2~4문장의 자연스러운 한국어 날씨 브리핑을 작성해주세요. (옷차림, 기상 변동 예측, 필요한 경우 주의사항)';

        return implode("\n", $lines);
    }

    private function windDirectionLabel(int $deg): string
    {
        if ($deg < 23 || $deg >= 338) return '북';
        if ($deg < 68) return '북동';
        if ($deg < 113) return '동';
        if ($deg < 158) return '남동';
        if ($deg < 203) return '남';
        if ($deg < 248) return '남서';
        if ($deg < 293) return '서';
        if ($deg < 338) return '북서';
        return '북';
    }

    private function wmoToKorean(int $code): string
    {
        if (isset(self::WMO_WEATHER[$code])) {
            return self::WMO_WEATHER[$code];
        }
        if ($code >= 51 && $code <= 67) return '비';
        if ($code >= 71 && $code <= 77) return '눈';
        if ($code >= 80 && $code <= 82) return '소나기';
        if ($code >= 95 && $code <= 99) return '뇌우';
        if ($code <= 3) return ['맑음', '대체로 맑음', '약간 흐림', '흐림'][$code] ?? '흐림';
        return '흐림';
    }

    /** 오전 6시 ~ 새벽 12시(0시) */
    private function getExpectedHours($at = null): array
    {
        $hours = [];
        for ($h = 6; $h <= 23; $h++) {
            $hours[] = $h;
        }
        $hours[] = 0;
        return $hours;
    }

    private function fetchFromGroq(): ?array
    {
        $apiKey = config('services.groq.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $now = $this->seoulNow();
        $date = $now->format('Y년 n월 j일');
        $expectedHours = $this->getExpectedHours();
        $hourRange = '6시~24시(0시)';

        $response = Http::withToken($apiKey)
            ->timeout(20)
            ->post(self::GROQ_URL, [
                'model' => config('services.groq.model', 'llama-3.1-8b-instant'),
                'messages' => [
                    ['role' => 'system', 'content' => '당신은 기상 안내자입니다. 서울 기준으로, 반드시 아래 JSON 형식만 출력하세요.'],
                    ['role' => 'user', 'content' => <<<PROMPT
오늘 {$date} 서울 날씨입니다. 아래 JSON 형식만 출력하세요.

{"current":{"temp":현재기온숫자,"yesterday_diff":어제대비차이(소수한자리,예:0.4),"direction":"up또는down","weather":"맑음또는흐림또는구름많음또는비또는눈"},"hourly":[{"hour":6,"temp":기온,"weather":"날씨"},{"hour":7,...},...,{"hour":23,...},{"hour":0,"temp":기온,"weather":"맑은밤또는흐림"}]}

- current: temp(정수), yesterday_diff(양수), direction(up/down), weather
- hourly: 6시, 7시, ..., 23시, 0시(자정) 총 19개. 각 시간별 temp(정수), weather. 18~05시는 "맑은밤" 또는 "흐림"
PROMPT
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => 512,
            'response_format' => ['type' => 'json_object'],
        ]);

        if (! $response->successful()) {
            Log::warning('WeatherExtract Groq failed', ['status' => $response->status()]);
            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return null;
        }

        $result = $this->normalizeGroqResult($decoded, $now);
        $result['location'] = '서울';
        $result['source'] = 'groq';
        $result['briefing'] = ''; // Groq 폴백은 상세 데이터 없음

        return $result;
    }

    private function normalizeGroqResult(array $decoded, Carbon $now): array
    {
        $current = $decoded['current'] ?? [];
        $hourly = $decoded['hourly'] ?? [];
        $expectedHours = $this->getExpectedHours($now);

        $currentTemp = isset($current['temp']) && is_numeric($current['temp']) ? (int) $current['temp'] : null;
        $yesterdayDiff = isset($current['yesterday_diff']) ? (float) $current['yesterday_diff'] : null;
        $direction = ($current['direction'] ?? '') === 'down' ? 'down' : 'up';
        $currentWeather = trim((string) ($current['weather'] ?? '맑음'));

        $byHour = [];
        foreach (is_array($hourly) ? $hourly : [] as $h) {
            $hour = isset($h['hour']) ? (int) $h['hour'] : null;
            if ($hour === null || $hour < 0 || $hour > 23) continue;
            $byHour[$hour] = [
                'hour' => $hour,
                'temp' => isset($h['temp']) && is_numeric($h['temp']) ? (int) $h['temp'] : null,
                'weather' => trim((string) ($h['weather'] ?? '')),
            ];
        }

        $hourlyData = [];
        foreach ($expectedHours as $h) {
            if (isset($byHour[$h])) {
                $hourlyData[] = $byHour[$h];
            } else {
                $t = (int) round(10 + (($h - 14 + 24) % 24) * -0.2);
                $hourlyData[] = ['hour' => $h, 'temp' => $t, 'weather' => ($h >= 18 || $h < 6) ? '맑은밤' : '흐림'];
            }
        }

        return [
            'current' => [
                'temp' => $currentTemp,
                'yesterday_diff' => $yesterdayDiff,
                'direction' => $direction,
                'weather' => $currentWeather,
            ],
            'hourly' => $hourlyData,
            'updated_at' => $now->format('H:i'),
        ];
    }

    private function emptyResult(float $lat, float $lon): array
    {
        $hourly = [];
        foreach ($this->getExpectedHours() as $h) {
            $t = (int) round(10 + (($h - 14 + 24) % 24) * -0.2);
            $hourly[] = ['hour' => $h, 'temp' => $t, 'weather' => ($h >= 18 || $h < 6) ? '맑은밤' : '흐림'];
        }
        return [
            'current' => ['temp' => null, 'yesterday_diff' => null, 'direction' => 'up', 'weather' => '-'],
            'hourly' => $hourly,
            'updated_at' => $this->seoulNow()->format('H:i'),
            'location' => $this->fetchLocationName($lat, $lon),
            'source' => 'fallback',
            'briefing' => '',
        ];
    }
}
