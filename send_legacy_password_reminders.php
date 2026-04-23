<?php

require_once __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

$sql = 'SELECT * FROM users WHERE deleted_at IS NULL AND must_change_password = 1 AND password_policy_version < ?';
$stmt = $conn->prepare($sql);

if (!$stmt) {
    fwrite(STDERR, "Failed to prepare legacy password query.\n");
    exit(1);
}

$version = password_policy_current_version();
$stmt->bind_param('i', $version);
$stmt->execute();
$users = db_stmt_fetch_all_assoc($stmt);

$processed = 0;
$sent = 0;
$skipped = 0;

foreach ($users as $user) {
    $processed++;

    if (!empty($user['password_strength_notified_at'])) {
        $skipped++;
        echo "[skip] {$user['email']} already notified\n";
        continue;
    }

    $resetState = password_policy_issue_reset_for_user($conn, $user, true);
    if ($resetState && isset($resetState['mail']['status']) && $resetState['mail']['status'] === 'success') {
        $sent++;
        echo "[sent] {$user['email']}\n";
        continue;
    }

    $message = $resetState['mail']['message'] ?? 'Unknown mailer failure.';
    echo "[fail] {$user['email']} - {$message}\n";
}

$stmt->close();
$conn->close();

echo "\nProcessed: {$processed}\n";
echo "Sent: {$sent}\n";
echo "Skipped: {$skipped}\n";
