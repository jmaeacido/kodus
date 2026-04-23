<?php
require 'config.php';
 = ->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='program_activity_metadata' AND COLUMN_NAME='fiscal_year'");
 =  && ->num_rows > 0;
 = ->query("SELECT fiscal_year, province, municipality, barangay, work_accomplishment_report_status FROM program_activity_metadata WHERE barangay='ZZ CODEX TEST BRGY 20260412'");
 = [];
if () {
    while ( = ->fetch_assoc()) {
        [] = ;
    }
}
echo json_encode(['hasFiscalYear' => , 'rows' => ]);
?>
