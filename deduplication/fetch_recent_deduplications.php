<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';

auth_handle_page_access($conn);
auth_apply_security_headers();

// Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
  echo "<p style='color: red;'>Fiscal year not selected. Please go back and select.</p>";
  exit;
}

$year = (int) $_SESSION['selected_year'];
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = (string) ($_SESSION['user_type'] ?? '');

$stmt = $conn->prepare("
    SELECT
        j.id,
        j.file_name,
        j.status,
        j.rule,
        j.threshold,
        j.created_at,
        COALESCE(r.possible_duplicates, 0) AS possible_duplicates
    FROM deduplication_jobs j
    LEFT JOIN (
        SELECT job_id, COUNT(DISTINCT group_id) AS possible_duplicates
        FROM deduplication_results
        GROUP BY job_id
    ) r ON r.job_id = j.id
    WHERE YEAR(j.created_at) = ?
      AND (? = 'admin' OR j.user_id = ?)
    ORDER BY j.id DESC
");
$stmt->bind_param("isi", $year, $userType, $userId);
$stmt->execute();
$result = db_stmt_fetch_all_assoc($stmt);

if ($result !== []) {
    foreach ($result as $row) {
        $ruleLabel = ucfirst($row['rule']);
        $threshold = $row['threshold'] . '%';

        $possibilities = (int) ($row['possible_duplicates'] ?? 0);

        // Format created_at
        $date = new DateTime($row['created_at']);
        $createdAtFormatted = $date->format('F d, Y | h:i:s A');

        echo "<tr>
                <td>{$row['file_name']}</td>
                <td>{$ruleLabel}</td>
                <td>{$threshold}</td>
                <td>{$possibilities}</td>
                <td>{$createdAtFormatted}</td>
                <td>
                  <a href='results.php?job={$row['id']}' class='btn btn-sm btn-info'>
                    <i class='far fa-eye'></i>
                  </a>
                </td>
              </tr>";
    }
} else {
    // Create empty row with correct column count
    echo "<tr>
            <td>No recent deduplications found.</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>";
}
