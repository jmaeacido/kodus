<?php
require 'config.php';
->query("DELETE FROM program_activity_metadata WHERE barangay='ZZ CODEX TEST BRGY 20260412'");
 = ->affected_rows;
->query("DELETE FROM project_lawa_binhi_targets WHERE barangay='ZZ CODEX TEST BRGY 20260412' AND fiscal_year=2026");
 = ->affected_rows;
->query("DELETE FROM users WHERE username='zz_codex_test_editor'");
 = ->affected_rows;
echo json_encode(['metadata_deleted' => , 'target_deleted' => , 'user_deleted' => ]);
?>
