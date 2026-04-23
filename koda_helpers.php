<?php

require_once __DIR__ . '/env_helpers.php';
require_once __DIR__ . '/app_location_helpers.php';

function koda_env_bool(string $key, bool $default = false): bool
{
    $value = strtolower((string) app_env($key, $default ? 'true' : 'false'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function koda_scene_palette(string $scene): array
{
    return match ($scene) {
        'rainy' => [
            'name' => 'Rainy',
            'subtitle' => 'Rainy Day',
            'card_background' => 'linear-gradient(180deg, #b8d7ea 0%, #99c7e2 32%, #6aa5c6 33%, #4d87ad 54%, #8ca7b6 54%, #708a9c 100%)',
            'sun_orb' => 'rgba(255,255,255,0.28)',
            'glow' => 'rgba(255,255,255,0.16)',
            'shore' => 'rgba(255,255,255,0.22)',
            'text_accent' => '#0b5e8c',
            'prompt_accent' => '#0a6aa1',
            'copy_text' => '#123f5a',
            'particle_a' => '#8ecae6',
            'particle_b' => '#bde0fe',
            'particle_c' => '#90e0ef',
            'overlay_css' => 'linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04))',
        ],
        'cloudy' => [
            'name' => 'Cloudy',
            'subtitle' => 'Cloudy Coast',
            'card_background' => 'linear-gradient(180deg, #d7e6f3 0%, #c5d9eb 36%, #83c5e8 37%, #4aa9d9 56%, #e4d3b5 56%, #d9bc8f 100%)',
            'sun_orb' => 'rgba(255,255,255,0.46)',
            'glow' => 'rgba(255,255,255,0.18)',
            'shore' => 'rgba(255,255,255,0.2)',
            'text_accent' => '#156082',
            'prompt_accent' => '#0d6efd',
            'copy_text' => '#33566f',
            'particle_a' => '#8ecae6',
            'particle_b' => '#d7efff',
            'particle_c' => '#cdb4db',
            'overlay_css' => 'linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02))',
        ],
        'stormy' => [
            'name' => 'Stormy',
            'subtitle' => 'Resilience Weather',
            'card_background' => 'linear-gradient(180deg, #66758f 0%, #51607a 34%, #2a5370 35%, #1d3b52 56%, #7a7f8d 56%, #636973 100%)',
            'sun_orb' => 'rgba(255,255,255,0.16)',
            'glow' => 'rgba(255,255,255,0.08)',
            'shore' => 'rgba(255,255,255,0.12)',
            'text_accent' => '#e8f4ff',
            'prompt_accent' => '#ffe082',
            'copy_text' => '#edf6ff',
            'particle_a' => '#cdb4db',
            'particle_b' => '#8ecae6',
            'particle_c' => '#ffd166',
            'overlay_css' => 'linear-gradient(180deg, rgba(255,255,255,0.04), rgba(0,0,0,0.08))',
        ],
        'night' => [
            'name' => 'Moonlit',
            'subtitle' => 'Night Tide',
            'card_background' => 'linear-gradient(180deg, #16213e 0%, #1f4068 34%, #246b9c 35%, #16425b 56%, #2f4858 56%, #27404e 100%)',
            'sun_orb' => 'rgba(238, 242, 255, 0.72)',
            'glow' => 'rgba(191, 219, 254, 0.18)',
            'shore' => 'rgba(255,255,255,0.14)',
            'text_accent' => '#dbeafe',
            'prompt_accent' => '#bfdbfe',
            'copy_text' => '#e5eef9',
            'particle_a' => '#93c5fd',
            'particle_b' => '#c4b5fd',
            'particle_c' => '#e0f2fe',
            'overlay_css' => 'linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01))',
        ],
        default => [
            'name' => 'Sunny',
            'subtitle' => 'Summer Beach',
            'card_background' => 'linear-gradient(180deg, #8fe3ff 0%, #7ed8ff 38%, #33b1e6 39%, #1593ce 58%, #ffd89b 58%, #f6c97a 100%)',
            'sun_orb' => 'rgba(255, 240, 140, 0.95)',
            'glow' => 'rgba(255, 205, 96, 0.35)',
            'shore' => 'rgba(255,255,255,0.2)',
            'text_accent' => '#0d6efd',
            'prompt_accent' => '#0d6efd',
            'copy_text' => '#335a74',
            'particle_a' => '#ffb703',
            'particle_b' => '#cdb4db',
            'particle_c' => '#ff7b54',
            'overlay_css' => 'linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02))',
        ],
    };
}

function koda_expression_map(string $expression): array
{
    return match ($expression) {
        'happy' => [
            'eyes' => 'happy',
            'mouth' => 'M80 155 Q100 185 120 155',
            'brows' => false,
        ],
        'sad' => [
            'eyes' => 'round',
            'mouth' => 'M85 175 Q100 160 115 175',
            'brows' => false,
        ],
        'concerned' => [
            'eyes' => 'round',
            'mouth' => 'M88 168 Q100 160 112 168',
            'brows' => true,
        ],
        'shocked' => [
            'eyes' => 'spark',
            'mouth' => 'circle',
            'brows' => false,
        ],
        'resilient' => [
            'eyes' => 'focused',
            'mouth' => 'M85 165 L115 165',
            'brows' => true,
        ],
        'sleepy' => [
            'eyes' => 'sleepy',
            'mouth' => 'M90 167 Q100 162 110 167',
            'brows' => false,
        ],
        default => [
            'eyes' => 'round',
            'mouth' => 'M85 158 Q100 173 115 158',
            'brows' => false,
        ],
    };
}

function koda_scene_from_weather_code(int $weatherCode, bool $isDay): string
{
    if (!$isDay) {
        return in_array($weatherCode, [95, 96, 99], true) ? 'stormy' : 'night';
    }

    return match (true) {
        $weatherCode === 0 => 'sunny',
        in_array($weatherCode, [1, 2, 3, 45, 48], true) => 'cloudy',
        in_array($weatherCode, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true) => 'rainy',
        in_array($weatherCode, [71, 73, 75, 77, 85, 86, 95, 96, 99], true) => 'stormy',
        default => 'cloudy',
    };
}

function koda_expression_from_scene(string $scene): string
{
    return match ($scene) {
        'sunny' => 'happy',
        'rainy' => 'concerned',
        'stormy' => 'resilient',
        'night' => 'sleepy',
        default => 'calm',
    };
}

function koda_local_fallback_scene(): string
{
    $hour = (int) date('G');
    $month = (int) date('n');

    if ($hour < 6 || $hour >= 19) {
        return 'night';
    }

    if (in_array($month, [6, 7, 8, 9], true)) {
        return 'rainy';
    }

    if (in_array($month, [3, 4, 5], true)) {
        return 'sunny';
    }

    return 'cloudy';
}

function koda_fetch_weather_scene(): ?array
{
    $coordinates = app_current_coordinates();
    if ($coordinates === null) {
        return null;
    }

    $latitude = (string) $coordinates['latitude'];
    $longitude = (string) $coordinates['longitude'];

    $cacheDir = __DIR__ . '/storage/cache';
    $cacheKey = md5($latitude . ',' . $longitude);
    $cacheFile = $cacheDir . '/koda_weather_scene_' . $cacheKey . '.json';
    $cacheTtl = max(300, (int) app_env('KODA_WEATHER_CACHE_TTL', '1800'));

    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['scene'])) {
            return $cached;
        }
    }

    $url = sprintf(
        'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&current=weather_code,is_day&timezone=auto',
        rawurlencode($latitude),
        rawurlencode($longitude)
    );

    $response = false;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($curl);
        }
    }

    if ($response === false) {
        $response = @file_get_contents($url);
    }

    if (!is_string($response) || trim($response) === '') {
        return null;
    }

    $payload = json_decode($response, true);
    if (!is_array($payload) || !isset($payload['current']['weather_code'])) {
        return null;
    }

    $weatherCode = (int) $payload['current']['weather_code'];
    $isDay = ((int) ($payload['current']['is_day'] ?? 1)) === 1;
    $scene = koda_scene_from_weather_code($weatherCode, $isDay);

    $result = [
        'scene' => $scene,
        'weather_code' => $weatherCode,
        'is_day' => $isDay,
        'source' => 'weather',
        'latitude' => (float) $latitude,
        'longitude' => (float) $longitude,
    ];

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    @file_put_contents($cacheFile, json_encode($result));

    return $result;
}

function koda_resolve_identity(): array
{
    $mode = strtolower((string) app_env('KODA_SCENE_MODE', 'auto'));
    $sceneOverride = strtolower((string) app_env('KODA_SCENE_OVERRIDE', ''));
    $expressionOverride = strtolower((string) app_env('KODA_EXPRESSION_OVERRIDE', ''));

    $scene = '';
    $source = 'fallback';

    if ($mode === 'manual' && $sceneOverride !== '') {
        $scene = $sceneOverride;
        $source = 'manual';
    } elseif ($mode === 'weather' || $mode === 'auto') {
        $weather = koda_fetch_weather_scene();
        if (is_array($weather) && !empty($weather['scene'])) {
            $scene = (string) $weather['scene'];
            $source = (string) ($weather['source'] ?? 'weather');
        }
    }

    if ($scene === '') {
        $scene = koda_local_fallback_scene();
    }

    $expression = $expressionOverride !== '' ? $expressionOverride : koda_expression_from_scene($scene);
    $palette = koda_scene_palette($scene);
    $face = koda_expression_map($expression);

    return [
        'scene' => $scene,
        'scene_label' => $palette['subtitle'],
        'expression' => $expression,
        'source' => $source,
        'palette' => $palette,
        'face' => $face,
    ];
}
