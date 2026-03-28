<?php
session_start();
require_once __DIR__ . '/../config.php';

// Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
  echo "<p style='color: red;'>Fiscal year not selected. Please go back and select.</p>";
  exit;
}

$year = (int) $_SESSION['selected_year'];

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
    ORDER BY j.id DESC
");
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
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
