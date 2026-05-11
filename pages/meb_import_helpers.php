<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app_notification_helpers.php';
require_once __DIR__ . '/../socket_helpers.php';
require_once __DIR__ . '/../base_url.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function meb_import_jobs_dir(): string
{
    $preferredDir = __DIR__ . '/meb_import_jobs';
    if (meb_import_prepare_jobs_dir($preferredDir)) {
        return $preferredDir;
    }

    $fallbackDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kodus-meb-import-jobs';
    if (meb_import_prepare_jobs_dir($fallbackDir)) {
        error_log(sprintf('MEB import jobs folder is not writable; using fallback %s', $fallbackDir));
        return $fallbackDir;
    }

    throw new RuntimeException('The MEB import job folder is not writable.');
}

function meb_import_prepare_jobs_dir(string $dir): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    @chmod($dir, 02775);

    return is_writable($dir);
}

function meb_import_ensure_schema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS meb_import_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_token VARCHAR(32) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'queued',
            progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
            current_step VARCHAR(191) NOT NULL DEFAULT 'Queued',
            source_path VARCHAR(1024) NOT NULL,
            source_filename VARCHAR(255) NOT NULL,
            batch_id VARCHAR(16) DEFAULT NULL,
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT NULL,
            requested_by INT NULL,
            actor_name VARCHAR(255) DEFAULT NULL,
            generated_import_token VARCHAR(32) DEFAULT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            failed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_meb_import_job_token (job_token),
            INDEX idx_meb_import_jobs_requested_by (requested_by),
            INDEX idx_meb_import_jobs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $checked = true;
}

function meb_import_normalize_header($value): string
{
    $value = strtoupper(trim((string) $value));
    $value = str_replace(["\n", "\r", "\t"], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function meb_import_expected_columns(): array
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

function meb_import_headers_match(array $fileColumns, array $expectedColumns): bool
{
    if (count($fileColumns) !== count($expectedColumns)) {
        return false;
    }

    foreach ($expectedColumns as $index => $definition) {
        $actual = meb_import_normalize_header($fileColumns[$index] ?? '');
        $aliases = array_map('meb_import_normalize_header', $definition['aliases'] ?? []);
        if ($actual === '' || !in_array($actual, $aliases, true)) {
            return false;
        }
    }

    return true;
}

function meb_import_birthdate_value($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        $numeric = (float) $value;
        if ($numeric >= 1 && $numeric <= 60000) {
            try {
                return ExcelDate::excelToDateTimeObject($numeric)->format('Y-m-d');
            } catch (Throwable $e) {
            }
        }
    }

    $timestamp = strtotime(trim((string) $value));
    return $timestamp === false ? null : date('Y-m-d', $timestamp);
}

function meb_import_normalize_fourps($value): string
{
    $normalized = strtoupper(trim((string) $value));
    if (in_array($normalized, ['G', 'GRADUATED', 'GRADUATE'], true) || str_contains($normalized, 'GRADUATED')) {
        return 'G';
    }
    if (in_array($normalized, ['M', 'MEMBER', 'ACTIVE', 'BENEFICIARY', 'YES', 'Y', 'TRUE', '1', '✓', 'ÂŒ“', 'Ã¢Å“â€œ'], true) || str_contains($normalized, 'MEMBER')) {
        return 'M';
    }
    return '';
}

function meb_import_next_batch_id(mysqli $conn): string
{
    $result = $conn->query('SELECT MAX(batch_id) AS latest_batch_id FROM meb');
    $latestBatchId = 10001;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!is_null($row['latest_batch_id'] ?? null)) {
            $latestBatchId = (int) $row['latest_batch_id'] + 1;
        }
    }
    if (strlen((string) $latestBatchId) > 5) {
        throw new RuntimeException('Batch ID overflow. Please reset the database.');
    }
    return str_pad((string) $latestBatchId, 5, '0', STR_PAD_LEFT);
}

function meb_import_update_job(mysqli $conn, string $jobToken, array $fields): void
{
    meb_import_ensure_schema($conn);
    $allowed = [
        'status' => 's', 'progress' => 'i', 'current_step' => 's', 'batch_id' => 's',
        'row_count' => 'i', 'message' => 's', 'started_at' => 'raw', 'finished_at' => 'raw', 'failed_at' => 'raw',
    ];
    $sets = [];
    $types = '';
    $values = [];
    foreach ($fields as $field => $value) {
        if (!isset($allowed[$field])) {
            continue;
        }
        if ($allowed[$field] === 'raw') {
            $sets[] = $field . ' = NOW()';
            continue;
        }
        $sets[] = $field . ' = ?';
        $types .= $allowed[$field];
        $values[] = $allowed[$field] === 'i' ? (int) $value : (string) $value;
    }
    if ($sets === []) {
        return;
    }
    $types .= 's';
    $values[] = $jobToken;
    $stmt = $conn->prepare('UPDATE meb_import_jobs SET ' . implode(', ', $sets) . ' WHERE job_token = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $bindValues = [$types];
    foreach ($values as $index => $value) {
        $bindValues[] = &$values[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);
    $stmt->execute();
    $stmt->close();
}

function meb_import_process_file(mysqli $conn, string $filePath, string $originalName, ?string $jobToken = null, ?int $actorUserId = null, ?string $actorName = null, ?string $generatedImportToken = null): array
{
    if (!is_file($filePath)) {
        throw new RuntimeException('Uploaded workbook is no longer available.');
    }
    if (!in_array(strtolower(pathinfo($originalName, PATHINFO_EXTENSION)), ['xls', 'xlsx'], true)) {
        throw new RuntimeException('Invalid file type. Please upload an Excel file (.xls or .xlsx).');
    }

    $batchId = meb_import_next_batch_id($conn);
    if ($jobToken) {
        meb_import_update_job($conn, $jobToken, ['progress' => 16, 'current_step' => 'Reading workbook', 'message' => 'Reading the uploaded Excel workbook.', 'batch_id' => $batchId]);
    }

    $expectedColumns = meb_import_expected_columns();
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $expectedColumnCount = count($expectedColumns);
    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = Coordinate::stringFromColumnIndex($expectedColumnCount);
    $data = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, null, true, true, false);
    $spreadsheet->disconnectWorksheets();

    $fileColumns = array_map(static fn($column) => is_null($column) ? '' : trim((string) $column), $data[0] ?? []);
    if (!meb_import_headers_match($fileColumns, $expectedColumns)) {
        throw new RuntimeException('Column mismatch! Expected columns: ' . implode(', ', array_column($expectedColumns, 'label')) . '.');
    }

    if ($jobToken) {
        meb_import_update_job($conn, $jobToken, ['progress' => 28, 'current_step' => 'Saving records', 'message' => 'Validated headers. Saving MEB records...']);
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
    $totalRows = max(1, count($data) - 1);
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
        $birthDateValue = meb_import_birthdate_value($row[8] ?? null);
        $age = isset($row[9]) ? (int) $row[9] : 0;
        $sex = isset($row[10]) ? trim((string) $row[10]) : '';
        $civilStatus = isset($row[11]) ? trim((string) $row[11]) : '';
        $nhts1 = isset($row[12]) ? trim((string) $row[12]) : '';
        $nhts2 = isset($row[13]) ? trim((string) $row[13]) : '';
        $fourPs = meb_import_normalize_fourps($row[14] ?? '');
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

        $insertStmt->bind_param('sssssssssissssssssssssssssssi', $lastName, $firstName, $middleName, $ext, $purok, $barangay, $lgu, $province, $birthDateValue, $age, $sex, $civilStatus, $nhts1, $nhts2, $fourPs, $F, $FF, $IS, $IP, $SC, $SP, $LW, $PW, $PWD, $OSY, $FR, $ybDs, $lgbtqia, $batchIdValue);
        if (!$insertStmt->execute()) {
            $insertStmt->close();
            throw new RuntimeException('Import failed while saving one of the rows.');
        }
        $rowCount++;

        if ($jobToken && ($rowCount % 50 === 0 || $rowCount === $totalRows)) {
            meb_import_update_job($conn, $jobToken, [
                'progress' => min(92, 28 + (int) floor(($rowCount / $totalRows) * 62)),
                'row_count' => $rowCount,
                'message' => sprintf('Saved %d of %d rows...', $rowCount, $totalRows),
            ]);
        }
    }
    $insertStmt->close();

    if ($rowCount <= 0) {
        throw new RuntimeException('No data was imported. Please check your file.');
    }

    if ($generatedImportToken) {
        require_once __DIR__ . '/../mebis-lgu-template/helpers/history.php';
        mebis_template_mark_output_imported($conn, $generatedImportToken, (string) $batchId, $actorUserId);
    }

    $actorName = trim((string) $actorName) !== '' ? (string) $actorName : 'KODUS';
    app_notification_create($conn, [
        'category' => 'meb',
        'title' => 'MEB batch imported',
        'message' => $actorName . " imported {$rowCount} MEB records in batch {$batchId}.",
        'url' => app_notification_build_url('pages/meb-batch-summary?batch_id=' . rawurlencode((string) $batchId)),
        'icon_class' => 'fas fa-file-import',
        'color_class' => 'text-success',
        'actor_user_id' => $actorUserId,
        'target_user_id' => $actorUserId,
        'actor_name' => $actorName,
    ]);
    kodus_socket_broadcast('kodus.meb', 'meb.changed', ['action' => 'imported', 'batch_id' => (string) $batchId, 'row_count' => $rowCount, 'actor_id' => (int) ($actorUserId ?? 0)]);
    kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', ['action' => 'imported', 'batch_id' => (string) $batchId, 'row_count' => $rowCount, 'actor_id' => (int) ($actorUserId ?? 0)]);

    return ['batch_id' => (string) $batchId, 'row_count' => $rowCount];
}

function meb_import_create_job(mysqli $conn, array $file, int $requestedBy, string $actorName, ?string $generatedImportToken = null): string
{
    meb_import_ensure_schema($conn);
    $dir = meb_import_jobs_dir();
    $originalName = (string) ($file['name'] ?? 'MEB import.xlsx');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['xls', 'xlsx'], true)) {
        throw new RuntimeException('Invalid file type. Please upload an Excel file (.xls or .xlsx).');
    }

    $token = bin2hex(random_bytes(16));
    $storedPath = $dir . '/' . $token . '.' . $extension;
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $moved = is_uploaded_file($tmpName) ? move_uploaded_file($tmpName, $storedPath) : copy($tmpName, $storedPath);
    if (!$moved) {
        throw new RuntimeException('Unable to store the workbook for background import.');
    }

    $stmt = $conn->prepare("
        INSERT INTO meb_import_jobs
            (job_token, status, progress, current_step, source_path, source_filename, requested_by, actor_name, generated_import_token, message)
        VALUES (?, 'queued', 5, 'Queued', ?, ?, ?, ?, ?, 'Waiting for the background importer to start.')
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to create the MEB import job.');
    }
    $stmt->bind_param('sssiss', $token, $storedPath, $originalName, $requestedBy, $actorName, $generatedImportToken);
    $stmt->execute();
    $stmt->close();

    return $token;
}

function meb_import_find_php_binary(): string
{
    foreach ([PHP_BINDIR . DIRECTORY_SEPARATOR . 'php', PHP_BINARY] as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }
    return 'php';
}

function meb_import_start_background_job(string $jobToken): bool
{
    $command = escapeshellarg(meb_import_find_php_binary()) . ' ' . escapeshellarg(__DIR__ . '/meb_import_worker.php') . ' ' . escapeshellarg($jobToken) . ' > /dev/null 2>&1 &';
    $handle = @popen($command, 'r');
    if (!is_resource($handle)) {
        return false;
    }
    pclose($handle);
    return true;
}

function meb_import_get_job(mysqli $conn, string $jobToken): ?array
{
    meb_import_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT * FROM meb_import_jobs WHERE job_token = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $jobToken);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();
    return $row ?: null;
}

function meb_import_get_job_for_user(mysqli $conn, string $jobToken, int $userId): ?array
{
    meb_import_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT * FROM meb_import_jobs WHERE job_token = ? AND requested_by = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('si', $jobToken, $userId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();
    return $row ?: null;
}

function meb_import_latest_job_for_user(mysqli $conn, int $userId): ?array
{
    meb_import_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT * FROM meb_import_jobs WHERE requested_by = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();
    return $row ?: null;
}

function meb_import_job_payload(array $job): array
{
    return [
        'job_token' => (string) ($job['job_token'] ?? ''),
        'status' => (string) ($job['status'] ?? 'queued'),
        'progress' => max(0, min(100, (int) ($job['progress'] ?? 0))),
        'current_step' => (string) ($job['current_step'] ?? 'Queued'),
        'message' => (string) ($job['message'] ?? ''),
        'batch_id' => (string) ($job['batch_id'] ?? ''),
        'row_count' => (int) ($job['row_count'] ?? 0),
        'batch_url' => !empty($job['batch_id']) ? app_url('pages/meb-batch-summary?batch_id=' . rawurlencode((string) $job['batch_id'])) : '',
    ];
}

function meb_import_run_job(mysqli $conn, string $jobToken): void
{
    $job = meb_import_get_job($conn, $jobToken);
    if (!$job || (string) ($job['status'] ?? '') !== 'queued') {
        return;
    }

    try {
        meb_import_update_job($conn, $jobToken, ['status' => 'processing', 'progress' => 10, 'current_step' => 'Starting import', 'message' => 'Starting background MEB import...', 'started_at' => true]);
        $result = meb_import_process_file(
            $conn,
            (string) $job['source_path'],
            (string) $job['source_filename'],
            $jobToken,
            isset($job['requested_by']) ? (int) $job['requested_by'] : null,
            (string) ($job['actor_name'] ?? 'KODUS'),
            (string) ($job['generated_import_token'] ?? '')
        );
        meb_import_update_job($conn, $jobToken, [
            'status' => 'completed',
            'progress' => 100,
            'current_step' => 'Completed',
            'batch_id' => $result['batch_id'],
            'row_count' => $result['row_count'],
            'message' => 'Import complete. Batch ID: ' . $result['batch_id'],
            'finished_at' => true,
        ]);
    } catch (Throwable $e) {
        meb_import_update_job($conn, $jobToken, ['status' => 'failed', 'progress' => 100, 'current_step' => 'Failed', 'message' => $e->getMessage(), 'failed_at' => true, 'finished_at' => true]);
        app_notification_create($conn, [
            'category' => 'meb',
            'title' => 'MEB import failed',
            'message' => 'MEB import failed: ' . $e->getMessage(),
            'url' => app_notification_build_url('pages/data-tracking-meb'),
            'icon_class' => 'fas fa-exclamation-triangle',
            'color_class' => 'text-danger',
            'target_user_id' => isset($job['requested_by']) ? (int) $job['requested_by'] : null,
            'actor_name' => 'KODUS',
        ]);
        throw $e;
    } finally {
        $path = (string) ($job['source_path'] ?? '');
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
