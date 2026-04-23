<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

auth_handle_page_access($conn);
auth_apply_security_headers();

try {
    $sql = "SELECT DISTINCT batch_id FROM meb WHERE batch_id IS NOT NULL AND batch_id <> '' ORDER BY batch_id ASC";
    $result = $conn->query($sql);

    if ($result === false) {
        throw new RuntimeException('Failed to load batch IDs.');
    }

    $batches = [];
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row['batch_id'];
    }

    echo json_encode($batches);
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
