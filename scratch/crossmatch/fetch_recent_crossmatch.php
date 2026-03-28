<?php
session_start();
require_once __DIR__ . '/../config.php';

//Ensure fiscal year is selected
if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color: red;'>Fiscal year not selected. Please go back and select.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];

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
    ORDER BY j.id DESC
");
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $rule = ucfirst($row['rule']);
        $threshold = $row['threshold'] . '%';

        $possibilities = (int) ($row['possible_matches'] ?? 0);

        $date = new DateTime($row['created_at']);
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
    // Create empty row with correct column count
    echo "<tr>
            <td>No recent deduplications found.</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>";
}
