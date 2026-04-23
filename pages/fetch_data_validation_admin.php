<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
include('../config.php');
require_once '../project_targets_helpers.php';

header('Content-Type: application/json');

auth_handle_page_access($conn);
auth_apply_security_headers();

if (!isset($_SESSION['selected_year'])) {
    echo json_encode(["error" => "Fiscal year not selected"]);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["error" => "Access denied"]);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];
ensureProjectLawaBinhiTargets($conn);
$start = isset($_GET['start']) ? (int) $_GET['start'] : 0;
$limit = isset($_GET['length']) ? (int) $_GET['length'] : 10;
if ($limit === -1) {
    $limit = 10000;
}

$searchValue = trim($_GET['search']['value'] ?? '');
$columns = ['batch_numbers', 'province', 'municipality', 'barangay', 'target_beneficiaries', 'actual_beneficiaries', 'variance'];
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int) $_GET['order'][0]['column'] : 0;
$orderDir = isset($_GET['order'][0]['dir']) && strtoupper($_GET['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
$orderColumn = $columns[$orderColumnIndex] ?? 'province';
$orderByClause = "comparison.province ASC, comparison.municipality ASC, comparison.barangay ASC";

if (!in_array($orderColumn, ['province', 'municipality', 'barangay'], true)) {
    $orderByClause .= ", comparison.{$orderColumn} {$orderDir}";
}

$comparisonSql = "
    SELECT
        locations.province,
        locations.municipality,
        locations.barangay,
        COALESCE(actuals.batch_numbers, '') AS batch_numbers,
        COALESCE(targets.target_partner_beneficiaries, 0) AS target_beneficiaries,
        COALESCE(actuals.actual_beneficiaries, 0) AS actual_beneficiaries,
        COALESCE(actuals.actual_beneficiaries, 0) - COALESCE(targets.target_partner_beneficiaries, 0) AS variance,
        COALESCE(actuals.ids, '') AS ids
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
            GROUP_CONCAT(DISTINCT batch_id ORDER BY batch_id ASC SEPARATOR ', ') AS batch_numbers,
            COUNT(*) AS actual_beneficiaries,
            GROUP_CONCAT(id ORDER BY id ASC) AS ids
        FROM meb
        WHERE YEAR(time_stamp) = ?
        GROUP BY province, lgu, barangay
    ) AS actuals
        ON actuals.province = locations.province
       AND actuals.municipality = locations.municipality
       AND actuals.barangay = locations.barangay
";

$whereClause = '';
$params = [$selectedYear, $selectedYear, $selectedYear, $selectedYear];
$types = "iiii";

if ($searchValue !== '') {
    $whereClause = " WHERE comparison.batch_numbers LIKE ? OR comparison.province LIKE ? OR comparison.municipality LIKE ? OR comparison.barangay LIKE ?";
    $searchLike = "%{$searchValue}%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "ssss";
}

$sql = "
    SELECT
        province,
        lgu,
        barangay,
        COUNT(*) AS beneficiary_count,
        GROUP_CONCAT(id ORDER BY id ASC) AS ids,
        CASE
            WHEN SUM(CASE WHEN COALESCE(validation, '') <> '✓' THEN 1 ELSE 0 END) = 0 THEN 'validated'
            ELSE 'not_validated'
        END AS validation_status
    FROM meb
    {$whereClause}
    GROUP BY province, lgu, barangay
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT ?, ?
";

$sql = "
    SELECT *
    FROM ({$comparisonSql}) AS comparison
    {$whereClause}
    ORDER BY {$orderByClause}
    LIMIT ?, ?
";

$queryParams = $params;
$queryParams[] = $start;
$queryParams[] = $limit;
$queryTypes = $types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$result = db_stmt_fetch_all_assoc($stmt);

$data = [];
foreach ($result as $row) {
    $targetBeneficiaries = (int) ($row['target_beneficiaries'] ?? 0);
    $actualBeneficiaries = (int) ($row['actual_beneficiaries'] ?? 0);
    $variance = $actualBeneficiaries - $targetBeneficiaries;
    $ids = htmlspecialchars((string) ($row['ids'] ?? ''), ENT_QUOTES, 'UTF-8');
    $editUrl = $ids !== ''
        ? 'data-tracking-meb-edit.php?ids=' . rawurlencode((string) ($row['ids'] ?? '')) . '&return_to=' . rawurlencode('data-tracking-meb-validation')
        : '';

    if ($targetBeneficiaries <= 0 && $actualBeneficiaries > 0) {
        $badgeClass = 'badge-info';
        $badgeText = 'Unplanned Import';
    } elseif ($targetBeneficiaries <= 0) {
        $badgeClass = 'badge-secondary';
        $badgeText = 'No Target';
    } elseif ($actualBeneficiaries === 0) {
        $badgeClass = 'badge-secondary';
        $badgeText = 'No Import';
    } elseif ($actualBeneficiaries < $targetBeneficiaries) {
        $badgeClass = 'badge-warning';
        $badgeText = 'Partial';
    } elseif ($actualBeneficiaries === $targetBeneficiaries) {
        $badgeClass = 'badge-success';
        $badgeText = 'Validated';
    } else {
        $badgeClass = 'badge-danger';
        $badgeText = 'Over Target';
    }

    $data[] = [
        'batch_number' => $row['batch_numbers'] !== '' ? $row['batch_numbers'] : '<span class="text-muted">N/A</span>',
        'province' => $row['province'],
        'municipality' => $row['municipality'],
        'barangay' => $row['barangay'],
        'target_beneficiaries' => $targetBeneficiaries,
        'actual_beneficiaries' => $actualBeneficiaries,
        'variance' => $variance,
        'validation' => '<span class="badge ' . $badgeClass . '">' . $badgeText . '</span>',
        'action' => $editUrl !== ''
            ? '<a href="' . $editUrl . '" class="btn btn-sm btn-primary mr-1">Edit Rows</a>'
            : '<span class="text-muted">No imported rows</span>',
    ];
}
$stmt->close();

$sqlCount = "
    SELECT COUNT(*) AS total
    FROM ({$comparisonSql}) AS comparison
    {$whereClause}
";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$countRow = db_stmt_fetch_one_assoc($stmtCount);
$totalRows = (int) ($countRow['total'] ?? 0);
$stmtCount->close();

$stmtTotal = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM ({$comparisonSql}) AS comparison
");
$stmtTotal->bind_param("iiii", $selectedYear, $selectedYear, $selectedYear, $selectedYear);
$stmtTotal->execute();
$totalRow = db_stmt_fetch_one_assoc($stmtTotal);
$totalRecords = (int) ($totalRow['total'] ?? 0);
$stmtTotal->close();

echo json_encode([
    "draw" => isset($_GET['draw']) ? (int) $_GET['draw'] : 1,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalRows,
    "data" => $data,
]);

$conn->close();
