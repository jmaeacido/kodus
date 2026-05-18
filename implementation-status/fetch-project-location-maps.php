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
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected.', 'markers' => []]);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];
ensureProjectLawaBinhiTargets($conn);
ensureProgramActivityMetadata($conn, $selectedYear);

function parseLocationMapList($value, bool $uppercase = false): array
{
    return parseProjectTargetMultiValueCell((string) $value, !$uppercase ? false : true);
}

function normalizeMapCoordinate($value): ?float
{
    $normalized = trim((string) $value);
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}

function isValidMapCoordinate(?float $latitude, ?float $longitude): bool
{
    if ($latitude === null || $longitude === null) {
        return false;
    }

    return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
}

function fallbackProjectRowId(string $prefix, int $parentId, int $index): string
{
    return $prefix . '-' . $parentId . '-' . $index;
}

$markers = [];

$activityStmt = $conn->prepare("
    SELECT
        id,
        fiscal_year,
        province,
        municipality,
        barangay,
        updated_at
    FROM program_activity_metadata
    WHERE fiscal_year = ?
");
$activityStmt->bind_param('i', $selectedYear);
$activityStmt->execute();
$activityRows = db_stmt_fetch_all_assoc($activityStmt);
$activityStmt->close();
$actualProjectMap = programActivityFetchActualProjectsByMetadataIds($conn, array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $activityRows));
$photoLinkMap = programActivityFetchPhotoLinksByMetadataIds($conn, array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $activityRows));

foreach ($activityRows as $row) {
    $actualProjectRows = $actualProjectMap[(int) ($row['id'] ?? 0)] ?? [];
    $photoLinks = array_map(static function (array $entry): array {
        return [
            'folder_key' => (string) ($entry['folder_key'] ?? ''),
            'folder_label' => (string) ($entry['folder_label'] ?? ''),
            'drive_link' => (string) ($entry['drive_link'] ?? ''),
        ];
    }, $photoLinkMap[(int) ($row['id'] ?? 0)] ?? []);

    foreach ($actualProjectRows as $index => $actualProjectRow) {
        $status = strtolower(trim((string) ($actualProjectRow['status'] ?? 'pending')));
        if (!in_array($status, ['confirmed', 'custom'], true)) {
            continue;
        }

        $latitude = normalizeMapCoordinate($actualProjectRow['latitude'] ?? null);
        $longitude = normalizeMapCoordinate($actualProjectRow['longitude'] ?? null);
        if (!isValidMapCoordinate($latitude, $longitude)) {
            continue;
        }

        $actualProjectId = trim((string) ($actualProjectRow['actual_project_id'] ?? ''));
        $projectCode = trim((string) ($actualProjectRow['project_code'] ?? ''));
        if ($actualProjectId === '') {
            $actualProjectId = fallbackProjectRowId('pa', (int) $row['id'], $index);
        }
        if ($projectCode === '') {
            $projectCode = $actualProjectId;
        }

        $markers[] = [
            'project_id' => $projectCode,
            'project_code' => $projectCode,
            'actual_project_id' => $actualProjectId,
            'target_project_row_id' => trim((string) ($actualProjectRow['target_project_row_id'] ?? '')),
            'parent_record_id' => (int) $row['id'],
            'module_source' => 'program-activities',
            'module_label' => 'Program Activity',
            'status' => $status,
            'status_label' => $status === 'confirmed' ? 'Target Confirmed as Actual' : 'Custom Actual',
            'title' => trim((string) ($actualProjectRow['project_name'] ?? '')) ?: 'Unnamed Project',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'province' => (string) ($row['province'] ?? ''),
            'municipality' => (string) ($row['municipality'] ?? ''),
            'barangay' => (string) ($row['barangay'] ?? ''),
            'purok' => trim((string) ($actualProjectRow['purok'] ?? '')),
            'classification' => trim((string) ($actualProjectRow['project_classification'] ?? '')),
            'project_type' => trim((string) ($actualProjectRow['project_type'] ?? '')),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'details' => [
                'project_name' => trim((string) ($actualProjectRow['project_name'] ?? '')),
                'classification' => trim((string) ($actualProjectRow['project_classification'] ?? '')),
                'project_type' => trim((string) ($actualProjectRow['project_type'] ?? '')),
                'actual_status' => $status,
                'actual_accomplishment' => trim((string) ($actualProjectRow['actual_accomplishment'] ?? '')),
                'land_area' => trim((string) ($actualProjectRow['land_area'] ?? '')),
                'land_ownership' => trim((string) ($actualProjectRow['land_ownership'] ?? '')),
                'fertilizer_enabled' => trim((string) ($actualProjectRow['fertilizer_enabled'] ?? '')),
                'fertilizer_ohn_quantity' => trim((string) ($actualProjectRow['fertilizer_ohn_quantity'] ?? '')),
                'fertilizer_concoction_quantity' => trim((string) ($actualProjectRow['fertilizer_concoction_quantity'] ?? '')),
                'fertilizer_vermicompost_quantity' => trim((string) ($actualProjectRow['fertilizer_vermicompost_quantity'] ?? '')),
                'aquatic_resource' => trim((string) ($actualProjectRow['aquatic_resource'] ?? '')),
                'aquatic_resource_quantity' => trim((string) ($actualProjectRow['aquatic_resource_quantity'] ?? '')),
                'drive_link' => trim((string) ($actualProjectRow['drive_link'] ?? '')),
                'photo_links' => $photoLinks,
            ],
        ];
    }
}

usort($markers, static function (array $left, array $right): int {
    $locationComparison = strcmp(
        implode('|', [$left['province'], $left['municipality'], $left['barangay'], $left['purok'], $left['title']]),
        implode('|', [$right['province'], $right['municipality'], $right['barangay'], $right['purok'], $right['title']])
    );

    if ($locationComparison !== 0) {
        return $locationComparison;
    }

    return strcmp($left['module_source'], $right['module_source']);
});

$summary = [
    'total_markers' => count($markers),
    'target_markers' => 0,
    'activity_markers' => count(array_filter($markers, static fn(array $marker): bool => $marker['module_source'] === 'program-activities')),
    'municipality_count' => count(array_unique(array_filter(array_map(static fn(array $marker): string => trim((string) ($marker['municipality'] ?? '')), $markers)))),
];

echo json_encode([
    'success' => true,
    'summary' => $summary,
    'markers' => array_values($markers),
]);
