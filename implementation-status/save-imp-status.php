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

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if (!isset($_SESSION['selected_year'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fiscal year not selected.']);
    exit;
}

$municipality = trim((string) ($_POST['municipality'] ?? ''));
$province = trim((string) ($_POST['province'] ?? ''));
$plguFrom = trim((string) ($_POST['plgu_from'] ?? ''));
$plguTo = trim((string) ($_POST['plgu_to'] ?? ''));
$mlguFrom = trim((string) ($_POST['mlgu_from'] ?? ''));
$mlguTo = trim((string) ($_POST['mlgu_to'] ?? ''));
$blguFrom = trim((string) ($_POST['blgu_from'] ?? ''));
$blguTo = trim((string) ($_POST['blgu_to'] ?? ''));
$rows = json_decode($_POST['rows'] ?? '[]', true);

if ($municipality === '' || $province === '' || !is_array($rows) || empty($rows)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$forumRanges = [
    'PLGU Forum' => [$plguFrom, $plguTo],
    'MLGU Forum' => [$mlguFrom, $mlguTo],
    'BLGU Forum' => [$blguFrom, $blguTo],
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
$blguFrom = $blguFrom !== '' ? $blguFrom : null;
$blguTo = $blguTo !== '' ? $blguTo : null;

ensureProgramActivityMetadata($conn);
ensureProjectLawaBinhiTargets($conn);

$selectedYear = (int) $_SESSION['selected_year'];
$stmt = $conn->prepare("
    INSERT INTO program_activity_metadata (
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
        fund_obligation_partner_beneficiaries,
        fund_disbursement_served_partner_beneficiaries,
        liquidation_date,
        special_disbursing_officer,
        project_names
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        fund_obligation_partner_beneficiaries = VALUES(fund_obligation_partner_beneficiaries),
        fund_disbursement_served_partner_beneficiaries = VALUES(fund_disbursement_served_partner_beneficiaries),
        liquidation_date = VALUES(liquidation_date),
        special_disbursing_officer = VALUES(special_disbursing_officer),
        project_names = VALUES(project_names),
        updated_at = CURRENT_TIMESTAMP
");

$targetLookupStmt = $conn->prepare("
    SELECT lawa_target, binhi_target, capbuild_target, community_action_plan_target, target_partner_beneficiaries
    FROM project_lawa_binhi_targets
    WHERE fiscal_year = ?
      AND province = ?
      AND municipality = ?
      AND barangay = ?
    LIMIT 1
");

$targetSaveStmt = $conn->prepare("
    INSERT INTO project_lawa_binhi_targets (
        fiscal_year,
        province,
        municipality,
        barangay,
        puroks,
        project_names,
        project_classifications,
        lawa_target,
        binhi_target,
        capbuild_target,
        community_action_plan_target,
        target_partner_beneficiaries
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        puroks = VALUES(puroks),
        project_names = VALUES(project_names),
        project_classifications = VALUES(project_classifications),
        lawa_target = VALUES(lawa_target),
        binhi_target = VALUES(binhi_target),
        capbuild_target = VALUES(capbuild_target),
        community_action_plan_target = VALUES(community_action_plan_target),
        target_partner_beneficiaries = VALUES(target_partner_beneficiaries),
        updated_at = CURRENT_TIMESTAMP
");

foreach ($rows as $row) {
    $barangay = trim((string) ($row['barangay'] ?? ''));
    $entriesInput = is_array($row['target_entries'] ?? null) ? $row['target_entries'] : [];

    if ($barangay === '') {
        continue;
    }

    $normalizedProvince = normalizeProjectTargetLocation($province);
    $normalizedMunicipality = normalizeProjectTargetLocation($municipality);
    $normalizedBarangay = normalizeProjectTargetLocation($barangay);
    $puroks = [];
    $projects = [];
    $classifications = [];
    $lawaTarget = isset($row['lawa_target']) ? (int) $row['lawa_target'] : null;
    $binhiTarget = isset($row['binhi_target']) ? (int) $row['binhi_target'] : null;
    $capbuildTarget = isset($row['capbuild_target']) ? (int) $row['capbuild_target'] : null;
    $communityActionPlanTarget = isset($row['community_action_plan_target']) ? (int) $row['community_action_plan_target'] : null;
    $targetBeneficiaries = isset($row['target_partner_beneficiaries']) ? (int) $row['target_partner_beneficiaries'] : null;
    $stage1Start = trim((string) ($row['stage1_start_date'] ?? ''));
    $stage1End = trim((string) ($row['stage1_end_date'] ?? ''));
    $stage2Start = trim((string) ($row['stage2_start_date'] ?? ''));
    $stage2End = trim((string) ($row['stage2_end_date'] ?? ''));
    $stage3Start = trim((string) ($row['stage3_start_date'] ?? ''));
    $stage3End = trim((string) ($row['stage3_end_date'] ?? ''));
    $drmdMonitoringFrom = trim((string) ($row['drmd_monitoring_from'] ?? ''));
    $drmdMonitoringTo = trim((string) ($row['drmd_monitoring_to'] ?? ''));
    $drmdMonitoringParticipants = preg_replace('/\s+/', ' ', trim((string) ($row['drmd_monitoring_participants'] ?? '')));
    $jointPostMonitoringFrom = trim((string) ($row['joint_post_monitoring_from'] ?? ''));
    $jointPostMonitoringTo = trim((string) ($row['joint_post_monitoring_to'] ?? ''));
    $jointPostMonitoringParticipants = preg_replace('/\s+/', ' ', trim((string) ($row['joint_post_monitoring_participants'] ?? '')));
    $payoutScheduleFrom = trim((string) ($row['payout_schedule_from'] ?? ''));
    $payoutScheduleTo = trim((string) ($row['payout_schedule_to'] ?? ''));
    $fundObligationPartnerBeneficiaries = isset($row['fund_obligation_partner_beneficiaries']) ? (int) $row['fund_obligation_partner_beneficiaries'] : 0;
    $fundDisbursementServedPartnerBeneficiaries = isset($row['fund_disbursement_served_partner_beneficiaries']) ? (int) $row['fund_disbursement_served_partner_beneficiaries'] : 0;
    $liquidationDate = trim((string) ($row['liquidation_date'] ?? ''));
    $specialDisbursingOfficer = preg_replace('/\s+/', ' ', trim((string) ($row['special_disbursing_officer'] ?? '')));
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
            $targetSaveStmt->close();
            exit;
        }

        if ($startDate > $endDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' Start date cannot be later than the End date.']);
            $stmt->close();
            $targetLookupStmt->close();
            $targetSaveStmt->close();
            exit;
        }
    }

    foreach ([
        'LAWA' => $lawaTarget,
        'BINHI' => $binhiTarget,
        'CapBuild' => $capbuildTarget,
        'Community action plan' => $communityActionPlanTarget,
        'Target partner-beneficiaries' => $targetBeneficiaries,
        'Fund obligation partner-beneficiaries' => $fundObligationPartnerBeneficiaries,
        'Served partner-beneficiaries during payout' => $fundDisbursementServedPartnerBeneficiaries,
    ] as $label => $targetValue) {
        if ($targetValue !== null && $targetValue < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' target cannot be negative.']);
            $stmt->close();
            $targetLookupStmt->close();
            $targetSaveStmt->close();
            exit;
        }
    }

    if ($fundDisbursementServedPartnerBeneficiaries > $fundObligationPartnerBeneficiaries) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $barangay . ': served partner-beneficiaries cannot be greater than the obligated partner-beneficiaries.']);
        $stmt->close();
        $targetLookupStmt->close();
        $targetSaveStmt->close();
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
            $targetSaveStmt->close();
            exit;
        }

        if ($fromDate > $toDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $barangay . ': ' . $label . ' From date cannot be later than the To date.']);
            $stmt->close();
            $targetLookupStmt->close();
            $targetSaveStmt->close();
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
                $targetSaveStmt->close();
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
                $targetSaveStmt->close();
                exit;
            }

            $normalizedSiteValidationEntries[] = $startDate . '~' . $endDate;
        }

        $siteValidation = implode('||', $normalizedSiteValidationEntries);
    }

    foreach ($entriesInput as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $purok = normalizeProjectTargetLocation((string) ($entry['purok'] ?? ''));
        $projectName = preg_replace('/\s+/', ' ', trim((string) ($entry['name'] ?? '')));
        $classification = normalizeProjectTargetLocation((string) ($entry['classification'] ?? ''));

        if ($purok === '' && $projectName === '' && $classification === '') {
            continue;
        }

        if ($purok === '' || $projectName === '' || $classification === '' || !in_array($classification, ['LAWA', 'BINHI'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Each target coverage row must include a purok, project name, and classification of LAWA or BINHI.']);
            $stmt->close();
            $targetLookupStmt->close();
            $targetSaveStmt->close();
            exit;
        }

        $puroks[] = $purok;
        $projects[] = $projectName;
        $classifications[] = $classification;
    }

    $projectNames = implode('||', $projects);
    $puroksValue = implode('||', $puroks);
    $classificationsValue = implode('||', $classifications);

    $resolvedLawaTarget = 0;
    $resolvedBinhiTarget = 0;
    $resolvedCapbuildTarget = 0;
    $resolvedCommunityActionPlanTarget = 0;
    $resolvedTargetBeneficiaries = 0;
    $targetLookupStmt->bind_param('isss', $selectedYear, $normalizedProvince, $normalizedMunicipality, $normalizedBarangay);
    $targetLookupStmt->execute();
    $targetLookupResult = $targetLookupStmt->get_result();
    if ($targetLookupResult instanceof mysqli_result) {
        $targetRow = $targetLookupResult->fetch_assoc() ?: [];
        $resolvedLawaTarget = (int) ($targetRow['lawa_target'] ?? 0);
        $resolvedBinhiTarget = (int) ($targetRow['binhi_target'] ?? 0);
        $resolvedCapbuildTarget = (int) ($targetRow['capbuild_target'] ?? 0);
        $resolvedCommunityActionPlanTarget = (int) ($targetRow['community_action_plan_target'] ?? 0);
        $resolvedTargetBeneficiaries = (int) ($targetRow['target_partner_beneficiaries'] ?? 0);
        $targetLookupResult->free();
    }

    if ($lawaTarget !== null) {
        $resolvedLawaTarget = $lawaTarget;
    }
    if ($binhiTarget !== null) {
        $resolvedBinhiTarget = $binhiTarget;
    }
    if ($capbuildTarget !== null) {
        $resolvedCapbuildTarget = $capbuildTarget;
    }
    if ($communityActionPlanTarget !== null) {
        $resolvedCommunityActionPlanTarget = $communityActionPlanTarget;
    }
    if ($targetBeneficiaries !== null) {
        $resolvedTargetBeneficiaries = $targetBeneficiaries;
    }

    $targetSaveStmt->bind_param(
        'issssssiiiii',
        $selectedYear,
        $normalizedProvince,
        $normalizedMunicipality,
        $normalizedBarangay,
        $puroksValue,
        $projectNames,
        $classificationsValue,
        $resolvedLawaTarget,
        $resolvedBinhiTarget,
        $resolvedCapbuildTarget,
        $resolvedCommunityActionPlanTarget,
        $resolvedTargetBeneficiaries
    );
    $targetSaveStmt->execute();

    $stmt->bind_param(
        "ssssssssssssssssssssssssssiisss",
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
        $fundObligationPartnerBeneficiaries,
        $fundDisbursementServedPartnerBeneficiaries,
        $liquidationDate,
        $specialDisbursingOfficer,
        $projectNames
    );
    $stmt->execute();
}

$stmt->close();
$targetLookupStmt->close();
$targetSaveStmt->close();

echo json_encode(['success' => true, 'message' => 'Program activity updated successfully.']);
