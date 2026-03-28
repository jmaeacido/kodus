<?php
session_start();

include('../config.php');

header('Content-Type: application/json');

if (!isset($_SESSION['selected_year'])) {
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected']);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];
$recordId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$direction = isset($_GET['direction']) ? strtolower(trim((string) $_GET['direction'])) : 'current';
$searchValue = trim((string) ($_GET['search'] ?? ''));

if ($recordId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid beneficiary record']);
    exit;
}

if (!in_array($direction, ['current', 'prev', 'next'], true)) {
    $direction = 'current';
}

function mebBaseWhere(string $searchValue): array
{
    $whereClause = " WHERE YEAR(time_stamp) = ?";
    $params = [(int) $_SESSION['selected_year']];
    $types = "i";

    if ($searchValue !== '') {
        $searchLike = '%' . $searchValue . '%';
        $whereClause .= " AND (
            lastName LIKE ? OR
            firstName LIKE ? OR
            middleName LIKE ? OR
            ext LIKE ? OR
            purok LIKE ? OR
            barangay LIKE ? OR
            lgu LIKE ? OR
            province LIKE ?
        )";

        for ($i = 0; $i < 8; $i++) {
            $params[] = $searchLike;
            $types .= "s";
        }
    }

    return [$whereClause, $params, $types];
}

function mebFetchSingle(mysqli $conn, string $sql, string $types, array $params): ?array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function mebOrderComparison(string $operator, array $row): array
{
    $fields = [
        'province',
        'lgu',
        'barangay',
        'lastName',
        'firstName',
        'middleName',
        'ext'
    ];

    $conditions = [];
    $params = [];
    $types = '';

    foreach ($fields as $index => $field) {
        $parts = [];

        for ($i = 0; $i < $index; $i++) {
            $parts[] = "IFNULL({$fields[$i]}, '') = ?";
            $params[] = (string) ($row[$fields[$i]] ?? '');
            $types .= 's';
        }

        $parts[] = "IFNULL({$field}, '') {$operator} ?";
        $params[] = (string) ($row[$field] ?? '');
        $types .= 's';

        $conditions[] = '(' . implode(' AND ', $parts) . ')';
    }

    $parts = [];
    foreach ($fields as $field) {
        $parts[] = "IFNULL({$field}, '') = ?";
        $params[] = (string) ($row[$field] ?? '');
        $types .= 's';
    }
    $parts[] = "id {$operator} ?";
    $params[] = (int) ($row['id'] ?? 0);
    $types .= 'i';
    $conditions[] = '(' . implode(' AND ', $parts) . ')';

    return ['(' . implode(' OR ', $conditions) . ')', $params, $types];
}

list($baseWhere, $baseParams, $baseTypes) = mebBaseWhere($searchValue);
$currentSql = "SELECT * FROM meb {$baseWhere} AND id = ? LIMIT 1";
$currentParams = $baseParams;
$currentParams[] = $recordId;
$currentTypes = $baseTypes . 'i';
$currentRow = mebFetchSingle($conn, $currentSql, $currentTypes, $currentParams);

if (!$currentRow) {
    echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
    exit;
}

$targetRow = $currentRow;

if ($direction === 'prev' || $direction === 'next') {
    $operator = $direction === 'prev' ? '<' : '>';
    $sortDirection = $direction === 'prev' ? 'DESC' : 'ASC';
    list($compareClause, $compareParams, $compareTypes) = mebOrderComparison($operator, $currentRow);

    $neighborSql = "
        SELECT *
        FROM meb
        {$baseWhere} AND {$compareClause}
        ORDER BY province {$sortDirection},
                 lgu {$sortDirection},
                 barangay {$sortDirection},
                 lastName {$sortDirection},
                 firstName {$sortDirection},
                 middleName {$sortDirection},
                 ext {$sortDirection},
                 id {$sortDirection}
        LIMIT 1
    ";

    $neighborParams = array_merge($baseParams, $compareParams);
    $neighborTypes = $baseTypes . $compareTypes;
    $neighborRow = mebFetchSingle($conn, $neighborSql, $neighborTypes, $neighborParams);

    if ($neighborRow) {
        $targetRow = $neighborRow;
    }
}

list($positionClause, $positionParams, $positionTypes) = mebOrderComparison('<', $targetRow);

$positionSql = "SELECT COUNT(*) AS total_before FROM meb {$baseWhere} AND {$positionClause}";
$positionStmt = $conn->prepare($positionSql);
$positionAllParams = array_merge($baseParams, $positionParams);
$positionAllTypes = $baseTypes . $positionTypes;
$positionStmt->bind_param($positionAllTypes, ...$positionAllParams);
$positionStmt->execute();
$positionResult = $positionStmt->get_result();
$positionRow = $positionResult->fetch_assoc();
$positionStmt->close();

$totalSql = "SELECT COUNT(*) AS total_records FROM meb {$baseWhere}";
$totalStmt = $conn->prepare($totalSql);
$totalStmt->bind_param($baseTypes, ...$baseParams);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalStmt->close();

$position = ((int) ($positionRow['total_before'] ?? 0)) + 1;
$totalRecords = (int) ($totalRow['total_records'] ?? 0);

echo json_encode([
    'success' => true,
    'row' => $targetRow,
    'position' => $position,
    'total' => $totalRecords
]);

$conn->close();
?>
