<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

auth_handle_page_access($conn);
auth_apply_security_headers();

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Fiscal year not selected.',
    ]);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];

try {
    $stmt = $conn->prepare(
        "SELECT batch_id, COUNT(*) AS record_count
           FROM meb
          WHERE batch_id IS NOT NULL
            AND batch_id <> ''
            AND YEAR(time_stamp) = ?
          GROUP BY batch_id
          ORDER BY batch_id ASC"
    );

    if (!$stmt) {
        throw new RuntimeException('Failed to load batch IDs.');
    }

    $stmt->bind_param('i', $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    $batches = [];
    while ($row = $result->fetch_assoc()) {
        $batches[] = [
            'id' => (string) $row['batch_id'],
            'recordCount' => (int) $row['record_count'],
        ];
    }
    $stmt->close();

    echo json_encode([
        'year' => $selectedYear,
        'batches' => $batches,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Unable to load batch IDs.',
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
