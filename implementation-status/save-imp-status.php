<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
require_once '../config.php';
require_once __DIR__ . '/activity_metadata.php';
require_once '../project_targets_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
security_enforce_same_origin();
security_require_method(['POST']);
security_require_csrf_token();

header('Content-Type: application/json');

if (!auth_can_manage_program_activities()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected.']);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];

$municipality = trim((string) ($_POST['municipality'] ?? ''));
$province = trim((string) ($_POST['province'] ?? ''));
$plguFrom = trim((string) ($_POST['plgu_from'] ?? ''));
$plguTo = trim((string) ($_POST['plgu_to'] ?? ''));
$mlguFrom = trim((string) ($_POST['mlgu_from'] ?? ''));
$mlguTo = trim((string) ($_POST['mlgu_to'] ?? ''));
$rows = json_decode($_POST['rows'] ?? '[]', true);

if ($municipality === '' || $province === '' || !is_array($rows) || empty($rows)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

if (!auth_can_edit_implementation_province($conn, $province)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Editors can only edit implementation activity records in their assigned province.']);
    exit;
}

$forumRanges = [
    'PLGU Forum' => [$plguFrom, $plguTo],
    'MLGU Forum' => [$mlguFrom, $mlguTo],
];

foreach ($forumRanges as $label => [$fromDate, $toDate]) {
    $fromDate = trim($fromDate);
    $toDate = trim($toDate);

    if ($fromDate === '' && $toDate === '') {
        continue;
    }

    if ($fromDate === '' || $toDate === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $label . ' needs both From and To dates when one of them is provided.']);
        exit;
    }

    if ($fromDate > $toDate) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $label . ' From date cannot be later than the To date.']);
        exit;
    }
}

$plguFrom = $plguFrom !== '' ? $plguFrom : null;
$plguTo = $plguTo !== '' ? $plguTo : null;
$mlguFrom = $mlguFrom !== '' ? $mlguFrom : null;
$mlguTo = $mlguTo !== '' ? $mlguTo : null;

ensureProgramActivityMetadata($conn, $selectedYear);
ensureProjectLawaBinhiTargets($conn);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function normalizeNonNegativeDecimal($value): float
{
    $normalized = preg_replace('/[^\d.-]/', '', (string) $value);
    if ($normalized === '' || !is_numeric($normalized)) {
        return 0.0;
    }

    $parsed = (float) $normalized;
    return $parsed >= 0 ? $parsed : 0.0;
}

function normalizeCoordinateDecimalString($value): string
{
    $normalized = preg_replace('/\s+/', '', trim((string) $value));
    if ($normalized === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
        return '';
    }

    return $normalized;
}

function splitCoordinatePair($value): array
{
    $raw = trim((string) $value);
    if ($raw === '' || strpos($raw, ',') === false) {
        return ['', ''];
    }

    [$latitude, $longitude] = array_map('trim', explode(',', $raw, 2));
    return [
        normalizeCoordinateDecimalString($latitude),
        normalizeCoordinateDecimalString($longitude),
    ];
}

function generateActualProjectId(): string
{
    try {
        return 'pa-' . bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        return 'pa-' . uniqid('', true);
    }
}

try {
    $stmt = $conn->prepare("
    INSERT INTO program_activity_metadata (
        fiscal_year,
        province,
        municipality,
        barangay,
        plgu_forum,
        mlgu_forum,
        blgu_forum,
        plgu_forum_from,
        plgu_forum_to,
        mlgu_forum_from,
        mlgu_forum_to,
        blgu_forum_from,
        blgu_forum_to,
        site_validation,
        stage1_start_date,
        stage1_end_date,
        stage2_start_date,
        stage2_end_date,
        stage3_start_date,
        stage3_end_date,
        drmd_monitoring_from,
        drmd_monitoring_to,
        drmd_monitoring_participants,
        joint_post_monitoring_from,
        joint_post_monitoring_to,
        joint_post_monitoring_participants,
        payout_schedule_from,
        payout_schedule_to,
        actual_lawa_accomplishment,
        actual_binhi_accomplishment,
        actual_capbuild_accomplishment,
        actual_community_action_plan_accomplishment,
        fund_obligation_partner_beneficiaries,
        fund_disbursement_served_partner_beneficiaries,
        liquidation_date,
        last_day_project_implementation,
        check_issuance_date,
        work_accomplishment_report_status,
        performance_rating_remarks,
        special_disbursing_officer,
        binhi_sites_established_target,
        binhi_sites_established_actual,
        binhi_facilities_added_target,
        binhi_facilities_added_actual,
        fertilizer_ohn_target,
        fertilizer_ohn_actual,
        fertilizer_concoction_target,
        fertilizer_concoction_actual,
        fertilizer_vermicompost_target,
        fertilizer_vermicompost_actual,
        area_land_utilized_target
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        plgu_forum = VALUES(plgu_forum),
        mlgu_forum = VALUES(mlgu_forum),
        blgu_forum = VALUES(blgu_forum),
        plgu_forum_from = VALUES(plgu_forum_from),
        plgu_forum_to = VALUES(plgu_forum_to),
        mlgu_forum_from = VALUES(mlgu_forum_from),
        mlgu_forum_to = VALUES(mlgu_forum_to),
        blgu_forum_from = VALUES(blgu_forum_from),
        blgu_forum_to = VALUES(blgu_forum_to),
        site_validation = VALUES(site_validation),
        stage1_start_date = VALUES(stage1_start_date),
        stage1_end_date = VALUES(stage1_end_date),
        stage2_start_date = VALUES(stage2_start_date),
        stage2_end_date = VALUES(stage2_end_date),
        stage3_start_date = VALUES(stage3_start_date),
        stage3_end_date = VALUES(stage3_end_date),
        drmd_monitoring_from = VALUES(drmd_monitoring_from),
        drmd_monitoring_to = VALUES(drmd_monitoring_to),
        drmd_monitoring_participants = VALUES(drmd_monitoring_participants),
        joint_post_monitoring_from = VALUES(joint_post_monitoring_from),
        joint_post_monitoring_to = VALUES(joint_post_monitoring_to),
        joint_post_monitoring_participants = VALUES(joint_post_monitoring_participants),
        payout_schedule_from = VALUES(payout_schedule_from),
        payout_schedule_to = VALUES(payout_schedule_to),
        actual_lawa_accomplishment = VALUES(actual_lawa_accomplishment),
        actual_binhi_accomplishment = VALUES(actual_binhi_accomplishment),
        actual_capbuild_accomplishment = VALUES(actual_capbuild_accomplishment),
        actual_community_action_plan_accomplishment = VALUES(actual_community_action_plan_accomplishment),
        fund_obligation_partner_beneficiaries = VALUES(fund_obligation_partner_beneficiaries),
        fund_disbursement_served_partner_beneficiaries = VALUES(fund_disbursement_served_partner_beneficiaries),
        liquidation_date = VALUES(liquidation_date),
        last_day_project_implementation = VALUES(last_day_project_implementation),
        check_issuance_date = VALUES(check_issuance_date),
        work_accomplishment_report_status = VALUES(work_accomplishment_report_status),
        performance_rating_remarks = VALUES(performance_rating_remarks),
        special_disbursing_officer = VALUES(special_disbursing_officer),
        binhi_sites_established_target = VALUES(binhi_sites_established_target),
        binhi_sites_established_actual = VALUES(binhi_sites_established_actual),
        binhi_facilities_added_target = VALUES(binhi_facilities_added_target),
        binhi_facilities_added_actual = VALUES(binhi_facilities_added_actual),
        fertilizer_ohn_target = VALUES(fertilizer_ohn_target),
        fertilizer_ohn_actual = VALUES(fertilizer_ohn_actual),
        fertilizer_concoction_target = VALUES(fertilizer_concoction_target),
        fertilizer_concoction_actual = VALUES(fertilizer_concoction_actual),
        fertilizer_vermicompost_target = VALUES(fertilizer_vermicompost_target),
        fertilizer_vermicompost_actual = VALUES(fertilizer_vermicompost_actual),
        area_land_utilized_target = VALUES(area_land_utilized_target),
        updated_at = CURRENT_TIMESTAMP
");
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare program activity changes right now.',
        'debug' => $exception->getMessage(),
    ]);
    exit;
}

$targetLookupStmt = $conn->prepare("
    SELECT GROUP_CONCAT(pte.project_name ORDER BY pte.sort_order SEPARATOR '||') AS project_names
    FROM project_lawa_binhi_targets plt
    LEFT JOIN project_target_entries pte
        ON pte.target_id = plt.id
    WHERE plt.fiscal_year = ?
      AND plt.province = ?
      AND plt.municipality = ?
      AND plt.barangay = ?
");

foreach ($rows as $row) {
    $barangay = trim((string) ($row['barangay'] ?? ''));

    if ($barangay === '') {
        continue;
    }

    $normalizedProvince = normalizeProjectTargetLocation($province);
    $normalizedMunicipality = normalizeProjectTargetLocation($municipality);
    $normalizedBarangay = normalizeProjectTargetLocation($barangay);
    $stage1Start = trim((string) ($row['stage1_start_date'] ?? ''));
    $stage1End = trim((string) ($row['stage1_end_date'] ?? ''));
    $stage2Start = trim((string) ($row['stage2_start_date'] ?? ''));
    $stage2End = trim((string) ($row['stage2_end_date'] ?? ''));
    $stage3Start = trim((string) ($row['stage3_start_date'] ?? ''));
    $stage3End = trim((string) ($row['stage3_end_date'] ?? ''));
    $blguFrom = trim((string) ($row['blgu_forum_from'] ?? ''));
    $blguTo = trim((string) ($row['blgu_forum_to'] ?? ''));
    $drmdMonitoringFrom = trim((string) ($row['drmd_monitoring_from'] ?? ''));
    $drmdMonitoringTo = trim((string) ($row['drmd_monitoring_to'] ?? ''));
    $drmdMonitoringParticipants = preg_replace('/\s+/', ' ', trim((string) ($row['drmd_monitoring_participants'] ?? '')));
    $jointPostMonitoringFrom = trim((string) ($row['joint_post_monitoring_from'] ?? ''));
    $jointPostMonitoringTo = trim((string) ($row['joint_post_monitoring_to'] ?? ''));
    $jointPostMonitoringParticipants = preg_replace('/\s+/', ' ', trim((string) ($row['joint_post_monitoring_participants'] ?? '')));
    $payoutScheduleFrom = trim((string) ($row['payout_schedule_from'] ?? ''));
    $payoutScheduleTo = trim((string) ($row['payout_schedule_to'] ?? ''));
    $actualLawaAccomplishment = 0;
    $actualBinhiAccomplishment = 0;
    $actualCapbuildAccomplishment = isset($row['actual_capbuild_accomplishment']) ? (int) $row['actual_capbuild_accomplishment'] : 0;
    $actualCommunityActionPlanAccomplishment = isset($row['actual_community_action_plan_accomplishment']) ? (int) $row['actual_community_action_plan_accomplishment'] : 0;
    $coveragePuroksInput = is_array($row['coverage_puroks'] ?? null) ? $row['coverage_puroks'] : [];
    $actualProjectIdsInput = is_array($row['actual_project_ids'] ?? null) ? $row['actual_project_ids'] : [];
    $coverageEntryIdsInput = is_array($row['coverage_entry_ids'] ?? null) ? $row['coverage_entry_ids'] : [];
    $targetProjectRowIdsInput = is_array($row['target_project_row_ids'] ?? null) ? $row['target_project_row_ids'] : [];
    $coverageCoordinatesInput = is_array($row['coverage_coordinates'] ?? null) ? $row['coverage_coordinates'] : [];
    $coverageLatitudesInput = is_array($row['coverage_latitudes'] ?? null) ? $row['coverage_latitudes'] : [];
    $coverageLongitudesInput = is_array($row['coverage_longitudes'] ?? null) ? $row['coverage_longitudes'] : [];
    $coverageProjectNamesInput = is_array($row['coverage_project_names'] ?? null) ? $row['coverage_project_names'] : [];
    $coverageProjectClassificationsInput = is_array($row['coverage_project_classifications'] ?? null) ? $row['coverage_project_classifications'] : [];
    $coverageProjectTypesInput = is_array($row['coverage_project_types'] ?? null) ? $row['coverage_project_types'] : [];
    $coverageFertilizerEnabledFlagsInput = is_array($row['coverage_fertilizer_enabled_flags'] ?? null) ? $row['coverage_fertilizer_enabled_flags'] : [];
    $coverageFertilizerOhnQuantitiesInput = is_array($row['coverage_fertilizer_ohn_quantities'] ?? null) ? $row['coverage_fertilizer_ohn_quantities'] : [];
    $coverageFertilizerConcoctionQuantitiesInput = is_array($row['coverage_fertilizer_concoction_quantities'] ?? null) ? $row['coverage_fertilizer_concoction_quantities'] : [];
    $coverageFertilizerVermicompostQuantitiesInput = is_array($row['coverage_fertilizer_vermicompost_quantities'] ?? null) ? $row['coverage_fertilizer_vermicompost_quantities'] : [];
    $coverageAquaticResourcesInput = is_array($row['coverage_aquatic_resources'] ?? null) ? $row['coverage_aquatic_resources'] : [];
    $coverageAquaticResourceQuantitiesInput = is_array($row['coverage_aquatic_resource_quantities'] ?? null) ? $row['coverage_aquatic_resource_quantities'] : [];
    $coverageActualAccomplishmentsInput = is_array($row['coverage_actual_accomplishments'] ?? null) ? $row['coverage_actual_accomplishments'] : [];
    $coverageLandAreasInput = is_array($row['coverage_land_areas'] ?? null) ? $row['coverage_land_areas'] : [];
    $coverageLandOwnershipsInput = is_array($row['coverage_land_ownerships'] ?? null) ? $row['coverage_land_ownerships'] : [];
    $coverageDriveLinksInput = is_array($row['coverage_drive_links'] ?? null) ? $row['coverage_drive_links'] : [];
    $coverageStatusesInput = is_array($row['coverage_actual_statuses'] ?? null) ? $row['coverage_actual_statuses'] : [];
    $coverageCount = max(
        count($coveragePuroksInput),
        count($actualProjectIdsInput),
        count($coverageEntryIdsInput),
        count($targetProjectRowIdsInput),
        count($coverageCoordinatesInput),
        count($coverageLatitudesInput),
        count($coverageLongitudesInput),
        count($coverageProjectNamesInput),
        count($coverageProjectClassificationsInput),
        count($coverageProjectTypesInput),
        count($coverageFertilizerEnabledFlagsInput),
        count($coverageFertilizerOhnQuantitiesInput),
        count($coverageFertilizerConcoctionQuantitiesInput),
        count($coverageFertilizerVermicompostQuantitiesInput),
        count($coverageAquaticResourcesInput),
        count($coverageAquaticResourceQuantitiesInput),
        count($coverageActualAccomplishmentsInput),
        count($coverageLandAreasInput),
        count($coverageLandOwnershipsInput),
        count($coverageDriveLinksInput),
        count($coverageStatusesInput)
    );

    if (($blguFrom === '' && $blguTo !== '') || ($blguFrom !== '' && $blguTo === '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $barangay . ': BLGU Forum needs both From and To dates when one of them is provided.']);
        $stmt->close();
        $targetLookupStmt->close();
        exit;
    }

    if ($blguFrom !== '' && $blguTo !== '' && $blguFrom > $blguTo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $barangay . ': BLGU Forum From date cannot be later than the To date.']);
        $stmt->close();
        $targetLookupStmt->close();
        exit;
    }

    $normalizedCoveragePuroks = [];
    $normalizedActualProjectIds = [];
    $normalizedCoverageEntryIds = [];
    $normalizedTargetProjectRowIds = [];
    $normalizedCoverageLatitudes = [];
    $normalizedCoverageLongitudes = [];
    $normalizedCoverageProjectNames = [];
    $normalizedCoverageProjectClassifications = [];
    $normalizedCoverageProjectTypes = [];
    $normalizedCoverageFertilizerEnabledFlags = [];
    $normalizedCoverageFertilizerOhnQuantities = [];
    $normalizedCoverageFertilizerConcoctionQuantities = [];
    $normalizedCoverageFertilizerVermicompostQuantities = [];
    $normalizedCoverageAquaticResources = [];
    $normalizedCoverageAquaticResourceQuantities = [];
    $normalizedCoverageLandAreas = [];
    $normalizedCoverageLandOwnerships = [];
    $normalizedCoverageDriveLinks = [];
    $coverageActualAccomplishments = [];
    $coverageStatuses = [];

    for ($coverageIndex = 0; $coverageIndex < $coverageCount; $coverageIndex++) {
        $coveragePurok = preg_replace('/\s+/', ' ', trim((string) ($coveragePuroksInput[$coverageIndex] ?? '')));
        $actualProjectId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($actualProjectIdsInput[$coverageIndex] ?? ''));
        $coverageEntryId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($coverageEntryIdsInput[$coverageIndex] ?? ''));
        $targetProjectRowId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($targetProjectRowIdsInput[$coverageIndex] ?? ''));
        $coverageLegacyCoordinates = trim((string) ($coverageCoordinatesInput[$coverageIndex] ?? ''));
        $coverageLatitude = normalizeCoordinateDecimalString($coverageLatitudesInput[$coverageIndex] ?? '');
        $coverageLongitude = normalizeCoordinateDecimalString($coverageLongitudesInput[$coverageIndex] ?? '');
        $coverageProjectName = preg_replace('/\s+/', ' ', trim((string) ($coverageProjectNamesInput[$coverageIndex] ?? '')));
        $coverageClassification = strtoupper(trim((string) ($coverageProjectClassificationsInput[$coverageIndex] ?? '')));
        $coverageProjectType = preg_replace('/\s+/', ' ', trim((string) ($coverageProjectTypesInput[$coverageIndex] ?? '')));
        $coverageFertilizerEnabled = trim((string) ($coverageFertilizerEnabledFlagsInput[$coverageIndex] ?? ''));
        $coverageFertilizerOhnQuantity = trim((string) ($coverageFertilizerOhnQuantitiesInput[$coverageIndex] ?? ''));
        $coverageFertilizerConcoctionQuantity = trim((string) ($coverageFertilizerConcoctionQuantitiesInput[$coverageIndex] ?? ''));
        $coverageFertilizerVermicompostQuantity = trim((string) ($coverageFertilizerVermicompostQuantitiesInput[$coverageIndex] ?? ''));
        $coverageAquaticResource = preg_replace('/\s+/', ' ', trim((string) ($coverageAquaticResourcesInput[$coverageIndex] ?? '')));
        $coverageAquaticResourceQuantity = trim((string) ($coverageAquaticResourceQuantitiesInput[$coverageIndex] ?? ''));
        $coverageActualAccomplishment = trim((string) ($coverageActualAccomplishmentsInput[$coverageIndex] ?? ''));
        $coverageLandArea = preg_replace('/\s+/', ' ', trim((string) ($coverageLandAreasInput[$coverageIndex] ?? '')));
        $coverageLandOwnership = preg_replace('/\s+/', ' ', trim((string) ($coverageLandOwnershipsInput[$coverageIndex] ?? '')));
        $coverageDriveLink = trim((string) ($coverageDriveLinksInput[$coverageIndex] ?? ''));
        $coverageStatus = strtolower(trim((string) ($coverageStatusesInput[$coverageIndex] ?? 'pending')));
        if (!in_array($coverageStatus, ['pending', 'confirmed', 'custom'], true)) {
            $coverageStatus = 'pending';
        }

        if (($coverageLatitude === '' || $coverageLongitude === '') && $coverageLegacyCoordinates !== '') {
            [$legacyLatitude, $legacyLongitude] = splitCoordinatePair($coverageLegacyCoordinates);
            $coverageLatitude = $coverageLatitude !== '' ? $coverageLatitude : $legacyLatitude;
            $coverageLongitude = $coverageLongitude !== '' ? $coverageLongitude : $legacyLongitude;
        }

        if ($coveragePurok === '' && $coverageLatitude === '' && $coverageLongitude === '' && $coverageProjectName === '' && $coverageClassification === '' && $coverageProjectType === '' && $coverageFertilizerEnabled === '' && $coverageFertilizerOhnQuantity === '' && $coverageFertilizerConcoctionQuantity === '' && $coverageFertilizerVermicompostQuantity === '' && $coverageAquaticResource === '' && $coverageAquaticResourceQuantity === '' && $coverageActualAccomplishment === '' && $coverageLandArea === '' && $coverageLandOwnership === '' && $coverageDriveLink === '' && $coverageStatus === 'pending') {
            continue;
        }

        if ($coverageStatus === 'pending') {
            continue;
        }

        if ($coveragePurok === '' || $coverageProjectName === '' || $coverageClassification === '' || $coverageProjectType === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': each coverage accomplishment row needs a purok, project name, classification, and type.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        if (!in_array($coverageClassification, ['LAWA', 'BINHI'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': coverage classification must be LAWA or BINHI.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        $validLawaTypes = [
            'Rehabilitation of Water System Level I (Manual Pump)',
            'Rehabilitation of Water System Level II (Pipe Laying)',
            'Construction of Small Farm Reservoir',
            'Rehabilitation of Water System',
            'Diversification of Water Supply',
            'Rehabilitation of Fishpond',
            'Installation of Shallow Tube Wells (STWs)',
            'Construction of Water Reservoir',
            'Rehabilitation of Small Farm Reservoir',
            'Installation of Pitcher Pump (Shallow Well)',
            'Installation of Jetmatic Pump (Deep Well)',
            'Rehabilitation of Water Supply',
        ];
        $validBinhiTypes = [
            'Vegetable',
            'Crops (Banana, Corn, Rice)',
            'Disaster Resilient Crops (Taro, Sweet Potato)',
            'Fruit-Bearing Trees',
            'Tilapia (Fish pond)',
        ];
        $validTypes = $coverageClassification === 'LAWA' ? $validLawaTypes : $validBinhiTypes;
        if (!in_array($coverageProjectType, $validTypes, true)) {
            $coverageProjectType = preg_replace('/\s+/', ' ', $coverageProjectType);
            if ($coverageProjectType === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $barangay . ': each coverage row needs a valid type or a custom type value.']);
                $stmt->close();
                $targetLookupStmt->close();
                exit;
            }
        }

        if ($coverageClassification !== 'BINHI') {
            $coverageFertilizerEnabled = '';
            $coverageFertilizerOhnQuantity = '';
            $coverageFertilizerConcoctionQuantity = '';
            $coverageFertilizerVermicompostQuantity = '';
        } elseif ($coverageFertilizerEnabled !== '1') {
            $coverageFertilizerEnabled = $coverageFertilizerEnabled === '0' ? '0' : '';
        }

        foreach ([
            'Oriental Herbal Nutrients quantity' => $coverageFertilizerOhnQuantity,
            'Concoction/Vermitea quantity' => $coverageFertilizerConcoctionQuantity,
            'Vermicompost/Vermicast quantity' => $coverageFertilizerVermicompostQuantity,
        ] as $label => $value) {
            if ($value !== '' && !preg_match('/^\d+(?:\.\d+)?$/', $value)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' must be a non-negative number when provided.']);
                $stmt->close();
                $targetLookupStmt->close();
                exit;
            }
        }

        if ($coverageClassification === 'BINHI' && $coverageFertilizerEnabled === '1' && $coverageFertilizerOhnQuantity === '' && $coverageFertilizerConcoctionQuantity === '' && $coverageFertilizerVermicompostQuantity === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': please fill in at least one fertilizer quantity for garden projects that produce or reproduce fertilizers.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        if (($coverageLatitude === '') xor ($coverageLongitude === '')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': latitude and longitude must both be provided when one of them is filled in.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        if ($actualProjectId === '') {
            $actualProjectId = $coverageEntryId !== '' ? $coverageEntryId : generateActualProjectId();
        }
        $coverageEntryId = $actualProjectId;

        if ($coverageLatitude !== '' && ((float) $coverageLatitude < -90 || (float) $coverageLatitude > 90)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': latitude must be a valid number between -90 and 90.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        if ($coverageLongitude !== '' && ((float) $coverageLongitude < -180 || (float) $coverageLongitude > 180)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': longitude must be a valid number between -180 and 180.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        if ($coverageClassification !== 'BINHI' || $coverageFertilizerEnabled !== '1') {
            $coverageFertilizerOhnQuantity = '';
            $coverageFertilizerConcoctionQuantity = '';
            $coverageFertilizerVermicompostQuantity = '';
        }

        $hasAquaticResourceInput = $coverageAquaticResource !== '' || $coverageAquaticResourceQuantity !== '';
        if ($hasAquaticResourceInput) {
            $normalizedAquaticResource = strtolower($coverageAquaticResource);
            if ($normalizedAquaticResource === 'crabs' || $normalizedAquaticResource === 'fish') {
                $coverageAquaticResource = $normalizedAquaticResource;
            }

            if ($coverageAquaticResource === '' || $coverageAquaticResourceQuantity === '' || !preg_match('/^\d+$/', $coverageAquaticResourceQuantity)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $barangay . ': aquatic resource and quantity must both be filled in when aquatic resources are included.']);
                $stmt->close();
                $targetLookupStmt->close();
                exit;
            }
        } else {
            $coverageAquaticResource = '';
            $coverageAquaticResourceQuantity = '';
        }

        if (in_array($coverageClassification, ['LAWA', 'BINHI'], true)) {
            if ($coverageActualAccomplishment === '' || !preg_match('/^\d+$/', $coverageActualAccomplishment)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $barangay . ': LAWA and BINHI entries need a whole-number actual accomplishment for the selected type.']);
                $stmt->close();
                $targetLookupStmt->close();
                exit;
            }
        } else {
            $coverageActualAccomplishment = '';
        }

        if ($coverageDriveLink !== '' && filter_var($coverageDriveLink, FILTER_VALIDATE_URL) === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': Drive Link must be a valid URL when provided.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        $normalizedCoveragePuroks[] = $coveragePurok;
        $normalizedActualProjectIds[] = $actualProjectId;
        $normalizedCoverageEntryIds[] = $coverageEntryId;
        $normalizedTargetProjectRowIds[] = $targetProjectRowId;
        $normalizedCoverageLatitudes[] = $coverageLatitude;
        $normalizedCoverageLongitudes[] = $coverageLongitude;
        $normalizedCoverageProjectNames[] = $coverageProjectName;
        $normalizedCoverageProjectClassifications[] = $coverageClassification;
        $normalizedCoverageProjectTypes[] = $coverageProjectType;
        $normalizedCoverageFertilizerEnabledFlags[] = $coverageFertilizerEnabled;
        $normalizedCoverageFertilizerOhnQuantities[] = $coverageFertilizerOhnQuantity;
        $normalizedCoverageFertilizerConcoctionQuantities[] = $coverageFertilizerConcoctionQuantity;
        $normalizedCoverageFertilizerVermicompostQuantities[] = $coverageFertilizerVermicompostQuantity;
        $normalizedCoverageAquaticResources[] = $coverageAquaticResource;
        $normalizedCoverageAquaticResourceQuantities[] = $coverageAquaticResourceQuantity;
        $normalizedCoverageLandAreas[] = $coverageLandArea;
        $normalizedCoverageLandOwnerships[] = $coverageLandOwnership;
        $normalizedCoverageDriveLinks[] = $coverageDriveLink;
        $coverageActualAccomplishments[] = $coverageActualAccomplishment;
        $coverageStatuses[] = $coverageStatus;
    }
    $fundObligationPartnerBeneficiaries = isset($row['fund_obligation_partner_beneficiaries']) ? (int) $row['fund_obligation_partner_beneficiaries'] : 0;
    $fundDisbursementServedPartnerBeneficiaries = isset($row['fund_disbursement_served_partner_beneficiaries']) ? (int) $row['fund_disbursement_served_partner_beneficiaries'] : 0;
    $liquidationDate = trim((string) ($row['liquidation_date'] ?? ''));
    $lastDayProjectImplementation = trim((string) ($row['last_day_project_implementation'] ?? ''));
    $checkIssuanceDate = trim((string) ($row['check_issuance_date'] ?? ''));
    $workAccomplishmentReportStatus = preg_replace('/\s+/', ' ', trim((string) ($row['work_accomplishment_report_status'] ?? '')));
    $performanceRatingRemarks = preg_replace('/\s+/', ' ', trim((string) ($row['performance_rating_remarks'] ?? '')));
    $specialDisbursingOfficer = preg_replace('/\s+/', ' ', trim((string) ($row['special_disbursing_officer'] ?? '')));
    $binhiSitesEstablishedTarget = isset($row['binhi_sites_established_target']) ? (int) $row['binhi_sites_established_target'] : 0;
    $binhiSitesEstablishedActual = isset($row['binhi_sites_established_actual']) ? (int) $row['binhi_sites_established_actual'] : 0;
    $binhiFacilitiesAddedTarget = isset($row['binhi_facilities_added_target']) ? (int) $row['binhi_facilities_added_target'] : 0;
    $binhiFacilitiesAddedActual = isset($row['binhi_facilities_added_actual']) ? (int) $row['binhi_facilities_added_actual'] : 0;
    $fertilizerOhnTarget = normalizeNonNegativeDecimal($row['fertilizer_ohn_target'] ?? 0);
    $fertilizerOhnActual = normalizeNonNegativeDecimal($row['fertilizer_ohn_actual'] ?? 0);
    $fertilizerConcoctionTarget = normalizeNonNegativeDecimal($row['fertilizer_concoction_target'] ?? 0);
    $fertilizerConcoctionActual = normalizeNonNegativeDecimal($row['fertilizer_concoction_actual'] ?? 0);
    $fertilizerVermicompostTarget = normalizeNonNegativeDecimal($row['fertilizer_vermicompost_target'] ?? 0);
    $fertilizerVermicompostActual = normalizeNonNegativeDecimal($row['fertilizer_vermicompost_actual'] ?? 0);
    $areaLandUtilizedTarget = normalizeNonNegativeDecimal($row['area_land_utilized_target'] ?? 0);
    $siteValidationRaw = trim((string) ($row['site_validation'] ?? ''));

    $stageRanges = [
        'Stage 1 - Cash-for-Training' => [$stage1Start, $stage1End],
        'Stage 2 - Cash-for-Work' => [$stage2Start, $stage2End],
        'Stage 3 - Cash-for-Training (Sustainability Training)' => [$stage3Start, $stage3End],
    ];

    foreach ($stageRanges as $label => [$startDate, $endDate]) {
        if ($startDate === '' && $endDate === '') {
            continue;
        }

        if ($startDate === '' && $endDate !== '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' needs a Start date before its End date can be set.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        if ($startDate > $endDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' Start date cannot be later than the End date.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }
    }

    foreach ([
        'LAWA actual accomplishment' => $actualLawaAccomplishment,
        'BINHI actual accomplishment' => $actualBinhiAccomplishment,
        'CapBuild actual accomplishment' => $actualCapbuildAccomplishment,
        'Community action plan actual accomplishment' => $actualCommunityActionPlanAccomplishment,
        'Fund obligation partner-beneficiaries' => $fundObligationPartnerBeneficiaries,
        'Served partner-beneficiaries during payout' => $fundDisbursementServedPartnerBeneficiaries,
        'BINHI sites established target' => $binhiSitesEstablishedTarget,
        'BINHI sites established actual' => $binhiSitesEstablishedActual,
        'BINHI facilities added target' => $binhiFacilitiesAddedTarget,
        'BINHI facilities added actual' => $binhiFacilitiesAddedActual,
    ] as $label => $targetValue) {
        if ($targetValue !== null && $targetValue < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' cannot be negative.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }
    }

    if ($fundDisbursementServedPartnerBeneficiaries > $fundObligationPartnerBeneficiaries) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $barangay . ': served partner-beneficiaries cannot be greater than the obligated partner-beneficiaries.']);
        $stmt->close();
        $targetLookupStmt->close();
        exit;
    }

    $postImplementationRanges = [
        'DRMD Monitoring Schedule' => [$drmdMonitoringFrom, $drmdMonitoringTo],
        'Joint DRMB-DRMD Post-Monitoring Schedule' => [$jointPostMonitoringFrom, $jointPostMonitoringTo],
        'Payout Schedule' => [$payoutScheduleFrom, $payoutScheduleTo],
    ];

    foreach ($postImplementationRanges as $label => [$fromDate, $toDate]) {
        if ($fromDate === '' && $toDate === '') {
            continue;
        }

        if ($fromDate === '' || $toDate === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' needs both From and To dates when one of them is provided.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }

        if ($fromDate > $toDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' From date cannot be later than the To date.']);
            $stmt->close();
            $targetLookupStmt->close();
            exit;
        }
    }

    $stage1Start = $stage1Start !== '' ? $stage1Start : null;
    $stage1End = $stage1End !== '' ? $stage1End : null;
    $stage2Start = $stage2Start !== '' ? $stage2Start : null;
    $stage2End = $stage2End !== '' ? $stage2End : null;
    $stage3Start = $stage3Start !== '' ? $stage3Start : null;
    $stage3End = $stage3End !== '' ? $stage3End : null;
    $drmdMonitoringFrom = $drmdMonitoringFrom !== '' ? $drmdMonitoringFrom : null;
    $drmdMonitoringTo = $drmdMonitoringTo !== '' ? $drmdMonitoringTo : null;
    $jointPostMonitoringFrom = $jointPostMonitoringFrom !== '' ? $jointPostMonitoringFrom : null;
    $jointPostMonitoringTo = $jointPostMonitoringTo !== '' ? $jointPostMonitoringTo : null;
    $payoutScheduleFrom = $payoutScheduleFrom !== '' ? $payoutScheduleFrom : null;
    $payoutScheduleTo = $payoutScheduleTo !== '' ? $payoutScheduleTo : null;
    $liquidationDate = $liquidationDate !== '' ? $liquidationDate : null;
    $lastDayProjectImplementation = $lastDayProjectImplementation !== '' ? $lastDayProjectImplementation : null;
    $checkIssuanceDate = $checkIssuanceDate !== '' ? $checkIssuanceDate : null;
    $blguFrom = $blguFrom !== '' ? $blguFrom : null;
    $blguTo = $blguTo !== '' ? $blguTo : null;

    $siteValidation = '';
    if ($siteValidationRaw !== '') {
        $siteValidationEntries = preg_split('/\|\|/', $siteValidationRaw) ?: [];
        $normalizedSiteValidationEntries = [];

        foreach ($siteValidationEntries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (strpos($entry, '~') !== false) {
                [$startDate, $endDate] = array_pad(explode('~', $entry, 2), 2, '');
                $startDate = trim($startDate);
                $endDate = trim($endDate);
            } else {
                $startDate = $entry;
                $endDate = $entry;
            }

            if ($startDate === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $barangay . ': each Site Validation row needs a valid Start date.']);
                $stmt->close();
                $targetLookupStmt->close();
                exit;
            }

            if ($endDate === '') {
                $endDate = $startDate;
            }

            if ($startDate > $endDate) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $barangay . ': each Site Validation range must have a Start date earlier than or equal to its End date.']);
                $stmt->close();
                $targetLookupStmt->close();
                exit;
            }

            $normalizedSiteValidationEntries[] = $startDate . '~' . $endDate;
        }

        $siteValidation = implode('||', $normalizedSiteValidationEntries);
    }

    foreach ($normalizedCoverageProjectClassifications as $classification) {
        $classification = strtoupper(trim((string) $classification));
        if ($classification === 'LAWA') {
            $actualLawaAccomplishment += 1;
        } elseif ($classification === 'BINHI') {
            $actualBinhiAccomplishment += 1;
        }
    }

    $actualProjectRows = [];
    foreach ($normalizedCoverageProjectNames as $entryIndex => $coverageProjectName) {
        $actualProjectRows[] = [
            'actual_project_id' => (string) ($normalizedActualProjectIds[$entryIndex] ?? ''),
            'coverage_entry_id' => ($normalizedCoverageEntryIds[$entryIndex] ?? '') !== '' ? (string) $normalizedCoverageEntryIds[$entryIndex] : null,
            'target_project_row_id' => ($normalizedTargetProjectRowIds[$entryIndex] ?? '') !== '' ? (string) $normalizedTargetProjectRowIds[$entryIndex] : null,
            'sort_order' => $entryIndex,
            'purok' => (string) ($normalizedCoveragePuroks[$entryIndex] ?? ''),
            'latitude' => ($normalizedCoverageLatitudes[$entryIndex] ?? '') !== '' ? (float) $normalizedCoverageLatitudes[$entryIndex] : null,
            'longitude' => ($normalizedCoverageLongitudes[$entryIndex] ?? '') !== '' ? (float) $normalizedCoverageLongitudes[$entryIndex] : null,
            'project_name' => (string) $coverageProjectName,
            'project_classification' => (string) ($normalizedCoverageProjectClassifications[$entryIndex] ?? ''),
            'project_type' => (string) ($normalizedCoverageProjectTypes[$entryIndex] ?? ''),
            'fertilizer_enabled' => (string) ($normalizedCoverageFertilizerEnabledFlags[$entryIndex] ?? '') === '1' ? 1 : 0,
            'fertilizer_ohn_quantity' => ($normalizedCoverageFertilizerOhnQuantities[$entryIndex] ?? '') !== '' ? (float) $normalizedCoverageFertilizerOhnQuantities[$entryIndex] : null,
            'fertilizer_concoction_quantity' => ($normalizedCoverageFertilizerConcoctionQuantities[$entryIndex] ?? '') !== '' ? (float) $normalizedCoverageFertilizerConcoctionQuantities[$entryIndex] : null,
            'fertilizer_vermicompost_quantity' => ($normalizedCoverageFertilizerVermicompostQuantities[$entryIndex] ?? '') !== '' ? (float) $normalizedCoverageFertilizerVermicompostQuantities[$entryIndex] : null,
            'aquatic_resource' => ($normalizedCoverageAquaticResources[$entryIndex] ?? '') !== '' ? (string) $normalizedCoverageAquaticResources[$entryIndex] : null,
            'aquatic_resource_quantity' => ($normalizedCoverageAquaticResourceQuantities[$entryIndex] ?? '') !== '' ? (int) $normalizedCoverageAquaticResourceQuantities[$entryIndex] : null,
            'actual_accomplishment' => ($coverageActualAccomplishments[$entryIndex] ?? '') !== '' ? (string) $coverageActualAccomplishments[$entryIndex] : null,
            'land_area' => ($normalizedCoverageLandAreas[$entryIndex] ?? '') !== '' ? (string) $normalizedCoverageLandAreas[$entryIndex] : null,
            'land_ownership' => ($normalizedCoverageLandOwnerships[$entryIndex] ?? '') !== '' ? (string) $normalizedCoverageLandOwnerships[$entryIndex] : null,
            'drive_link' => ($normalizedCoverageDriveLinks[$entryIndex] ?? '') !== '' ? (string) $normalizedCoverageDriveLinks[$entryIndex] : null,
            'status' => (string) ($coverageStatuses[$entryIndex] ?? 'pending'),
        ];
    }

    $params = [
        $selectedYear,
        $province,
        $municipality,
        $barangay,
        $plguFrom,
        $mlguFrom,
        $blguFrom,
        $plguFrom,
        $plguTo,
        $mlguFrom,
        $mlguTo,
        $blguFrom,
        $blguTo,
        $siteValidation,
        $stage1Start,
        $stage1End,
        $stage2Start,
        $stage2End,
        $stage3Start,
        $stage3End,
        $drmdMonitoringFrom,
        $drmdMonitoringTo,
        $drmdMonitoringParticipants,
        $jointPostMonitoringFrom,
        $jointPostMonitoringTo,
        $jointPostMonitoringParticipants,
        $payoutScheduleFrom,
        $payoutScheduleTo,
        $actualLawaAccomplishment,
        $actualBinhiAccomplishment,
        $actualCapbuildAccomplishment,
        $actualCommunityActionPlanAccomplishment,
        $fundObligationPartnerBeneficiaries,
        $fundDisbursementServedPartnerBeneficiaries,
        $liquidationDate,
        $lastDayProjectImplementation,
        $checkIssuanceDate,
        $workAccomplishmentReportStatus,
        $performanceRatingRemarks,
        $specialDisbursingOfficer,
        $binhiSitesEstablishedTarget,
        $binhiSitesEstablishedActual,
        $binhiFacilitiesAddedTarget,
        $binhiFacilitiesAddedActual,
        $fertilizerOhnTarget,
        $fertilizerOhnActual,
        $fertilizerConcoctionTarget,
        $fertilizerConcoctionActual,
        $fertilizerVermicompostTarget,
        $fertilizerVermicompostActual,
        $areaLandUtilizedTarget,
    ];
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $exception) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to save program activity changes right now.',
            'debug' => $exception->getMessage(),
        ]);
        $stmt->close();
        $targetLookupStmt->close();
        exit;
    }

    $metadataLookupStmt = $conn->prepare("
        SELECT id
        FROM program_activity_metadata
        WHERE fiscal_year = ? AND province = ? AND municipality = ? AND barangay = ?
        LIMIT 1
    ");
    if ($metadataLookupStmt) {
        $metadataLookupStmt->bind_param('isss', $selectedYear, $province, $municipality, $barangay);
        $metadataLookupStmt->execute();
        $metadataId = (int) ((db_stmt_fetch_one_assoc($metadataLookupStmt)['id'] ?? 0));
        $metadataLookupStmt->close();

        if ($metadataId > 0) {
            programActivityReplaceActualProjects($conn, $metadataId, $actualProjectRows);
            programActivityBackfillProjectCodes($conn, true);
        }
    }
}

$stmt->close();
$targetLookupStmt->close();

echo json_encode(['success' => true, 'message' => 'Program activity updated successfully.']);
