<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
require_once '../config.php';
require_once '../project_targets_helpers.php';
require_once __DIR__ . '/activity_metadata.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
header('Content-Type: application/json');

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected.', 'data' => []]);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];
ensureProjectLawaBinhiTargets($conn);
ensureProgramActivityMetadata($conn, $selectedYear);

function parseProjectLocationRecordList($value): array
{
    return parseProjectTargetMultiValueCell((string) $value, false);
}

function normalizeProjectLocationRecordCoordinate($value): ?float
{
    $normalized = trim((string) $value);
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}

function fallbackActualProjectRecordId(int $parentId, int $index): string
{
    return 'pa-' . $parentId . '-' . $index;
}

$records = [];

$stmt = $conn->prepare("
    SELECT
        id,
        province,
        municipality,
        barangay
    FROM program_activity_metadata
    WHERE fiscal_year = ?
");
$stmt->bind_param('i', $selectedYear);
$stmt->execute();
$rows = db_stmt_fetch_all_assoc($stmt);
$stmt->close();
$actualProjectMap = programActivityFetchActualProjectsByMetadataIds($conn, array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows));

foreach ($rows as $row) {
    $actualProjectRows = $actualProjectMap[(int) ($row['id'] ?? 0)] ?? [];

    foreach ($actualProjectRows as $index => $actualProjectRow) {
        $status = strtolower(trim((string) ($actualProjectRow['status'] ?? 'pending')));
        if (!in_array($status, ['confirmed', 'custom'], true)) {
            continue;
        }

        $actualProjectId = trim((string) ($actualProjectRow['actual_project_id'] ?? ''));
        $projectCode = trim((string) ($actualProjectRow['project_code'] ?? ''));
        $coverageEntryId = trim((string) ($actualProjectRow['coverage_entry_id'] ?? ''));
        if ($actualProjectId === '') {
            $actualProjectId = $coverageEntryId !== '' ? $coverageEntryId : fallbackActualProjectRecordId((int) $row['id'], $index);
        }
        if ($projectCode === '') {
            $projectCode = $actualProjectId;
        }

        $records[] = [
            'project_id' => $actualProjectId,
            'project_code' => $projectCode,
            'project_name' => trim((string) ($actualProjectRow['project_name'] ?? '')) ?: 'Unnamed Project',
            'latitude' => normalizeProjectLocationRecordCoordinate($actualProjectRow['latitude'] ?? null),
            'longitude' => normalizeProjectLocationRecordCoordinate($actualProjectRow['longitude'] ?? null),
            'purok' => trim((string) ($actualProjectRow['purok'] ?? '')),
            'barangay' => (string) ($row['barangay'] ?? ''),
            'municipality' => (string) ($row['municipality'] ?? ''),
            'province' => (string) ($row['province'] ?? ''),
            'drive_link' => trim((string) ($actualProjectRow['drive_link'] ?? '')),
            'status' => $status,
        ];
    }
}

usort($records, static function (array $left, array $right): int {
    $locationComparison = strcmp(
        implode('|', [
            mb_strtolower((string) ($left['province'] ?? '')),
            mb_strtolower((string) ($left['municipality'] ?? '')),
            mb_strtolower((string) ($left['barangay'] ?? '')),
            mb_strtolower((string) ($left['purok'] ?? '')),
            mb_strtolower((string) ($left['project_name'] ?? '')),
        ]),
        implode('|', [
            mb_strtolower((string) ($right['province'] ?? '')),
            mb_strtolower((string) ($right['municipality'] ?? '')),
            mb_strtolower((string) ($right['barangay'] ?? '')),
            mb_strtolower((string) ($right['purok'] ?? '')),
            mb_strtolower((string) ($right['project_name'] ?? '')),
        ])
    );

    if ($locationComparison !== 0) {
        return $locationComparison;
    }

    return strcmp((string) ($left['project_code'] ?? ''), (string) ($right['project_code'] ?? ''));
});

echo json_encode([
    'success' => true,
    'data' => array_values($records),
]);
