<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/fund_monitoring_helpers.php';
include('config.php');

auth_handle_page_access($conn);
auth_apply_security_headers();
header('Content-Type: application/json');

// Make sure a fiscal year was selected
if (!isset($_SESSION['selected_year'])) {
    echo json_encode(["error" => "Fiscal year not selected"]);
    exit;
}

$year = (int) $_SESSION['selected_year'];
$canViewOperations = auth_can_view_operations();

// SQL with time_stamp filter
$sql = "SELECT
            COUNT(lastName) AS beneficiary_count,
            COUNT(DISTINCT CONCAT(barangay, lgu, province)) AS barangay_count,
            COUNT(DISTINCT CONCAT(lgu, province)) AS municipality_count,
            COUNT(DISTINCT province) AS province_count,
            SUM(CASE WHEN sex = 'FEMALE' THEN 1 ELSE 0 END) AS female_count, 
            SUM(CASE WHEN sex = 'MALE' THEN 1 ELSE 0 END) AS male_count,
            SUM(CASE WHEN nhts1 = '✓' THEN 1 ELSE 0 END) AS nhts1_count,
            SUM(CASE WHEN nhts2 = '✓' THEN 1 ELSE 0 END) AS nhts2_count,
            SUM(CASE WHEN fourPs IN ('✓', 'M', 'G', 'âœ“') THEN 1 ELSE 0 END) AS fourPs_count,
            SUM(CASE WHEN F = '✓' THEN 1 ELSE 0 END) AS farmer_count,
            SUM(CASE WHEN FF = '✓' THEN 1 ELSE 0 END) AS fisherfolk_count,
            SUM(CASE WHEN `IS` = '✓' THEN 1 ELSE 0 END) AS is_count,
            SUM(CASE WHEN IP = '✓' THEN 1 ELSE 0 END) AS ip_count,
            SUM(CASE WHEN SC = '✓' THEN 1 ELSE 0 END) AS sc_count,
            SUM(CASE WHEN SP = '✓' THEN 1 ELSE 0 END) AS sp_count,
            SUM(CASE WHEN LW = '✓' THEN 1 ELSE 0 END) AS lw_count,
            SUM(CASE WHEN PW = '✓' THEN 1 ELSE 0 END) AS pw_count,
            SUM(CASE WHEN PWD REGEXP '^[A-Z]$' THEN 1 ELSE 0 END) AS pwd_count,
            SUM(CASE WHEN OSY = '✓' THEN 1 ELSE 0 END) AS osy_count,
            SUM(CASE WHEN FR = '✓' THEN 1 ELSE 0 END) AS fr_count,
            SUM(CASE WHEN ybDs = '✓' THEN 1 ELSE 0 END) AS ybDs_count,
            SUM(CASE WHEN lgbtqia = '✓' THEN 1 ELSE 0 END) AS lgbtqia_count,
            SUM(CASE WHEN sex = 'FEMALE' AND nhts1 = '✓' THEN 1 ELSE 0 END) AS female_nhts1_count,
            SUM(CASE WHEN sex = 'MALE' AND nhts1 = '✓' THEN 1 ELSE 0 END) AS male_nhts1_count,
            SUM(CASE WHEN sex = 'FEMALE' AND nhts2 = '✓' THEN 1 ELSE 0 END) AS female_nhts2_count,
            SUM(CASE WHEN sex = 'MALE' AND nhts2 = '✓' THEN 1 ELSE 0 END) AS male_nhts2_count,
            SUM(CASE WHEN sex = 'FEMALE' AND F = '✓' THEN 1 ELSE 0 END) AS female_farmer_count,
            SUM(CASE WHEN sex = 'MALE' AND F = '✓' THEN 1 ELSE 0 END) AS male_farmer_count
        FROM meb
        WHERE YEAR(time_stamp) = ?";

// Prepare & execute
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();

$row = db_stmt_fetch_one_assoc($stmt);

if ($row) {
    foreach ($row as $key => $value) {
        $row[$key] = (int) $value;
    }

    if ($canViewOperations) {
        $fundSummary = [
            'items' => 0,
            'adjusted' => 0.0,
            'obligations' => 0.0,
            'disbursement' => 0.0,
            'utilization' => 0.0,
        ];

        $fundItems = fund_monitoring_list_items_with_entries($conn, $year);
        foreach ($fundItems as $fundItem) {
            $adjusted = (float) ($fundItem['adjusted_appropriation'] ?? 0);
            $fundSummary['items']++;
            $fundSummary['adjusted'] += $adjusted;

            foreach (($fundItem['monthly'] ?? []) as $monthlyValues) {
                $fundSummary['obligations'] += (float) ($monthlyValues['obligations'] ?? 0);
                $fundSummary['disbursement'] += (float) ($monthlyValues['disbursement'] ?? 0);
            }
        }

        if ($fundSummary['adjusted'] > 0) {
            $fundSummary['utilization'] = ($fundSummary['obligations'] / $fundSummary['adjusted']) * 100;
        }

        $row['fund_summary'] = $fundSummary;
    }

    echo json_encode($row);
} else {
    echo json_encode(["error" => "Query failed"]);
}

$stmt->close();
$conn->close();
?>
