<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/project_targets_helpers.php';

security_bootstrap_session();
security_configure_runtime_for_web();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Accel-Buffering: no');

ignore_user_abort(true);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = (string) ($_SESSION['user_type'] ?? '');
$selectedYear = isset($_SESSION['selected_year']) ? (int) $_SESSION['selected_year'] : 0;

session_write_close();

if ($userId <= 0) {
    security_send_json([
        'success' => false,
        'error' => 'Not authenticated.',
    ], 403);
}

$requestedChannels = array_values(array_unique(array_filter(array_map(
    static function ($value) {
        return preg_replace('/[^a-z0-9_,-]/i', '', trim((string) $value));
    },
    explode(',', (string) ($_GET['channels'] ?? ''))
))));

if ($requestedChannels === []) {
    security_send_json([
        'success' => false,
        'error' => 'No channels requested.',
    ], 400);
}

$allowedChannels = [
    'incoming_table',
    'outgoing_table',
    'meb_table',
    'meb_validation_table',
    'user_status_table',
    'crossmatch_recent_table',
    'deduplication_recent_table',
];

$channels = array_values(array_intersect($allowedChannels, $requestedChannels));

if ($channels === []) {
    security_send_json([
        'success' => false,
        'error' => 'No valid channels requested.',
    ], 400);
}

$clientToken = trim((string) ($_GET['token'] ?? ''));
$timeoutSeconds = (int) ($_GET['timeout'] ?? 20);
$timeoutSeconds = max(5, min(25, $timeoutSeconds));
$pollSleepMicros = 1000000;

@set_time_limit($timeoutSeconds + 5);

function live_refresh_fetch_single(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Live refresh query failed to prepare: ' . $conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    return $row;
}

function live_refresh_channel_payload(mysqli $conn, string $channel, int $selectedYear, int $userId, string $userType): array
{
    switch ($channel) {
        case 'incoming_table':
            return live_refresh_fetch_single(
                $conn,
                "SELECT
                    COUNT(*) AS row_count,
                    COALESCE(MAX(id), 0) AS max_id,
                    COALESCE(MAX(UNIX_TIMESTAMP(date_received)), 0) AS latest_date_ts,
                    COALESCE(SUM(CRC32(CONCAT_WS('|', id, date_received, tracking_number, description, focal, remarks, file_name, user_log, status))), 0) AS fingerprint
                 FROM incoming
                 WHERE YEAR(date_received) = ?",
                'i',
                [$selectedYear]
            );

        case 'outgoing_table':
            return live_refresh_fetch_single(
                $conn,
                "SELECT
                    COUNT(*) AS row_count,
                    COALESCE(MAX(id), 0) AS max_id,
                    COALESCE(MAX(UNIX_TIMESTAMP(date_out)), 0) AS latest_date_ts,
                    COALESCE(SUM(CRC32(CONCAT_WS('|', id, date_out, tracking_number, description, remarks, file_name, receiving_office, date_forwarded, user_log))), 0) AS fingerprint
                 FROM outgoing
                 WHERE YEAR(date_out) = ?",
                'i',
                [$selectedYear]
            );

        case 'meb_table':
            return live_refresh_fetch_single(
                $conn,
                "SELECT
                    COUNT(*) AS row_count,
                    COALESCE(MAX(id), 0) AS max_id,
                    COALESCE(MAX(UNIX_TIMESTAMP(time_stamp)), 0) AS latest_ts,
                    COALESCE(SUM(CRC32(CONCAT_WS('|', id, province, lgu, barangay, lastName, firstName, middleName, ext, purok, validation, time_stamp))), 0) AS fingerprint
                 FROM meb
                 WHERE YEAR(time_stamp) = ?",
                'i',
                [$selectedYear]
            );

        case 'meb_validation_table':
            ensureProjectLawaBinhiTargets($conn);
            $mebSnapshot = live_refresh_fetch_single(
                $conn,
                "SELECT
                    COUNT(*) AS meb_count,
                    COALESCE(MAX(id), 0) AS meb_max_id,
                    COALESCE(SUM(CRC32(CONCAT_WS('|', id, province, lgu, barangay, validation, time_stamp))), 0) AS meb_fingerprint
                 FROM meb
                 WHERE YEAR(time_stamp) = ?",
                'i',
                [$selectedYear]
            );
            $targetSnapshot = live_refresh_fetch_single(
                $conn,
                "SELECT
                    COUNT(*) AS target_count,
                    COALESCE(MAX(id), 0) AS target_max_id,
                    COALESCE(SUM(CRC32(CONCAT_WS('|', id, fiscal_year, province, municipality, barangay, target_partner_beneficiaries))), 0) AS target_fingerprint
                 FROM project_lawa_binhi_targets
                 WHERE fiscal_year = ?",
                'i',
                [$selectedYear]
            );

            return [
                'meb_count' => (int) ($mebSnapshot['meb_count'] ?? 0),
                'meb_max_id' => (int) ($mebSnapshot['meb_max_id'] ?? 0),
                'meb_fingerprint' => (string) ($mebSnapshot['meb_fingerprint'] ?? '0'),
                'target_count' => (int) ($targetSnapshot['target_count'] ?? 0),
                'target_max_id' => (int) ($targetSnapshot['target_max_id'] ?? 0),
                'target_fingerprint' => (string) ($targetSnapshot['target_fingerprint'] ?? '0'),
            ];

        case 'user_status_table':
            if ($userType !== 'admin') {
                return ['forbidden' => 1];
            }

            return live_refresh_fetch_single(
                $conn,
                "SELECT
                    COUNT(*) AS user_count,
                    COALESCE(MAX(id), 0) AS max_user_id,
                    COALESCE(MAX(UNIX_TIMESTAMP(last_activity)), 0) AS latest_activity_ts,
                    COALESCE(SUM(is_online), 0) AS online_total,
                    COALESCE(SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS deactivated_total,
                    COALESCE(SUM(CRC32(CONCAT_WS('|', id, userType, is_online, COALESCE(last_activity, ''), COALESCE(deleted_at, '')))), 0) AS fingerprint
                 FROM users"
            );

        case 'crossmatch_recent_table':
            return live_refresh_fetch_single(
                $conn,
                "SELECT
                    COUNT(DISTINCT j.id) AS job_count,
                    COALESCE(MAX(j.id), 0) AS max_job_id,
                    COALESCE(MAX(UNIX_TIMESTAMP(j.created_at)), 0) AS latest_job_ts,
                    COALESCE(MAX(r.id), 0) AS max_result_id,
                    COALESCE(COUNT(r.id), 0) AS result_count,
                    COALESCE(SUM(DISTINCT CRC32(CONCAT_WS('|', j.id, j.file1_name, j.file2_name, j.rule, j.threshold, j.created_at))), 0) AS job_fingerprint
                 FROM crossmatch_jobs j
                 LEFT JOIN crossmatch_results r ON r.job_id = j.id
                 WHERE YEAR(j.created_at) = ?",
                'i',
                [$selectedYear]
            );

        case 'deduplication_recent_table':
            return live_refresh_fetch_single(
                $conn,
                "SELECT
                    COUNT(DISTINCT j.id) AS job_count,
                    COALESCE(MAX(j.id), 0) AS max_job_id,
                    COALESCE(MAX(UNIX_TIMESTAMP(j.created_at)), 0) AS latest_job_ts,
                    COALESCE(MAX(r.id), 0) AS max_result_id,
                    COALESCE(COUNT(r.id), 0) AS result_count,
                    COALESCE(SUM(DISTINCT CRC32(CONCAT_WS('|', j.id, j.file_name, j.status, j.rule, j.threshold, j.created_at))), 0) AS job_fingerprint
                 FROM deduplication_jobs j
                 LEFT JOIN deduplication_results r ON r.job_id = j.id
                 WHERE YEAR(j.created_at) = ?",
                'i',
                [$selectedYear]
            );

        default:
            return ['unsupported' => 1];
    }
}

function live_refresh_snapshot(mysqli $conn, array $channels, int $selectedYear, int $userId, string $userType): array
{
    $snapshot = [];
    foreach ($channels as $channel) {
        $snapshot[$channel] = live_refresh_channel_payload($conn, $channel, $selectedYear, $userId, $userType);
    }
    ksort($snapshot);

    return $snapshot;
}

$startedAt = time();
$changed = true;
$snapshot = [];
$nextToken = '';

do {
    $snapshot = live_refresh_snapshot($conn, $channels, $selectedYear, $userId, $userType);
    $nextToken = hash('sha256', json_encode($snapshot));
    $changed = $clientToken === '' || !hash_equals($clientToken, $nextToken);

    if ($changed) {
        break;
    }

    if ((time() - $startedAt) >= $timeoutSeconds) {
        break;
    }

    usleep($pollSleepMicros);
} while (true);

security_send_json([
    'success' => true,
    'changed' => $changed,
    'token' => $nextToken,
    'channels' => $snapshot,
]);
