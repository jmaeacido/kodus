$ErrorActionPreference='Stop'
$base='https://crg-co2-24-9-05/kodus'
$jar='C:\laragon\www\kodus\tmp_codex_cookies.txt'
if (Test-Path $jar) { Remove-Item $jar -Force }

$selectResp = curl.exe -k -s -L -c $jar -b $jar -d 'year=2026' "$base/select_year"
if ($selectResp -notmatch 'name="csrf_token" value="([^"]+)"') { throw 'Could not find login CSRF token.' }
$csrf = $matches[1]

$loginResp = curl.exe -k -s -L -c $jar -b $jar -d "csrf_token=$csrf&username=zz_codex_test_editor&password=TempPass123!" "$base/login"
if ($loginResp -notmatch 'Workspace Overview|Dashboard') { throw 'Login did not reach dashboard.' }

$targetsPage = curl.exe -k -s -L -c $jar -b $jar "$base/implementation-status/program-targets"
if ($targetsPage -notmatch 'name="csrf_token" value="([^"]+)"') { throw 'Could not find app CSRF token.' }
$appCsrf = $matches[1]

$tempBarangay = 'ZZ CODEX TEST BRGY 20260412'
$tempProject1 = '<b>Codex Baseline Test</b>'
$createBody = @{
  csrf_token = $appCsrf
  province = 'AGUSAN DEL NORTE'
  municipality = 'BUENAVISTA'
  barangay = $tempBarangay
  capbuild_target = '1'
  community_action_plan_target = '0'
  target_partner_beneficiaries = '5'
  'entries[0][purok]' = 'PUROK 1'
  'entries[0][name]' = $tempProject1
  'entries[0][type]' = 'Vegetable'
  'entries[0][classification]' = 'BINHI'
  'entries[0][binhi_target_quantity]' = '3'
}
$createEncoded = ($createBody.GetEnumerator() | ForEach-Object { '{0}={1}' -f [uri]::EscapeDataString([string]$_.Key), [uri]::EscapeDataString([string]$_.Value) }) -join '&'
$createResp = curl.exe -k -s -c $jar -b $jar -H 'Content-Type: application/x-www-form-urlencoded' --data-raw $createEncoded "$base/implementation-status/save-project-target.php"

$targetsJson = curl.exe -k -s -c $jar -b $jar "$base/implementation-status/fetch-project-targets.php"
$targetsObj = $targetsJson | ConvertFrom-Json
$tempTarget = $targetsObj.data | Where-Object { $_.barangay -eq $tempBarangay } | Select-Object -First 1
if (-not $tempTarget) { throw 'Temp baseline target was not found after creation.' }

$tempProject2 = '<img src=x onerror=alert(1)> Edited Codex Test'
$editBody = @{
  csrf_token = $appCsrf
  id = [string]$tempTarget.id
  province = 'AGUSAN DEL NORTE'
  municipality = 'BUENAVISTA'
  barangay = $tempBarangay
  capbuild_target = '2'
  community_action_plan_target = '1'
  target_partner_beneficiaries = '8'
  'entries[0][purok]' = 'PUROK 1'
  'entries[0][name]' = $tempProject2
  'entries[0][type]' = 'Vegetable'
  'entries[0][classification]' = 'BINHI'
  'entries[0][binhi_target_quantity]' = '4'
}
$editEncoded = ($editBody.GetEnumerator() | ForEach-Object { '{0}={1}' -f [uri]::EscapeDataString([string]$_.Key), [uri]::EscapeDataString([string]$_.Value) }) -join '&'
$editResp = curl.exe -k -s -c $jar -b $jar -H 'Content-Type: application/x-www-form-urlencoded' --data-raw $editEncoded "$base/implementation-status/save-project-target.php"

$getProg = curl.exe -k -s -L -c $jar -b $jar "$base/implementation-status/get-program-activity.php?province=AGUSAN%20DEL%20NORTE&municipality=BUENAVISTA"
$getProgObj = $getProg | ConvertFrom-Json
$tempProgRow = $getProgObj.rows | Where-Object { $_.barangay -eq $tempBarangay } | Select-Object -First 1
if (-not $tempProgRow) { throw 'Temp barangay did not appear in Program Activities dataset.' }

$rows = @(@{
  barangay = $tempBarangay
  blgu_forum_from = ''
  blgu_forum_to = ''
  stage1_start_date = ''
  stage1_end_date = ''
  stage2_start_date = ''
  stage2_end_date = ''
  stage3_start_date = ''
  stage3_end_date = ''
  drmd_monitoring_from = ''
  drmd_monitoring_to = ''
  drmd_monitoring_participants = ''
  joint_post_monitoring_from = ''
  joint_post_monitoring_to = ''
  joint_post_monitoring_participants = ''
  payout_schedule_from = ''
  payout_schedule_to = ''
  actual_capbuild_accomplishment = 0
  actual_community_action_plan_accomplishment = 0
  coverage_puroks = @()
  coverage_project_names = @()
  coverage_project_classifications = @()
  coverage_project_types = @()
  coverage_aquatic_resources = @()
  coverage_aquatic_resource_quantities = @()
  coverage_actual_accomplishments = @()
  coverage_land_areas = @()
  coverage_land_ownerships = @()
  coverage_actual_statuses = @()
  fund_obligation_partner_beneficiaries = 0
  fund_disbursement_served_partner_beneficiaries = 0
  liquidation_date = ''
  last_day_project_implementation = ''
  check_issuance_date = ''
  work_accomplishment_report_status = 'Draft only'
  performance_rating_remarks = ''
  special_disbursing_officer = ''
  binhi_sites_established_target = 0
  binhi_sites_established_actual = 0
  binhi_facilities_added_target = 0
  binhi_facilities_added_actual = 0
  fertilizer_ohn_target = 0
  fertilizer_ohn_actual = 0
  fertilizer_concoction_target = 0
  fertilizer_concoction_actual = 0
  fertilizer_vermicompost_target = 0
  fertilizer_vermicompost_actual = 0
  area_land_utilized_target = 0
  site_validation = ''
}) | ConvertTo-Json -Compress -Depth 6
$saveImpBody = @{
  csrf_token = $appCsrf
  province = 'AGUSAN DEL NORTE'
  municipality = 'BUENAVISTA'
  plgu_from = ''
  plgu_to = ''
  mlgu_from = ''
  mlgu_to = ''
  rows = $rows
}
$saveImpEncoded = ($saveImpBody.GetEnumerator() | ForEach-Object { '{0}={1}' -f [uri]::EscapeDataString([string]$_.Key), [uri]::EscapeDataString([string]$_.Value) }) -join '&'
$saveImpResp = curl.exe -k -s -c $jar -b $jar -H 'Content-Type: application/x-www-form-urlencoded' --data-raw $saveImpEncoded "$base/implementation-status/save-imp-status.php"

$getProgAfter = curl.exe -k -s -L -c $jar -b $jar "$base/implementation-status/get-program-activity.php?province=AGUSAN%20DEL%20NORTE&municipality=BUENAVISTA"
$getProgAfterObj = $getProgAfter | ConvertFrom-Json
$tempProgAfter = $getProgAfterObj.rows | Where-Object { $_.barangay -eq $tempBarangay } | Select-Object -First 1

$schemaCheck = php -r "require 'config.php'; \$res = \$conn->query(\"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='program_activity_metadata' AND COLUMN_NAME='fiscal_year'\"); \$hasFiscalYear = \$res && \$res->num_rows > 0; \$rowRes = \$conn->query(\"SELECT fiscal_year, province, municipality, barangay, work_accomplishment_report_status FROM program_activity_metadata WHERE barangay='ZZ CODEX TEST BRGY 20260412'\"); \$rows = []; if (\$rowRes) { while (\$r = \$rowRes->fetch_assoc()) { \$rows[] = \$r; } } echo json_encode(['hasFiscalYear' => \$hasFiscalYear, 'rows' => \$rows]);"

$cleanup = php -r "require 'config.php'; \$conn->query(\"DELETE FROM program_activity_metadata WHERE barangay='ZZ CODEX TEST BRGY 20260412'\"); \$metaDeleted = \$conn->affected_rows; \$conn->query(\"DELETE FROM project_lawa_binhi_targets WHERE barangay='ZZ CODEX TEST BRGY 20260412' AND fiscal_year=2026\"); \$targetDeleted = \$conn->affected_rows; \$conn->query(\"DELETE FROM users WHERE username='zz_codex_test_editor'\"); \$userDeleted = \$conn->affected_rows; echo json_encode(['metadata_deleted'=>\$metaDeleted,'target_deleted'=>\$targetDeleted,'user_deleted'=>\$userDeleted]);"

$result = [ordered]@{
  login_ok = $true
  create_response = ($createResp | ConvertFrom-Json)
  edit_response = ($editResp | ConvertFrom-Json)
  created_target_id = $tempTarget.id
  created_target_project_names_display = $tempTarget.project_names_display
  save_imp_response = ($saveImpResp | ConvertFrom-Json)
  temp_program_row_after_save = $tempProgAfter
  schema_check = ($schemaCheck | ConvertFrom-Json)
  cleanup = ($cleanup | ConvertFrom-Json)
}
$result | ConvertTo-Json -Depth 8
