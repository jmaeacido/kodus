<?php

require_once __DIR__ . '/env_helpers.php';

function app_location_default_timezone(): string
{
    $configured = app_env('APP_TIMEZONE', 'Asia/Manila');
    return app_location_is_valid_timezone($configured ?? '') ? (string) $configured : 'Asia/Manila';
}

function app_location_is_valid_timezone(string $timezone): bool
{
    if ($timezone === '') {
        return false;
    }

    try {
        new DateTimeZone($timezone);
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function app_location_normalize_timezone(?string $timezone): ?string
{
    $value = trim((string) $timezone);
    return app_location_is_valid_timezone($value) ? $value : null;
}

function app_location_normalize_coordinate($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round((float) $value, 6);
}

function app_location_is_valid_latitude(float $latitude): bool
{
    return $latitude >= -90.0 && $latitude <= 90.0;
}

function app_location_is_valid_longitude(float $longitude): bool
{
    return $longitude >= -180.0 && $longitude <= 180.0;
}

function app_location_session_snapshot(): array
{
    $timezone = app_location_normalize_timezone($_SESSION['app_client_timezone'] ?? null);
    $latitude = app_location_normalize_coordinate($_SESSION['app_client_latitude'] ?? null);
    $longitude = app_location_normalize_coordinate($_SESSION['app_client_longitude'] ?? null);
    $capturedAt = isset($_SESSION['app_client_location_captured_at']) && is_numeric($_SESSION['app_client_location_captured_at'])
        ? (int) $_SESSION['app_client_location_captured_at']
        : 0;
    $capturedAtIso = $capturedAt > 0 ? gmdate('c', $capturedAt) : null;

    if ($latitude === null || !app_location_is_valid_latitude($latitude)) {
        $latitude = null;
    }

    if ($longitude === null || !app_location_is_valid_longitude($longitude)) {
        $longitude = null;
    }

    return [
        'timezone' => $timezone,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'captured_at_iso' => $capturedAtIso,
    ];
}

function app_current_timezone(): string
{
    $snapshot = app_location_session_snapshot();
    return $snapshot['timezone'] ?? app_location_default_timezone();
}

function app_apply_current_timezone(): string
{
    $timezone = app_current_timezone();
    date_default_timezone_set($timezone);
    return $timezone;
}

function app_timezone_offset_string(?int $timestamp = null, ?string $timezone = null): string
{
    $resolvedTimezone = $timezone ?? app_current_timezone();
    $date = new DateTimeImmutable('now', new DateTimeZone($resolvedTimezone));

    if ($timestamp !== null) {
        $date = $date->setTimestamp($timestamp);
    }

    return $date->format('P');
}

function app_apply_mysql_timezone(mysqli $conn, ?int $timestamp = null): void
{
    $offset = app_timezone_offset_string($timestamp);
    $escapedOffset = $conn->real_escape_string($offset);
    $conn->query("SET time_zone = '{$escapedOffset}'");
}

function app_current_coordinates(): ?array
{
    $snapshot = app_location_session_snapshot();
    if ($snapshot['latitude'] !== null && $snapshot['longitude'] !== null) {
        return [
            'latitude' => $snapshot['latitude'],
            'longitude' => $snapshot['longitude'],
            'source' => 'session',
        ];
    }

    $fallbackLatitude = app_location_normalize_coordinate(app_env('KODA_WEATHER_LATITUDE'));
    $fallbackLongitude = app_location_normalize_coordinate(app_env('KODA_WEATHER_LONGITUDE'));

    if (
        $fallbackLatitude !== null
        && $fallbackLongitude !== null
        && app_location_is_valid_latitude($fallbackLatitude)
        && app_location_is_valid_longitude($fallbackLongitude)
    ) {
        return [
            'latitude' => $fallbackLatitude,
            'longitude' => $fallbackLongitude,
            'source' => 'env',
        ];
    }

    return null;
}

function app_location_reverse_geocode_label(float $latitude, float $longitude): ?string
{
    $url = sprintf(
        'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=%s&lon=%s&zoom=14&addressdetails=1',
        rawurlencode(sprintf('%.6f', $latitude)),
        rawurlencode(sprintf('%.6f', $longitude))
    );

    $responseBody = null;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: KODUS/1.0 (+https://kodus.local)',
                ],
            ]);

            $result = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if (is_string($result) && $status >= 200 && $status < 300) {
                $responseBody = $result;
            }
        }
    }

    if ($responseBody === null) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'header' => "Accept: application/json\r\nUser-Agent: KODUS/1.0 (+https://kodus.local)\r\n",
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        if (is_string($result) && $result !== '') {
            $responseBody = $result;
        }
    }

    if (!is_string($responseBody) || trim($responseBody) === '') {
        return null;
    }

    $payload = json_decode($responseBody, true);
    if (!is_array($payload)) {
        return null;
    }

    $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];
    $labelParts = [];

    $primaryLocality = '';
    foreach (['city', 'town', 'municipality', 'city_district'] as $key) {
        $primaryLocality = trim((string) ($address[$key] ?? ''));
        if ($primaryLocality !== '') {
            break;
        }
    }

    $localityKeys = $primaryLocality !== ''
        ? ['city', 'town', 'municipality', 'city_district']
        : ['village', 'suburb', 'quarter', 'neighbourhood'];

    foreach ([
        $localityKeys,
        ['county', 'province', 'state', 'region'],
        ['country'],
    ] as $keyGroup) {
        foreach ($keyGroup as $key) {
            $value = trim((string) ($address[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $alreadyIncluded = false;
            foreach ($labelParts as $part) {
                if (strcasecmp($part, $value) === 0) {
                    $alreadyIncluded = true;
                    break;
                }
            }

            if (!$alreadyIncluded) {
                $labelParts[] = $value;
            }

            break;
        }
    }

    if ($labelParts !== []) {
        return implode(', ', $labelParts);
    }

    $displayName = trim((string) ($payload['display_name'] ?? ''));
    if ($displayName !== '') {
        $parts = array_filter(array_map('trim', explode(',', $displayName)));
        return implode(', ', array_slice($parts, 0, 3));
    }

    return null;
}

function app_describe_client_location(): string
{
    $labelCacheVersion = 2;
    $snapshot = app_location_session_snapshot();
    $latitude = $snapshot['latitude'];
    $longitude = $snapshot['longitude'];

    if ($latitude === null || $longitude === null) {
        return 'Unavailable';
    }

    $cachedLatitude = app_location_normalize_coordinate($_SESSION['app_client_location_label_latitude'] ?? null);
    $cachedLongitude = app_location_normalize_coordinate($_SESSION['app_client_location_label_longitude'] ?? null);
    $cachedLabel = trim((string) ($_SESSION['app_client_location_label'] ?? ''));
    $cachedVersion = (int) ($_SESSION['app_client_location_label_version'] ?? 0);

    if ($cachedLabel !== '' && $cachedLatitude === $latitude && $cachedLongitude === $longitude && $cachedVersion === $labelCacheVersion) {
        return $cachedLabel;
    }

    $resolvedLabel = app_location_reverse_geocode_label($latitude, $longitude);
    if ($resolvedLabel !== null && $resolvedLabel !== '') {
        $_SESSION['app_client_location_label'] = $resolvedLabel;
        $_SESSION['app_client_location_label_latitude'] = $latitude;
        $_SESSION['app_client_location_label_longitude'] = $longitude;
        $_SESSION['app_client_location_label_version'] = $labelCacheVersion;
        return $resolvedLabel;
    }

    $fallback = sprintf('Lat %.6f, Lng %.6f', $latitude, $longitude);
    $_SESSION['app_client_location_label'] = $fallback;
    $_SESSION['app_client_location_label_latitude'] = $latitude;
    $_SESSION['app_client_location_label_longitude'] = $longitude;
    $_SESSION['app_client_location_label_version'] = $labelCacheVersion;

    return $fallback;
}

function app_store_client_location_context(array $input): bool
{
    $changed = false;
    $timezone = app_location_normalize_timezone($input['timezone'] ?? null);
    $latitude = app_location_normalize_coordinate($input['latitude'] ?? null);
    $longitude = app_location_normalize_coordinate($input['longitude'] ?? null);

    if ($timezone !== null && ($_SESSION['app_client_timezone'] ?? null) !== $timezone) {
        $_SESSION['app_client_timezone'] = $timezone;
        $changed = true;
    }

    $hasValidCoordinates = $latitude !== null
        && $longitude !== null
        && app_location_is_valid_latitude($latitude)
        && app_location_is_valid_longitude($longitude);

    if ($hasValidCoordinates) {
        $storedLatitude = app_location_normalize_coordinate($_SESSION['app_client_latitude'] ?? null);
        $storedLongitude = app_location_normalize_coordinate($_SESSION['app_client_longitude'] ?? null);

        if ($storedLatitude !== $latitude || $storedLongitude !== $longitude) {
            $_SESSION['app_client_latitude'] = $latitude;
            $_SESSION['app_client_longitude'] = $longitude;
            unset(
                $_SESSION['app_client_location_label'],
                $_SESSION['app_client_location_label_latitude'],
                $_SESSION['app_client_location_label_longitude'],
                $_SESSION['app_client_location_label_version']
            );
            $changed = true;
        }
    }

    if ($changed) {
        $_SESSION['app_client_location_captured_at'] = time();
    }

    return $changed;
}
