<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/generator_history.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_require_method(['GET']);

$entries = crossmatch_template_list_outputs(
    $conn,
    (int) ($_SESSION['user_id'] ?? 0),
    (string) ($_SESSION['user_type'] ?? 'user')
);

if ($entries === []) {
    echo '<tr><td colspan="6" class="text-center text-muted py-4">No generated template files yet.</td></tr>';
    exit;
}

foreach ($entries as $entry) {
    $downloadUrl = 'template_generated_file.php?id=' . urlencode((string) $entry['token']);

    echo '<tr>';
    echo '<td>' . htmlspecialchars((string) $entry['filename'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $entry['municipality_name'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . number_format((int) ($entry['rows'] ?? 0)) . '</td>';
    echo '<td>' . htmlspecialchars((string) $entry['source_file'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($entry['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td class="text-right pr-3">';
    echo '<a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-sm btn-outline-success">';
    echo '<i class="fas fa-download mr-1"></i> Download';
    echo '</a>';
    echo '</td>';
    echo '</tr>';
}
