<?php
require_once '../security.php';
security_bootstrap_session();
require_once '../auth_helpers.php';
include('../config.php');
require_once __DIR__ . '/activity_metadata.php';
require_once '../project_targets_helpers.php';
require_once '../project_variable_helpers.php';

auth_handle_page_access($conn);
auth_apply_security_headers();
header('Content-Type: application/json');

if (!isset($_SESSION['selected_year'])) {
    echo json_encode(['data' => [], 'error' => 'Fiscal year not selected']);
    exit;
}

$classification = strtoupper(trim((string) ($_GET['classification'] ?? '')));
if (!in_array($classification, ['LAWA', 'BINHI'], true)) {
    http_response_code(400);
    echo json_encode(['data' => [], 'error' => 'Invalid classification']);
    exit;
}

$selectedYear = (int) $_SESSION['selected_year'];

ensureProgramActivityMetadata($conn, $selectedYear);
ensureProjectLawaBinhiTargets($conn);

function formatSummaryNumber($value): string
{
    $number = (float) $value;
    if (abs($number - round($number)) < 0.00001) {
        return number_format((float) round($number), 0, '.', ',');
    }

    return number_format($number, 2, '.', ',');
}

function formatTargetActualValue($target, $actual): string
{
    return formatSummaryNumber($target) . ' vs ' . formatSummaryNumber($actual);
}

function uniqueSummaryValues(array $values): array
{
    $unique = [];
    foreach ($values as $value) {
        $trimmed = preg_replace('/\s+/', ' ', trim((string) $value));
        if ($trimmed === '') {
            continue;
        }

        $unique[$trimmed] = true;
    }

    return array_keys($unique);
}

function joinSummaryValues(array $values, string $fallback = ''): string
{
    $unique = uniqueSummaryValues($values);
    return $unique ? implode(', ', $unique) : $fallback;
}

function normalizeSummaryTargetEntries(array $entries, string $classification): array
{
    $normalized = [];
    foreach ($entries as $entry) {
        $entryClassification = strtoupper(trim((string) ($entry['project_classification'] ?? '')));
        if ($entryClassification !== $classification) {
            continue;
        }

        $normalized[] = [
            'name' => trim((string) ($entry['project_name'] ?? '')),
            'type' => trim((string) ($entry['project_type'] ?? '')),
            'fertilizer_enabled' => !empty($entry['fertilizer_enabled']) ? '1' : '0',
            'fertilizer_ohn' => isset($entry['fertilizer_ohn_target']) ? (float) $entry['fertilizer_ohn_target'] : 0.0,
            'fertilizer_concoction' => isset($entry['fertilizer_concoction_target']) ? (float) $entry['fertilizer_concoction_target'] : 0.0,
            'fertilizer_vermicompost' => isset($entry['fertilizer_vermicompost_target']) ? (float) $entry['fertilizer_vermicompost_target'] : 0.0,
            'aquatic_resource' => trim((string) ($entry['aquatic_resource'] ?? '')),
            'aquatic_resource_quantity' => (int) ($entry['aquatic_resource_quantity'] ?? 0),
        ];
    }

    return $normalized;
}

function normalizeSummaryCoverageEntries(array $entries, string $classification): array
{
    $normalized = [];
    foreach ($entries as $entry) {
        $entryClassification = strtoupper(trim((string) ($entry['project_classification'] ?? '')));
        if ($entryClassification !== $classification) {
            continue;
        }

        $normalized[] = [
            'name' => trim((string) ($entry['project_name'] ?? '')),
            'type' => trim((string) ($entry['project_type'] ?? '')),
            'fertilizer_enabled' => !empty($entry['fertilizer_enabled']) ? '1' : '0',
            'fertilizer_ohn' => isset($entry['fertilizer_ohn_quantity']) ? (float) $entry['fertilizer_ohn_quantity'] : 0.0,
            'fertilizer_concoction' => isset($entry['fertilizer_concoction_quantity']) ? (float) $entry['fertilizer_concoction_quantity'] : 0.0,
            'fertilizer_vermicompost' => isset($entry['fertilizer_vermicompost_quantity']) ? (float) $entry['fertilizer_vermicompost_quantity'] : 0.0,
            'aquatic_resource' => trim((string) ($entry['aquatic_resource'] ?? '')),
            'aquatic_resource_quantity' => (int) ($entry['aquatic_resource_quantity'] ?? 0),
            'actual' => (int) ($entry['actual_accomplishment'] ?? 0),
            'land_area' => trim((string) ($entry['land_area'] ?? '')),
        ];
    }

    return $normalized;
}

function countEntriesByKeywords(array $entries, array $keywords): int
{
    $count = 0;
    foreach ($entries as $entry) {
        $source = trim((string) ($entry['type'] ?? ''));
        if ($source === '') {
            $source = trim((string) ($entry['name'] ?? ''));
        }
        $haystack = strtoupper($source);
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && strpos($haystack, strtoupper($keyword)) !== false) {
                $count++;
                break;
            }
        }
    }

    return $count;
}

function sumEntryActualsByKeywords(array $entries, array $keywords): int
{
    $total = 0;
    foreach ($entries as $entry) {
        $source = trim((string) ($entry['type'] ?? ''));
        if ($source === '') {
            $source = trim((string) ($entry['name'] ?? ''));
        }
        $haystack = strtoupper($source);
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && strpos($haystack, strtoupper($keyword)) !== false) {
                $total += (int) ($entry['actual'] ?? 0);
                break;
            }
        }
    }

    return $total;
}

function countLawaEstablished(array $entries): int
{
    return countEntriesByKeywords($entries, ['CONSTRUCTION', 'INSTALLATION']);
}

function sumAquaticResourceQuantities(array $entries): int
{
    $total = 0;
    foreach ($entries as $entry) {
        if (trim((string) ($entry['aquatic_resource'] ?? '')) === '') {
            continue;
        }

        $total += (int) ($entry['aquatic_resource_quantity'] ?? 0);
    }

    return $total;
}

function countLawaRepaired(array $entries): int
{
    return countEntriesByKeywords($entries, ['REHABILITATION']);
}

function sumLawaEstablishedActuals(array $entries): int
{
    return sumEntryActualsByKeywords($entries, ['CONSTRUCTION', 'INSTALLATION']);
}

function sumLawaRepairedActuals(array $entries): int
{
    return sumEntryActualsByKeywords($entries, ['REHABILITATION']);
}

function sumLandAreaFromEntries(array $entries): float
{
    $sum = 0.0;
    foreach ($entries as $entry) {
        $normalized = preg_replace('/[^\d.-]/', '', (string) ($entry['land_area'] ?? ''));
        if ($normalized === '' || !is_numeric($normalized)) {
            continue;
        }

        $sum += (float) $normalized;
    }

    return $sum;
}

function parseSummaryDecimalValue($value): ?float
{
    $normalized = preg_replace('/[^\d.-]/', '', (string) $value);
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}

function computeLawaWaterCapacityFromEntries(mysqli $conn, int $selectedYear, array $entries): ?float
{
    $total = 0.0;
    $hasComputedValue = false;

    foreach ($entries as $entry) {
        $projectType = trim((string) ($entry['type'] ?? ''));
        if ($projectType === '') {
            $projectType = trim((string) ($entry['name'] ?? ''));
        }

        $landArea = parseSummaryDecimalValue($entry['land_area'] ?? '');
        $factor = project_variable_get_lawa_capacity_factor($conn, $selectedYear, $projectType);

        if ($landArea === null || $factor === null) {
            continue;
        }

        $total += $landArea * $factor;
        $hasComputedValue = true;
    }

    if (!$hasComputedValue) {
        return null;
    }

    return $total;
}

function roundSummaryWholeNumber(float $value): int
{
    return (int) round($value, 0, PHP_ROUND_HALF_UP);
}

function computeBinhiProduce(mysqli $conn, int $selectedYear, string $typeLabel, int $actualCount): float
{
    if ($actualCount <= 0) {
        return 0.0;
    }

    $yieldFactor = project_variable_get_binhi_yield_factor($conn, $selectedYear, $typeLabel);
    if ($yieldFactor === null) {
        return 0.0;
    }

    return $actualCount * $yieldFactor;
}

function computeBinhiIndividualsFed(mysqli $conn, int $selectedYear, string $typeLabel, float $produce): int
{
    if ($produce <= 0) {
        return 0;
    }

    $feedRequirement = project_variable_get_binhi_individual_feed_requirement($conn, $selectedYear, $typeLabel);
    if ($feedRequirement === null || $feedRequirement <= 0) {
        return 0;
    }

    return roundSummaryWholeNumber($produce / $feedRequirement);
}

function computeBinhiFamiliesFed(mysqli $conn, int $selectedYear, int $individualsFed): int
{
    if ($individualsFed <= 0) {
        return 0;
    }

    $familySize = project_variable_get_binhi_family_size($conn, $selectedYear);
    if ($familySize <= 0) {
        return 0;
    }

    return roundSummaryWholeNumber($individualsFed / $familySize);
}

function countBinhiTypeMatches(array $entries, string $typeLabel): int
{
    $count = 0;
    $normalizedNeedle = strtoupper(trim($typeLabel));
    foreach ($entries as $entry) {
        $type = strtoupper(trim((string) ($entry['type'] ?? '')));
        $name = strtoupper(trim((string) ($entry['name'] ?? '')));
        if ($type === $normalizedNeedle || $name === $normalizedNeedle) {
            $count++;
        }
    }

    return $count;
}

function sumFertilizerQuantity(array $entries, string $key): float
{
    $total = 0.0;
    foreach ($entries as $entry) {
        if (trim((string) ($entry['fertilizer_enabled'] ?? '')) !== '1') {
            continue;
        }
        $total += (float) ($entry[$key] ?? 0);
    }

    return $total;
}

function hasFertilizerEntries(array $entries): bool
{
    foreach ($entries as $entry) {
        if (trim((string) ($entry['fertilizer_enabled'] ?? '')) === '1') {
            return true;
        }
    }

    return false;
}

function sumBinhiTypeActuals(array $entries, string $typeLabel): int
{
    $total = 0;
    $normalizedNeedle = strtoupper(trim($typeLabel));
    foreach ($entries as $entry) {
        $type = strtoupper(trim((string) ($entry['type'] ?? '')));
        $name = strtoupper(trim((string) ($entry['name'] ?? '')));
        if ($type === $normalizedNeedle || $name === $normalizedNeedle) {
            $total += (int) ($entry['actual'] ?? 0);
        }
    }

    return $total;
}

$sql = "
    SELECT
        locations.province,
        locations.municipality,
        locations.barangay,
        targets.id AS target_id,
        metadata.id AS metadata_id,
        COALESCE(targets.lawa_target, 0) AS lawa_target,
        COALESCE(targets.binhi_target, 0) AS binhi_target,
        COALESCE(targets.binhi_vegetable_target, 0) AS binhi_vegetable_target,
        COALESCE(targets.binhi_crops_target, 0) AS binhi_crops_target,
        COALESCE(targets.binhi_disaster_resilient_crops_target, 0) AS binhi_disaster_resilient_crops_target,
        COALESCE(targets.binhi_fruit_bearing_trees_target, 0) AS binhi_fruit_bearing_trees_target,
        COALESCE(targets.binhi_tilapia_target, 0) AS binhi_tilapia_target,
        COALESCE(targets.target_partner_beneficiaries, 0) AS target_partner_beneficiaries,
        COALESCE(actuals.beneficiary_count, 0) AS actual_beneficiaries,
        COALESCE(metadata.actual_lawa_accomplishment, 0) AS actual_lawa_accomplishment,
        COALESCE(metadata.actual_binhi_accomplishment, 0) AS actual_binhi_accomplishment,
        metadata.binhi_sites_established_target,
        metadata.binhi_sites_established_actual,
        metadata.binhi_facilities_added_target,
        metadata.binhi_facilities_added_actual,
        metadata.fertilizer_ohn_target,
        metadata.fertilizer_ohn_actual,
        metadata.fertilizer_concoction_target,
        metadata.fertilizer_concoction_actual,
        metadata.fertilizer_vermicompost_target,
        metadata.fertilizer_vermicompost_actual,
        metadata.area_land_utilized_target,
        metadata.updated_at
    FROM (
        SELECT province, municipality, barangay
        FROM project_lawa_binhi_targets
        WHERE fiscal_year = ?

        UNION

        SELECT province, lgu AS municipality, barangay
        FROM meb
        WHERE YEAR(time_stamp) = ?
        GROUP BY province, lgu, barangay
    ) AS locations
    LEFT JOIN project_lawa_binhi_targets AS targets
        ON targets.fiscal_year = ?
       AND targets.province = locations.province
       AND targets.municipality = locations.municipality
       AND targets.barangay = locations.barangay
    LEFT JOIN (
        SELECT
            province,
            lgu AS municipality,
            barangay,
            COUNT(*) AS beneficiary_count
        FROM meb
        WHERE YEAR(time_stamp) = ?
        GROUP BY province, lgu, barangay
    ) AS actuals
        ON actuals.province = locations.province
       AND actuals.municipality = locations.municipality
       AND actuals.barangay = locations.barangay
    LEFT JOIN program_activity_metadata AS metadata
        ON metadata.fiscal_year = ?
       AND metadata.province = locations.province
       AND metadata.municipality = locations.municipality
       AND metadata.barangay = locations.barangay
    ORDER BY locations.province, locations.municipality, locations.barangay
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiiii', $selectedYear, $selectedYear, $selectedYear, $selectedYear, $selectedYear);
$stmt->execute();
$result = db_stmt_fetch_all_assoc($stmt);

$targetEntryMap = projectTargetsFetchEntriesByTargetIds(
    $conn,
    array_map(static fn(array $row): int => (int) ($row['target_id'] ?? 0), $result)
);
$actualProjectMap = programActivityFetchActualProjectsByMetadataIds(
    $conn,
    array_map(static fn(array $row): int => (int) ($row['metadata_id'] ?? 0), $result)
);

$data = [];
$binhiTypes = [
    'Vegetable',
    'Crops (Banana, Corn, Rice)',
    'Disaster Resilient Crops (Taro, Sweet Potato)',
    'Fruit-Bearing Trees',
    'Tilapia (Fish pond)',
];

foreach ($result as $row) {
    $targetEntries = normalizeSummaryTargetEntries(
        $targetEntryMap[(int) ($row['target_id'] ?? 0)] ?? [],
        $classification
    );
    $coverageEntries = normalizeSummaryCoverageEntries(
        $actualProjectMap[(int) ($row['metadata_id'] ?? 0)] ?? [],
        $classification
    );
    $overallTarget = $classification === 'BINHI'
        ? (int) ($row['binhi_target'] ?? 0)
        : (int) ($row['lawa_target'] ?? 0);
    $overallActual = $classification === 'BINHI'
        ? (int) ($row['actual_binhi_accomplishment'] ?? 0)
        : (int) ($row['actual_lawa_accomplishment'] ?? 0);

    if ($overallTarget <= 0 && $overallActual <= 0 && !$targetEntries && !$coverageEntries) {
        continue;
    }

    $base = [
        'province' => (string) ($row['province'] ?? ''),
        'municipality' => (string) ($row['municipality'] ?? ''),
        'barangay' => (string) ($row['barangay'] ?? ''),
        'partner_beneficiaries_target' => (int) ($row['target_partner_beneficiaries'] ?? 0),
        'partner_beneficiaries_actual' => (int) ($row['actual_beneficiaries'] ?? 0),
        'partner_beneficiaries' => formatTargetActualValue(
            (int) ($row['target_partner_beneficiaries'] ?? 0),
            (int) ($row['actual_beneficiaries'] ?? 0)
        ),
        'last_updated' => trim((string) ($row['updated_at'] ?? '')),
    ];

    if ($classification === 'LAWA') {
        $lawaTypeNames = array_merge(
            array_map(static fn($entry) => $entry['name'] ?? '', $targetEntries),
            array_map(static fn($entry) => $entry['type'] ?: ($entry['name'] ?? ''), $coverageEntries)
        );
        $computedWaterCapacity = computeLawaWaterCapacityFromEntries($conn, $selectedYear, $coverageEntries);
        $potentialAreaMultiplier = project_variable_get_lawa_potential_area_multiplier($conn, $selectedYear);
        $data[] = array_merge($base, [
            'type_of_lawa' => joinSummaryValues($lawaTypeNames, 'No sub-project/activity recorded'),
            'no_of_lawa_target' => $overallTarget,
            'no_of_lawa_actual' => $overallActual,
            'aquatic_resources_target' => sumAquaticResourceQuantities($targetEntries),
            'aquatic_resources_actual' => sumAquaticResourceQuantities($coverageEntries),
            'facilities_established_target' => countLawaEstablished($targetEntries),
            'facilities_established_actual' => sumLawaEstablishedActuals($coverageEntries),
            'facilities_repaired_target' => countLawaRepaired($targetEntries),
            'facilities_repaired_actual' => sumLawaRepairedActuals($coverageEntries),
            'area_land_utilized_target' => 0,
            'area_land_utilized_actual' => sumLandAreaFromEntries($coverageEntries),
            'total_water_capacity' => $computedWaterCapacity !== null
                ? formatSummaryNumber($computedWaterCapacity)
                : '',
            'potential_area_agri_land' => $computedWaterCapacity !== null
                ? formatSummaryNumber($computedWaterCapacity * $potentialAreaMultiplier)
                : '',
        ]);

        continue;
    }

    $binhiTypeMap = [];
    foreach ($binhiTypes as $typeLabel) {
        $safeKey = match ($typeLabel) {
            'Vegetable' => 'vegetable',
            'Crops (Banana, Corn, Rice)' => 'crops',
            'Disaster Resilient Crops (Taro, Sweet Potato)' => 'disaster_resilient_crops',
            'Fruit-Bearing Trees' => 'fruit_bearing_trees',
            'Tilapia (Fish pond)' => 'tilapia',
            default => md5($typeLabel),
        };

        $storedTargetValue = match ($safeKey) {
            'vegetable' => (int) ($row['binhi_vegetable_target'] ?? 0),
            'crops' => (int) ($row['binhi_crops_target'] ?? 0),
            'disaster_resilient_crops' => (int) ($row['binhi_disaster_resilient_crops_target'] ?? 0),
            'fruit_bearing_trees' => (int) ($row['binhi_fruit_bearing_trees_target'] ?? 0),
            'tilapia' => (int) ($row['binhi_tilapia_target'] ?? 0),
            default => 0,
        };
        $targetValue = $storedTargetValue > 0 ? $storedTargetValue : countBinhiTypeMatches($targetEntries, $typeLabel);
        $actualValue = sumBinhiTypeActuals($coverageEntries, $typeLabel);
        $produceValue = computeBinhiProduce($conn, $selectedYear, $typeLabel, $actualValue);
        $individualsValue = computeBinhiIndividualsFed($conn, $selectedYear, $typeLabel, $produceValue);
        $familiesValue = computeBinhiFamiliesFed($conn, $selectedYear, $individualsValue);

        $binhiTypeMap[$safeKey] = [
            'target' => $targetValue,
            'actual' => $actualValue,
            'produce' => $produceValue,
            'individuals' => $individualsValue,
            'families' => $familiesValue,
        ];
    }

    $totalActualPlanted = (int) (
        ($binhiTypeMap['vegetable']['actual'] ?? 0)
        + ($binhiTypeMap['crops']['actual'] ?? 0)
        + ($binhiTypeMap['disaster_resilient_crops']['actual'] ?? 0)
        + ($binhiTypeMap['fruit_bearing_trees']['actual'] ?? 0)
    );
    $produceTotalGreens = (float) (
        ($binhiTypeMap['vegetable']['produce'] ?? 0)
        + ($binhiTypeMap['crops']['produce'] ?? 0)
        + ($binhiTypeMap['disaster_resilient_crops']['produce'] ?? 0)
        + ($binhiTypeMap['fruit_bearing_trees']['produce'] ?? 0)
    );
    $produceTotal = $produceTotalGreens + (float) ($binhiTypeMap['tilapia']['produce'] ?? 0);
    $individualsTotal = (int) array_sum(array_map(static fn($item) => (int) ($item['individuals'] ?? 0), $binhiTypeMap));
    $familiesTotal = computeBinhiFamiliesFed($conn, $selectedYear, $individualsTotal);

    $hasTargetFertilizerEntries = hasFertilizerEntries($targetEntries);
    $hasActualFertilizerEntries = hasFertilizerEntries($coverageEntries);

    $data[] = array_merge($base, [
        'binhi_vegetable_target' => $binhiTypeMap['vegetable']['target'],
        'binhi_vegetable_actual' => $binhiTypeMap['vegetable']['actual'],
        'binhi_crops_target' => $binhiTypeMap['crops']['target'],
        'binhi_crops_actual' => $binhiTypeMap['crops']['actual'],
        'binhi_disaster_resilient_crops_target' => $binhiTypeMap['disaster_resilient_crops']['target'],
        'binhi_disaster_resilient_crops_actual' => $binhiTypeMap['disaster_resilient_crops']['actual'],
        'binhi_fruit_bearing_trees_target' => $binhiTypeMap['fruit_bearing_trees']['target'],
        'binhi_fruit_bearing_trees_actual' => $binhiTypeMap['fruit_bearing_trees']['actual'],
        'binhi_total_planted' => formatSummaryNumber($totalActualPlanted),
        'binhi_tilapia_target' => $binhiTypeMap['tilapia']['target'],
        'binhi_tilapia_actual' => $binhiTypeMap['tilapia']['actual'],
        'produce_vegetable' => formatSummaryNumber($binhiTypeMap['vegetable']['produce']),
        'produce_crops' => formatSummaryNumber($binhiTypeMap['crops']['produce']),
        'produce_disaster_resilient_crops' => formatSummaryNumber($binhiTypeMap['disaster_resilient_crops']['produce']),
        'produce_fruit_bearing_trees' => formatSummaryNumber($binhiTypeMap['fruit_bearing_trees']['produce']),
        'produce_tilapia' => formatSummaryNumber($binhiTypeMap['tilapia']['produce']),
        'produce_total_greens' => formatSummaryNumber($produceTotalGreens),
        'produce_total' => formatSummaryNumber($produceTotal),
        'individuals_vegetable' => $binhiTypeMap['vegetable']['individuals'],
        'individuals_crops' => $binhiTypeMap['crops']['individuals'],
        'individuals_disaster_resilient_crops' => $binhiTypeMap['disaster_resilient_crops']['individuals'],
        'individuals_fruit_bearing_trees' => $binhiTypeMap['fruit_bearing_trees']['individuals'],
        'individuals_tilapia' => $binhiTypeMap['tilapia']['individuals'],
        'individuals_total' => $individualsTotal,
        'families_vegetable' => $binhiTypeMap['vegetable']['families'],
        'families_crops' => $binhiTypeMap['crops']['families'],
        'families_disaster_resilient_crops' => $binhiTypeMap['disaster_resilient_crops']['families'],
        'families_fruit_bearing_trees' => $binhiTypeMap['fruit_bearing_trees']['families'],
        'families_tilapia' => $binhiTypeMap['tilapia']['families'],
        'families_total' => $familiesTotal,
        'fertilizer_ohn_target' => formatSummaryNumber($hasTargetFertilizerEntries ? sumFertilizerQuantity($targetEntries, 'fertilizer_ohn') : (float) ($row['fertilizer_ohn_target'] ?? 0)),
        'fertilizer_ohn_actual' => formatSummaryNumber($hasActualFertilizerEntries ? sumFertilizerQuantity($coverageEntries, 'fertilizer_ohn') : (float) ($row['fertilizer_ohn_actual'] ?? 0)),
        'fertilizer_concoction_target' => formatSummaryNumber($hasTargetFertilizerEntries ? sumFertilizerQuantity($targetEntries, 'fertilizer_concoction') : (float) ($row['fertilizer_concoction_target'] ?? 0)),
        'fertilizer_concoction_actual' => formatSummaryNumber($hasActualFertilizerEntries ? sumFertilizerQuantity($coverageEntries, 'fertilizer_concoction') : (float) ($row['fertilizer_concoction_actual'] ?? 0)),
        'fertilizer_vermicompost_target' => formatSummaryNumber($hasTargetFertilizerEntries ? sumFertilizerQuantity($targetEntries, 'fertilizer_vermicompost') : (float) ($row['fertilizer_vermicompost_target'] ?? 0)),
        'fertilizer_vermicompost_actual' => formatSummaryNumber($hasActualFertilizerEntries ? sumFertilizerQuantity($coverageEntries, 'fertilizer_vermicompost') : (float) ($row['fertilizer_vermicompost_actual'] ?? 0)),
        'binhi_sites_established_target' => (int) ($row['binhi_sites_established_target'] ?? 0),
        'binhi_sites_established_actual' => (int) ($row['binhi_sites_established_actual'] ?? 0),
        'binhi_facilities_added_target' => (int) ($row['binhi_facilities_added_target'] ?? 0),
        'binhi_facilities_added_actual' => (int) ($row['binhi_facilities_added_actual'] ?? 0),
        'area_land_utilized_target' => formatSummaryNumber((float) ($row['area_land_utilized_target'] ?? 0)),
        'area_land_utilized_actual' => sumLandAreaFromEntries($coverageEntries),
    ]);
}
$stmt->close();

echo json_encode(['data' => $data]);
