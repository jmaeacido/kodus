<?php

declare(strict_types=1);

require_once __DIR__ . '/history.php';
require_once __DIR__ . '/../../socket_helpers.php';
require_once __DIR__ . '/../../app_notification_helpers.php';
require_once __DIR__ . '/../../base_url.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function mebis_generated_import_normalize_header($value): string
{
    $value = strtoupper(trim((string) $value));
    $value = str_replace(["\n", "\r", "\t"], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function mebis_generated_import_expected_columns(): array
{
    return [
        ['label' => 'LAST NAME', 'aliases' => ['LAST NAME']],
        ['label' => 'FIRST NAME', 'aliases' => ['FIRST NAME']],
        ['label' => 'MIDDLE NAME', 'aliases' => ['MIDDLE NAME']],
        ['label' => 'EXT.', 'aliases' => ['EXT.']],
        ['label' => 'PUROK', 'aliases' => ['PUROK']],
        ['label' => 'BARANGAY', 'aliases' => ['BARANGAY']],
        ['label' => 'LGU', 'aliases' => ['LGU']],
        ['label' => 'PROVINCE', 'aliases' => ['PROVINCE']],
        ['label' => 'BIRTHDATE', 'aliases' => ['BIRTHDATE']],
        ['label' => 'AGE', 'aliases' => ['AGE']],
        ['label' => 'SEX', 'aliases' => ['SEX']],
        ['label' => 'CIVIL STATUS', 'aliases' => ['CIVIL STATUS']],
        ['label' => 'POOR BASED ON NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) Listahanan 3 (P)', 'aliases' => ['POOR BASED ON NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) LISTAHANAN 3 (P)', 'NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) POOR']],
        ['label' => 'IDENTIFIED POOR, MARGINALIZED & DISADVANTAGED BASED ON THE ASSESSMENT OF LSWDO (NON)', 'aliases' => ['IDENTIFIED POOR, MARGINALIZED & DISADVANTAGED BASED ON THE ASSESSMENT OF LSWDO (NON)', 'NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) NON-POOR BUT CONSIDERED POOR BY LSWDO ASSESSMENT']],
        ['label' => 'Pantawid Pamilyang Pilipino Program (4Ps)', 'aliases' => ['PANTAWID PAMILYANG PILIPINO PROGRAM (4PS)']],
        ['label' => 'Farmers (F)', 'aliases' => ['FARMERS (F)']],
        ['label' => 'Fisher-folks (FF)', 'aliases' => ['FISHER-FOLKS (FF)']],
        ['label' => 'Informal Sector (IS)', 'aliases' => ['INFORMAL SECTOR (IS)']],
        ['label' => 'Indigenous People (IP)', 'aliases' => ['INDIGENOUS PEOPLE (IP)']],
        ['label' => 'Senior Citizen (SC)', 'aliases' => ['SENIOR CITIZEN (SC)']],
        ['label' => 'Solo Parent (SP)', 'aliases' => ['SOLO PARENT (SP)']],
        ['label' => 'Lactating Women (LW)', 'aliases' => ['LACTATING WOMEN (LW)']],
        ['label' => 'Pregnant Women (PW)', 'aliases' => ['PREGNANT WOMEN (PW)']],
        ['label' => 'Persons with Disability (PWD)', 'aliases' => ['PERSONS WITH DISABILITY (PWD)']],
        ['label' => 'Out of School Youth (OSY)', 'aliases' => ['OUT OF SCHOOL YOUTH (OSY)', 'OUT-OF-SCHOOL YOUTH (OSY)']],
        ['label' => 'Former Rebel (FR)', 'aliases' => ['FORMER REBEL (FR)']],
        ['label' => 'YAKAP Bayan/ Person Who Used Drugs (YB/PWUD)', 'aliases' => ['YAKAP BAYAN/ PERSON WHO USED DRUGS (YB/PWUD)', 'YAKAP BAYAN/ DRUG SURENDEREE (YB/DS)']],
        ['label' => 'LGBTQIA+', 'aliases' => ['LGBTQIA+']],
    ];
}

function mebis_generated_import_headers_match(array $fileColumns, array $expectedColumns): bool
{
    if (count($fileColumns) !== count($expectedColumns)) {
        return false;
    }

    foreach ($expectedColumns as $index => $definition) {
        $actual = mebis_generated_import_normalize_header($fileColumns[$index] ?? '');
        $aliases = array_map('mebis_generated_import_normalize_header', $definition['aliases'] ?? []);
        if ($actual === '' || !in_array($actual, $aliases, true)) {
            return false;
        }
    }

    return true;
}

function mebis_generated_import_birthdate_value($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $numeric = (float) $value;
        if ($numeric >= 1 && $numeric <= 60000) {
            try {
                return ExcelDate::excelToDateTimeObject($numeric)->format('Y-m-d');
            } catch (Exception $e) {
            }
        }
    }

    $timestamp = strtotime(trim((string) $value));
    return $timestamp === false ? null : date('Y-m-d', $timestamp);
}

function mebis_generated_import_normalize_fourps($value): string
{
    $normalized = strtoupper(trim((string) $value));
    if ($normalized === 'G') {
        return 'G';
    }

    if ($normalized === 'M' || in_array($normalized, ['âœ“', 'Ã‚Å’â€œ', 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“', 'YES', 'Y', 'TRUE', '1'], true)) {
        return 'M';
    }

    return '';
}

function mebis_generated_import_next_batch_id(mysqli $conn): string
{
    $latestBatchId = 10001;
    $result = $conn->query('SELECT MAX(batch_id) AS latest_batch_id FROM meb');
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!is_null($row['latest_batch_id'])) {
            $latestBatchId = ((int) $row['latest_batch_id']) + 1;
        }
    }

    if (strlen((string) $latestBatchId) > 5) {
        throw new RuntimeException('Batch ID overflow. Please reset the database.');
    }

    return str_pad((string) $latestBatchId, 5, '0', STR_PAD_LEFT);
}

function mebis_generated_import_find_output_for_update(mysqli $conn, string $token): ?array
{
    mebis_template_history_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT id, output_token, filename, municipality_name, row_count, source_file, created_by,
               imported_at, imported_batch_id, imported_by, created_at
        FROM mebis_lgu_template_outputs
        WHERE output_token = ?
        LIMIT 1
        FOR UPDATE
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    if (!$row) {
        return null;
    }

    $filename = (string) ($row['filename'] ?? '');
    if ($filename === '' || !is_file(mebis_template_outputs_dir() . '/' . $filename)) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'token' => (string) ($row['output_token'] ?? ''),
        'filename' => $filename,
        'municipality_name' => (string) ($row['municipality_name'] ?? ''),
        'rows' => (int) ($row['row_count'] ?? 0),
        'source_file' => (string) ($row['source_file'] ?? ''),
        'created_by' => isset($row['created_by']) ? (int) ($row['created_by']) : null,
        'imported_at' => (string) ($row['imported_at'] ?? ''),
        'imported_batch_id' => (string) ($row['imported_batch_id'] ?? ''),
        'imported_by' => isset($row['imported_by']) ? (int) ($row['imported_by']) : null,
        'is_imported' => trim((string) ($row['imported_at'] ?? '')) !== '',
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function mebis_generated_import_output(mysqli $conn, string $token): array
{
    $token = preg_replace('/[^a-f0-9]/i', '', $token);
    if ($token === '') {
        throw new RuntimeException('No generated template was selected for import.');
    }

    $conn->begin_transaction();

    try {
    $entry = mebis_generated_import_find_output_for_update($conn, $token);
    if (!$entry) {
        throw new RuntimeException('Generated template file not found.');
    }

    if (!empty($entry['is_imported'])) {
        $conn->commit();
        return [
            'success' => true,
            'skipped' => true,
            'filename' => (string) $entry['filename'],
            'batch_id' => (string) ($entry['imported_batch_id'] ?? ''),
            'rows' => 0,
        ];
    }

    $path = mebis_template_outputs_dir() . '/' . $entry['filename'];
    if (!is_file($path)) {
        throw new RuntimeException('Generated template file is no longer available.');
    }

    $batchId = mebis_generated_import_next_batch_id($conn);
    $expectedColumns = mebis_generated_import_expected_columns();
    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $expectedColumnCount = count($expectedColumns);
    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = Coordinate::stringFromColumnIndex($expectedColumnCount);
    $data = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, null, true, true, false);
    $fileColumns = array_map(static function ($column) {
        return is_null($column) ? '' : trim((string) $column);
    }, $data[0] ?? []);

    if (!mebis_generated_import_headers_match($fileColumns, $expectedColumns)) {
        throw new RuntimeException('Column mismatch! Expected columns: ' . implode(', ', array_column($expectedColumns, 'label')) . '.');
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO meb (
            `lastName`, `firstName`, `middleName`, `ext`, `purok`, `barangay`, `lgu`, `province`,
            `birthDate`, `age`, `sex`, `civilStatus`, `nhts1`, `nhts2`, `fourPs`, `F`, `FF`, `IS`,
            `IP`, `SC`, `SP`, `LW`, `PW`, `PWD`, `OSY`, `FR`, `ybDs`, `lgbtqia`, `batch_id`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$insertStmt) {
        throw new RuntimeException('Unable to prepare the import statement.');
    }

    $rowCount = 0;
    foreach ($data as $index => $row) {
        if ($index === 0) {
            continue;
        }

        $lastName = isset($row[0]) ? trim((string) $row[0]) : '';
        $firstName = isset($row[1]) ? trim((string) $row[1]) : '';
        $middleName = isset($row[2]) ? trim((string) $row[2]) : '';
        $ext = isset($row[3]) ? trim((string) $row[3]) : '';
        $purok = isset($row[4]) ? trim((string) $row[4]) : '';
        $barangay = isset($row[5]) ? trim((string) $row[5]) : '';
        $lgu = isset($row[6]) ? trim((string) $row[6]) : '';
        $province = isset($row[7]) ? trim((string) $row[7]) : '';
        $birthDateValue = mebis_generated_import_birthdate_value($row[8] ?? null);
        $age = isset($row[9]) ? (int) $row[9] : 0;
        $sex = isset($row[10]) ? trim((string) $row[10]) : '';
        $civilStatus = isset($row[11]) ? trim((string) $row[11]) : '';
        $nhts1 = isset($row[12]) ? trim((string) $row[12]) : '';
        $nhts2 = isset($row[13]) ? trim((string) $row[13]) : '';
        $fourPs = mebis_generated_import_normalize_fourps($row[14] ?? '');
        $F = isset($row[15]) ? trim((string) $row[15]) : '';
        $FF = isset($row[16]) ? trim((string) $row[16]) : '';
        $IS = isset($row[17]) ? trim((string) $row[17]) : '';
        $IP = isset($row[18]) ? trim((string) $row[18]) : '';
        $SC = isset($row[19]) ? trim((string) $row[19]) : '';
        $SP = isset($row[20]) ? trim((string) $row[20]) : '';
        $LW = isset($row[21]) ? trim((string) $row[21]) : '';
        $PW = isset($row[22]) ? trim((string) $row[22]) : '';
        $PWD = isset($row[23]) ? trim((string) $row[23]) : '';
        $OSY = isset($row[24]) ? trim((string) $row[24]) : '';
        $FR = isset($row[25]) ? trim((string) $row[25]) : '';
        $ybDs = isset($row[26]) ? trim((string) $row[26]) : '';
        $lgbtqia = isset($row[27]) ? trim((string) $row[27]) : '';
        $batchIdValue = (int) $batchId;

        $insertStmt->bind_param(
            'sssssssssissssssssssssssssssi',
            $lastName,
            $firstName,
            $middleName,
            $ext,
            $purok,
            $barangay,
            $lgu,
            $province,
            $birthDateValue,
            $age,
            $sex,
            $civilStatus,
            $nhts1,
            $nhts2,
            $fourPs,
            $F,
            $FF,
            $IS,
            $IP,
            $SC,
            $SP,
            $LW,
            $PW,
            $PWD,
            $OSY,
            $FR,
            $ybDs,
            $lgbtqia,
            $batchIdValue
        );

        if ($insertStmt->execute() !== true) {
            $insertStmt->close();
            throw new RuntimeException('Import failed while saving one of the rows.');
        }

        $rowCount++;
    }
    $insertStmt->close();

    if ($rowCount <= 0) {
        throw new RuntimeException('No data was imported. Please check your file.');
    }

    mebis_template_mark_output_imported($conn, $token, $batchId, isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
    $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    app_notification_create($conn, [
        'category' => 'meb',
        'title' => 'MEB batch imported',
        'message' => app_notification_actor_name_from_session() . " imported {$rowCount} MEB records in batch {$batchId}.",
        'url' => app_notification_build_url('pages/meb-batch-summary?batch_id=' . rawurlencode($batchId)),
        'icon_class' => 'fas fa-file-import',
        'color_class' => 'text-success',
        'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
        'actor_name' => app_notification_actor_name_from_session(),
    ]);
    kodus_socket_broadcast('kodus.meb', 'meb.changed', [
        'action' => 'imported',
        'batch_id' => $batchId,
        'row_count' => $rowCount,
        'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
        'action' => 'imported',
        'batch_id' => $batchId,
        'row_count' => $rowCount,
        'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
    ]);

    return [
        'success' => true,
        'skipped' => false,
        'filename' => (string) $entry['filename'],
        'batch_id' => $batchId,
        'rows' => $rowCount,
    ];
}
