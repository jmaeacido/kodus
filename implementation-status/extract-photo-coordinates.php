<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
require_once '../config.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

header('Content-Type: application/json');

if (!auth_can_manage_program_activities()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

function photo_coordinate_drive_file_id(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $patterns = [
        '#drive\.google\.com/file/d/([A-Za-z0-9_-]{10,})#i',
        '#drive\.google\.com/open\?id=([A-Za-z0-9_-]{10,})#i',
        '#drive\.google\.com/uc\?(?:[^#]*&)?id=([A-Za-z0-9_-]{10,})#i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return (string) ($matches[1] ?? '');
        }
    }

    $query = parse_url($url, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        parse_str($query, $params);
        $id = (string) ($params['id'] ?? '');
        if (preg_match('/^[A-Za-z0-9_-]{10,}$/', $id)) {
            return $id;
        }
    }

    return '';
}

function photo_coordinate_is_google_drive_url(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    return in_array($host, ['drive.google.com', 'docs.google.com'], true)
        || substr($host, -17) === '.drive.google.com'
        || substr($host, -16) === '.docs.google.com';
}

function photo_coordinate_download_drive_file(string $fileId): string
{
    $tmpPath = tempnam(sys_get_temp_dir(), 'kodus_photo_');
    if ($tmpPath === false) {
        throw new RuntimeException('Could not prepare a temporary photo file.');
    }

    $handle = fopen($tmpPath, 'wb');
    if (!$handle) {
        @unlink($tmpPath);
        throw new RuntimeException('Could not open a temporary photo file.');
    }

    $downloadedBytes = 0;
    $maxBytes = 20 * 1024 * 1024;
    $curl = curl_init('https://drive.google.com/uc?export=download&id=' . rawurlencode($fileId));
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'KODUS/1.0',
        CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use ($handle, &$downloadedBytes, $maxBytes): int {
            $length = strlen($chunk);
            $downloadedBytes += $length;
            if ($downloadedBytes > $maxBytes) {
                return 0;
            }

            return fwrite($handle, $chunk) ?: 0;
        },
    ]);

    $ok = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($handle);

    if (!$ok || $statusCode < 200 || $statusCode >= 300) {
        @unlink($tmpPath);
        throw new RuntimeException($error !== '' ? $error : 'Could not download the Google Drive photo.');
    }

    if (stripos($contentType, 'image/') === false) {
        @unlink($tmpPath);
        throw new RuntimeException('The Drive link did not return a direct image file. Make sure the file is shared with view access.');
    }

    return $tmpPath;
}

function photo_coordinate_rational_to_float($value): float
{
    if (is_array($value)) {
        $value = reset($value);
    }

    $value = trim((string) $value);
    if ($value === '') {
        return 0.0;
    }

    if (strpos($value, '/') !== false) {
        [$numerator, $denominator] = array_pad(explode('/', $value, 2), 2, '1');
        $denominatorFloat = (float) $denominator;
        return $denominatorFloat != 0.0 ? ((float) $numerator / $denominatorFloat) : 0.0;
    }

    return (float) $value;
}

function photo_coordinate_gps_to_decimal(array $parts, string $ref): float
{
    $degrees = photo_coordinate_rational_to_float($parts[0] ?? 0);
    $minutes = photo_coordinate_rational_to_float($parts[1] ?? 0);
    $seconds = photo_coordinate_rational_to_float($parts[2] ?? 0);
    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    $ref = strtoupper(trim($ref));
    if ($ref === 'S' || $ref === 'W') {
        $decimal *= -1;
    }

    return $decimal;
}

function photo_coordinate_find_text_coordinates(array $exif): ?array
{
    $text = '';
    array_walk_recursive($exif, static function ($value) use (&$text): void {
        if (is_scalar($value)) {
            $text .= ' ' . (string) $value;
        }
    });

    if (preg_match('/(-?\d{1,2}\.\d{4,})\s*,\s*(-?\d{1,3}\.\d{4,})/', $text, $matches)) {
        return [(float) $matches[1], (float) $matches[2]];
    }

    return null;
}

function photo_coordinate_parse_coordinate_text(string $text): ?array
{
    if (preg_match('/(-?\d{1,2}\.\d{4,})\s*[,;]\s*(-?\d{1,3}\.\d{4,})/', $text, $matches)) {
        return [(float) $matches[1], (float) $matches[2]];
    }

    return null;
}

function photo_coordinate_command_exists(string $command): bool
{
    $result = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));
    return $result !== '';
}

function photo_coordinate_ocr_file(string $path): ?array
{
    if (!photo_coordinate_command_exists('tesseract')) {
        return null;
    }

    $ocrInput = $path;
    $cropPath = '';

    if (photo_coordinate_command_exists('convert')) {
        $cropPath = tempnam(sys_get_temp_dir(), 'kodus_photo_ocr_');
        if ($cropPath !== false) {
            $cropImagePath = $cropPath . '.png';
            @unlink($cropPath);
            $command = implode(' ', [
                'convert',
                escapeshellarg($path),
                '-gravity southwest',
                '-crop 70%x34%+0+0',
                '-colorspace Gray',
                '-resize 220%',
                '-contrast-stretch 0',
                '-sharpen 0x1',
                escapeshellarg($cropImagePath),
                '2>/dev/null',
            ]);
            shell_exec($command);
            if (is_file($cropImagePath) && filesize($cropImagePath) > 0) {
                $ocrInput = $cropImagePath;
                $cropPath = $cropImagePath;
            }
        }
    }

    $command = implode(' ', [
        'tesseract',
        escapeshellarg($ocrInput),
        'stdout',
        '--psm 6',
        '-l eng',
        '2>/dev/null',
    ]);
    $ocrText = (string) shell_exec($command);

    if ($cropPath !== '') {
        @unlink($cropPath);
    }

    $coordinates = photo_coordinate_parse_coordinate_text($ocrText);
    if ($coordinates === null) {
        return null;
    }

    return [
        'latitude' => $coordinates[0],
        'longitude' => $coordinates[1],
        'source' => 'ocr',
        'ocr_text' => trim($ocrText),
    ];
}

function photo_coordinate_extract_from_file(string $path): array
{
    $exif = function_exists('exif_read_data') ? @exif_read_data($path, null, true) : false;
    if (is_array($exif)) {
        $gps = $exif['GPS'] ?? [];
        if (isset($gps['GPSLatitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitude'], $gps['GPSLongitudeRef'])) {
            return [
                'latitude' => photo_coordinate_gps_to_decimal((array) $gps['GPSLatitude'], (string) $gps['GPSLatitudeRef']),
                'longitude' => photo_coordinate_gps_to_decimal((array) $gps['GPSLongitude'], (string) $gps['GPSLongitudeRef']),
                'source' => 'exif',
            ];
        }

        $textCoordinates = photo_coordinate_find_text_coordinates($exif);
        if ($textCoordinates !== null) {
            return [
                'latitude' => $textCoordinates[0],
                'longitude' => $textCoordinates[1],
                'source' => 'metadata_text',
            ];
        }
    }

    $ocrCoordinates = photo_coordinate_ocr_file($path);
    if ($ocrCoordinates !== null) {
        return $ocrCoordinates;
    }

    throw new RuntimeException('No GPS metadata or readable coordinate stamp was found. Install Tesseract OCR on the server to read visible coordinate text.');
}

$driveLink = trim((string) ($_POST['drive_link'] ?? ''));
if ($driveLink === '' || filter_var($driveLink, FILTER_VALIDATE_URL) === false || !photo_coordinate_is_google_drive_url($driveLink)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Provide a valid Google Drive photo share URL.']);
    exit;
}

$fileId = photo_coordinate_drive_file_id($driveLink);
if ($fileId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Could not identify the Google Drive file ID.']);
    exit;
}

$tmpPath = '';
try {
    $tmpPath = photo_coordinate_download_drive_file($fileId);
    $coordinates = photo_coordinate_extract_from_file($tmpPath);
    echo json_encode([
        'success' => true,
        'latitude' => round((float) $coordinates['latitude'], 7),
        'longitude' => round((float) $coordinates['longitude'], 7),
        'source' => $coordinates['source'],
    ]);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
} finally {
    if ($tmpPath !== '') {
        @unlink($tmpPath);
    }
}
