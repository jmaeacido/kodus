<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');

auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['GET']);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) && auth_restore_user_from_remember_me($conn)) {
    // Continue with the restored session for same-origin AJAX requests.
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.', 'items' => []]);
    exit;
}

$type = strtolower(trim((string) ($_GET['type'] ?? 'provinces')));
$parent = trim((string) ($_GET['parent'] ?? ''));
$province = trim((string) ($_GET['province'] ?? ''));
$items = [];

if ($type === 'provinces') {
    $stmt = $conn->prepare('SELECT DISTINCT province_name AS value, province_name AS label FROM provinces ORDER BY province_name ASC');
} elseif ($type === 'municipalities') {
    if ($parent === '') {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }
    $stmt = $conn->prepare('
        SELECT DISTINCT m.municipality_name AS value, m.municipality_name AS label
        FROM municipality m
        INNER JOIN provinces p
            ON p.id = m.province_id
        WHERE p.province_name = ?
        ORDER BY m.municipality_name ASC
    ');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load location options.', 'items' => []]);
        exit;
    }
    $stmt->bind_param('s', $parent);
} elseif ($type === 'barangays') {
    if ($parent === '') {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }
    if ($province !== '') {
        $stmt = $conn->prepare('
            SELECT DISTINCT b.brgy_name AS value, b.brgy_name AS label
            FROM barangay b
            INNER JOIN municipality m
                ON m.id = b.municipality_id
            INNER JOIN provinces p
                ON p.id = m.province_id
            WHERE m.municipality_name = ?
              AND p.province_name = ?
            ORDER BY b.brgy_name ASC
        ');
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not load location options.', 'items' => []]);
            exit;
        }
        $stmt->bind_param('ss', $parent, $province);
    } else {
        $stmt = $conn->prepare('
            SELECT DISTINCT b.brgy_name AS value, b.brgy_name AS label
            FROM barangay b
            INNER JOIN municipality m
                ON m.id = b.municipality_id
            WHERE m.municipality_name = ?
            ORDER BY b.brgy_name ASC
        ');
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not load location options.', 'items' => []]);
            exit;
        }
        $stmt->bind_param('s', $parent);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid location option type.', 'items' => []]);
    exit;
}

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load location options.', 'items' => []]);
    exit;
}

$stmt->execute();
$seenValues = [];
foreach (db_stmt_fetch_all_assoc($stmt) as $row) {
    $value = trim((string) ($row['value'] ?? ''));
    $dedupeKey = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    if ($value === '' || isset($seenValues[$dedupeKey])) {
        continue;
    }
    $seenValues[$dedupeKey] = true;
    $items[] = [
        'value' => $value,
        'label' => (string) ($row['label'] ?? $value),
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'items' => $items]);
