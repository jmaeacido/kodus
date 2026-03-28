<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once __DIR__ . '/activity_metadata.php';
require_once '../project_targets_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
header('Content-Type: application/json');

ensureProgramActivityMetadata($conn);
ensureProjectLawaBinhiTargets($conn);

if (!isset($_SESSION['selected_year'])) {
    echo json_encode(['data' => [], 'error' => 'Fiscal year not selected']);
    exit;
}

$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
$selectedYear = (int) $_SESSION['selected_year'];
$data = [];

function formatDateRange($from, $to): string
{
    if (!$from) {
        return '';
    }

    $fromFormatted = date('M d, Y', strtotime($from));
    if ($to && $from !== $to) {
        $toFormatted = date('M d, Y', strtotime($to));
        return $fromFormatted . ' - ' . $toFormatted;
    }

    return $fromFormatted;
}

function resolveForumFrom(array $row, string $prefix): ?string
{
    $fromValue = $row[$prefix . '_from'] ?? null;
    if (!empty($fromValue)) {
        return $fromValue;
    }

    return $row[$prefix] ?? null;
}

function resolveForumTo(array $row, string $prefix): ?string
{
    $toValue = $row[$prefix . '_to'] ?? null;
    if (!empty($toValue)) {
        return $toValue;
    }

    return $row[$prefix] ?? null;
}

function normalizeProjectList(?string $projectNames): array
{
    return parseProjectTargetMultiValueCell($projectNames, false);
}

function formatStructuredDateSummary(?string $rawValue): string
{
    $rawValue = trim((string) $rawValue);
    if ($rawValue === '') {
        return '';
    }

    $entries = preg_split('/\|\|/', $rawValue) ?: [];
    $formatted = [];

    foreach ($entries as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }

        if (strpos($entry, '~') !== false) {
            [$startDate, $endDate] = array_pad(explode('~', $entry, 2), 2, '');
            $startDate = trim($startDate);
            $endDate = trim($endDate);
            if ($startDate === '') {
                continue;
            }

            $formatted[] = formatDateRange($startDate, $endDate !== '' ? $endDate : $startDate);
            continue;
        }

        $formatted[] = $entry;
    }

    return implode(', ', array_filter($formatted, static fn($value) => trim((string) $value) !== ''));
}

function formatBarangayDateSummary(?string $rawValue): string
{
    $rawValue = trim((string) $rawValue);
    if ($rawValue === '') {
        return '';
    }

    $entries = preg_split('/\|\|/', $rawValue) ?: [];
    $formatted = [];

    foreach ($entries as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }

        $parts = explode('::', $entry, 2);
        $barangay = trim($parts[0] ?? '');
        $dates = formatStructuredDateSummary($parts[1] ?? '');
        if ($barangay === '' || $dates === '') {
            continue;
        }

        $formatted[] = $barangay . ': ' . $dates;
    }

    return implode(' || ', $formatted);
}

function buildValidationSnapshot(int $targetBeneficiaries, int $actualBeneficiaries): array
{
    if ($targetBeneficiaries <= 0 && $actualBeneficiaries > 0) {
        return ['label' => 'Unplanned Import', 'class' => 'info'];
    }

    if ($targetBeneficiaries <= 0) {
        return ['label' => 'No Target', 'class' => 'secondary'];
    }

    if ($actualBeneficiaries === 0) {
        return ['label' => 'No Import', 'class' => 'secondary'];
    }

    if ($actualBeneficiaries < $targetBeneficiaries) {
        return ['label' => 'Partial', 'class' => 'warning'];
    }

    if ($actualBeneficiaries === $targetBeneficiaries) {
        return ['label' => 'Validated', 'class' => 'success'];
    }

    return ['label' => 'Over Target', 'class' => 'danger'];
}

function buildCompletenessBadge(array $row): array
{
    $checks = [
        !empty($row['plgu_from']),
        !empty($row['mlgu_from']),
        !empty($row['blgu_from']),
        (int) ($row['target_barangay_count'] ?? 0) > 0,
        (int) ($row['target_beneficiaries'] ?? 0) > 0,
        (int) ($row['lawa_target_beneficiaries'] ?? 0) > 0,
        (int) ($row['binhi_target_beneficiaries'] ?? 0) > 0,
        (int) ($row['with_projects'] ?? 0) > 0,
    ];

    $score = (int) round((array_sum(array_map(static fn($v) => $v ? 1 : 0, $checks)) / count($checks)) * 100);
    if ($score >= 80) {
        return ['label' => 'Ready', 'class' => 'success', 'score' => $score];
    }
    if ($score >= 40) {
        return ['label' => 'In Progress', 'class' => 'warning', 'score' => $score];
    }

    return ['label' => 'Needs Update', 'class' => 'secondary', 'score' => $score];
}

$sql = "
    SELECT
        locations.province,
        locations.municipality,
        COALESCE(SUM(targets.lawa_target), 0) AS lawa_target_beneficiaries,
        COALESCE(SUM(targets.binhi_target), 0) AS binhi_target_beneficiaries,
        COALESCE(SUM(targets.target_partner_beneficiaries), 0) AS target_beneficiaries,
        COALESCE(SUM(actuals.actual_beneficiaries), 0) AS actual_beneficiaries,
        SUM(CASE WHEN COALESCE(targets.target_partner_beneficiaries, 0) > 0 THEN 1 ELSE 0 END) AS target_barangay_count,
        SUM(CASE WHEN COALESCE(actuals.actual_beneficiaries, 0) > 0 THEN 1 ELSE 0 END) AS actual_barangay_count,
        SUM(CASE WHEN (metadata.project_names IS NOT NULL AND TRIM(metadata.project_names) <> '') OR (targets.project_names IS NOT NULL AND TRIM(targets.project_names) <> '') THEN 1 ELSE 0 END) AS with_projects,
        GROUP_CONCAT(
            CONCAT(
                locations.barangay,
                ' (T:',
                COALESCE(targets.target_partner_beneficiaries, 0),
                ', A:',
                COALESCE(actuals.actual_beneficiaries, 0),
                ')'
            )
            ORDER BY locations.barangay
            SEPARATOR '||'
        ) AS barangays_with_beneficiaries,
        GROUP_CONCAT(targets.puroks SEPARATOR '||') AS all_puroks,
        GROUP_CONCAT(targets.project_names SEPARATOR '||') AS all_target_project_names,
        GROUP_CONCAT(targets.project_classifications SEPARATOR '||') AS all_target_project_classifications,
        GROUP_CONCAT(metadata.project_names SEPARATOR '||') AS all_project_names,
        MIN(COALESCE(metadata.plgu_forum_from, metadata.plgu_forum)) AS plgu_from,
        MAX(COALESCE(metadata.plgu_forum_to, metadata.plgu_forum)) AS plgu_to,
        MIN(COALESCE(metadata.mlgu_forum_from, metadata.mlgu_forum)) AS mlgu_from,
        MAX(COALESCE(metadata.mlgu_forum_to, metadata.mlgu_forum)) AS mlgu_to,
        MIN(COALESCE(metadata.blgu_forum_from, metadata.blgu_forum)) AS blgu_from,
        MAX(COALESCE(metadata.blgu_forum_to, metadata.blgu_forum)) AS blgu_to,
        GROUP_CONCAT(
            DISTINCT CASE
                WHEN metadata.site_validation IS NOT NULL AND TRIM(metadata.site_validation) <> ''
                THEN CONCAT(locations.barangay, '::', metadata.site_validation)
                ELSE NULL
            END
            ORDER BY locations.barangay
            SEPARATOR '||'
        ) AS site_validation,
        MIN(metadata.stage1_start_date) AS stage1_start,
        MAX(metadata.stage1_end_date) AS stage1_end,
        MIN(metadata.stage2_start_date) AS stage2_start,
        MAX(metadata.stage2_end_date) AS stage2_end,
        MIN(metadata.stage3_start_date) AS stage3_start,
        MAX(metadata.stage3_end_date) AS stage3_end,
        MAX(metadata.updated_at) AS last_updated
    FROM (
        SELECT province, municipality, barangay
        FROM project_lawa_binhi_targets
        WHERE fiscal_year = ?

        UNION

        SELECT province, lgu AS municipality, barangay
        FROM meb
        WHERE YEAR(time_stamp) = ?
        GROUP BY province, lgu, barangay
    ) AS locations
    LEFT JOIN project_lawa_binhi_targets AS targets
        ON targets.fiscal_year = ?
       AND targets.province = locations.province
       AND targets.municipality = locations.municipality
       AND targets.barangay = locations.barangay
    LEFT JOIN (
        SELECT
            province,
            lgu AS municipality,
            barangay,
            COUNT(*) AS actual_beneficiaries
        FROM meb
        WHERE YEAR(time_stamp) = ?
        GROUP BY province, lgu, barangay
    ) AS actuals
        ON actuals.province = locations.province
       AND actuals.municipality = locations.municipality
       AND actuals.barangay = locations.barangay
    LEFT JOIN program_activity_metadata AS metadata
        ON metadata.province = locations.province
       AND metadata.municipality = locations.municipality
       AND metadata.barangay = locations.barangay
    GROUP BY locations.province, locations.municipality
    ORDER BY locations.province, locations.municipality
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $selectedYear, $selectedYear, $selectedYear, $selectedYear);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $savedProjects = normalizeProjectList($row['all_project_names'] ?? '');
        $targetProjects = normalizeProjectList($row['all_target_project_names'] ?? '');
        $displayProjects = $savedProjects ?: $targetProjects;
        $targetPuroks = parseProjectTargetMultiValueCell($row['all_puroks'] ?? '');
        $targetProjectClassifications = parseProjectTargetMultiValueCell($row['all_target_project_classifications'] ?? '');
        $completeness = buildCompletenessBadge($row);
        $validation = buildValidationSnapshot((int) ($row['target_beneficiaries'] ?? 0), (int) ($row['actual_beneficiaries'] ?? 0));
        $detailsButton = '<button class="btn btn-primary btn-sm details-btn" title="View details" aria-label="View details"><i class="nav-icon fas fa-eye"></i></button>';
        $editButton = $isAdmin ? '<button class="btn btn-warning btn-sm edit-btn" title="Edit" aria-label="Edit"><i class="nav-icon fas fa-pen"></i></button>' : '';

        $data[] = [
            'action' => '<span class="kodus-row-actions">' . $detailsButton . $editButton . '</span>',
            'province' => $row['province'],
            'municipality' => $row['municipality'],
            'lawa_target_beneficiaries' => (int) $row['lawa_target_beneficiaries'],
            'binhi_target_beneficiaries' => (int) $row['binhi_target_beneficiaries'],
            'target_partner_beneficiaries' => (int) $row['target_beneficiaries'],
            'actual_partner_beneficiaries' => (int) $row['actual_beneficiaries'],
            'variance_partner_beneficiaries' => (int) $row['actual_beneficiaries'] - (int) $row['target_beneficiaries'],
            'amount' => number_format((int) $row['target_beneficiaries'] * 8700, 2, '.', ','),
            'plgu_forum' => formatDateRange(resolveForumFrom($row, 'plgu'), resolveForumTo($row, 'plgu')),
            'mlgu_forum' => formatDateRange(resolveForumFrom($row, 'mlgu'), resolveForumTo($row, 'mlgu')),
            'blgu_forum' => formatDateRange(resolveForumFrom($row, 'blgu'), resolveForumTo($row, 'blgu')),
            'site_validation' => formatBarangayDateSummary($row['site_validation'] ?? ''),
            'stage1_phase' => formatDateRange($row['stage1_start'] ?? null, $row['stage1_end'] ?? null),
            'stage2_phase' => formatDateRange($row['stage2_start'] ?? null, $row['stage2_end'] ?? null),
            'stage3_phase' => formatDateRange($row['stage3_start'] ?? null, $row['stage3_end'] ?? null),
            'no_of_barangays' => (int) $row['target_barangay_count'],
            'actual_barangay_count' => (int) $row['actual_barangay_count'],
            'barangays_and_beneficiaries' => $row['barangays_with_beneficiaries'] ?? '',
            'target_puroks' => implode('||', $targetPuroks),
            'target_project_names' => implode('||', $targetProjects),
            'target_project_classifications' => implode('||', $targetProjectClassifications),
            'project_names' => implode(', ', $displayProjects),
            'readiness' => '<span class="badge badge-' . $completeness['class'] . '">' . $completeness['label'] . ' (' . $completeness['score'] . '%)</span>',
            'validation_snapshot' => '<span class="badge badge-' . $validation['class'] . '">' . $validation['label'] . '</span>',
            'project_count' => count($displayProjects),
            'last_updated' => !empty($row['last_updated']) ? date('M d, Y h:i A', strtotime($row['last_updated'])) : '',
        ];
    }

    echo json_encode(['data' => $data]);
} else {
    echo json_encode(['data' => [], 'error' => $conn->error]);
}

$stmt->close();

