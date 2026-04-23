<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/jobs.php';

auth_handle_page_access($conn);
auth_apply_security_headers();

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userType = (string) ($_SESSION['user_type'] ?? '');
crossmatch_ensure_job_schema($conn);

$stmt = $conn->prepare("
    SELECT
        j.id,
        j.created_at,
        j.file1_name,
        j.file2_name,
        j.rule,
        j.threshold,
        COALESCE(r.possible_matches, 0) AS possible_matches
    FROM crossmatch_jobs j
    LEFT JOIN (
        SELECT
            job_id,
            SUM(
                CASE
                    WHEN COALESCE(JSON_LENGTH(candidates_json), 0) > 0 THEN 1
                    ELSE 0
                END
            ) AS possible_matches
        FROM crossmatch_results
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
        $rule = ucfirst((string) $row['rule']);
        $threshold = ((int) $row['threshold']) . '%';
        $possibilities = (int) ($row['possible_matches'] ?? 0);
        $date = new DateTime((string) $row['created_at']);
        $createdAtFormatted = $date->format('F d, Y | h:i:s A');

        echo "<tr>
                <td>" . (empty($row['file2_name']) ? "{$row['file1_name']}" : "{$row['file1_name']}<br>{$row['file2_name']}") . "</td>
                <td>" . (empty($row['file2_name']) ? 'KODUS DB vs File' : 'File vs File') . "</td>
                <td>{$rule}</td>
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
    echo "<tr>
            <td>No recent crossmatch jobs found.</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>";
}
