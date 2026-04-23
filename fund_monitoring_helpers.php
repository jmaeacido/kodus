<?php

function fund_monitoring_default_object_codes(): array
{
    return [
        'Travel Expenses - Local',
        'Training Expenses',
        'Office Supplies Expenses',
        'Gasoline, Oil and Lubricants Expenses',
        'SE-ICT Equipment',
        'SE-Furniture and Fixtures',
        'Other Supplies Expenses',
        'Other Professional Expenses',
        'RM - Buildings',
        'Subsidies - Others',
        'Fidelity Bond Premiums',
        'Advertising Expenses',
        'Printing & Publication Expenses',
        'Representation Expenses',
        'Auditing Services',
        'Rents - Motor Vehicles',
        'Other MOOE',
    ];
}

function fund_monitoring_default_budget_map(): array
{
    return [
        'Travel Expenses - Local' => 1138000.00,
        'Training Expenses' => 3330100.00,
        'Office Supplies Expenses' => 100000.00,
        'Gasoline, Oil and Lubricants Expenses' => 150000.00,
        'SE-ICT Equipment' => 75000.00,
        'SE-Furniture and Fixtures' => 150000.00,
        'Other Supplies Expenses' => 50000.00,
        'Other Professional Expenses' => 12298906.18,
        'RM - Buildings' => 100000.00,
        'Subsidies - Others' => 53896500.00,
        'Fidelity Bond Premiums' => 100000.00,
        'Advertising Expenses' => 50000.00,
        'Printing & Publication Expenses' => 100000.00,
        'Representation Expenses' => 674400.00,
        'Auditing Services' => 50000.00,
        'Rents - Motor Vehicles' => 500000.00,
        'Other MOOE' => 69659.09,
    ];
}

function fund_monitoring_default_saro_number(): string
{
    return 'DRRP-CC-2026-CARAGA-16';
}

function fund_monitoring_default_pap_name(): string
{
    return 'Shared PAP';
}

function fund_monitoring_month_labels(): array
{
    return [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];
}

function fund_monitoring_ensure_schema(mysqli $conn): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS fund_monitoring_object_codes (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fiscal_year INT NOT NULL,
            object_code_name VARCHAR(190) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fund_monitoring_code_year_name (fiscal_year, object_code_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS fund_monitoring_items (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fiscal_year INT NOT NULL,
            saro_number VARCHAR(120) NOT NULL,
            pap_name VARCHAR(255) NOT NULL,
            object_code_name VARCHAR(190) NOT NULL,
            authorized_appropriation DECIMAL(16,2) NOT NULL DEFAULT 0.00,
            realignment DECIMAL(16,2) NOT NULL DEFAULT 0.00,
            display_order INT NOT NULL DEFAULT 0,
            reason_obligation TEXT DEFAULT NULL,
            reason_disbursement TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_fund_monitoring_items_year (fiscal_year),
            KEY idx_fund_monitoring_items_saro (fiscal_year, saro_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS fund_monitoring_entries (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            entry_month TINYINT NOT NULL,
            obligations DECIMAL(16,2) NOT NULL DEFAULT 0.00,
            disbursement DECIMAL(16,2) NOT NULL DEFAULT 0.00,
            updated_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fund_monitoring_item_month (item_id, entry_month),
            CONSTRAINT fk_fund_monitoring_entry_item
                FOREIGN KEY (item_id) REFERENCES fund_monitoring_items(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $initialized = true;
}

function fund_monitoring_seed_object_codes(mysqli $conn, int $year, ?int $userId = null): void
{
    fund_monitoring_ensure_schema($conn);

    $codes = fund_monitoring_default_object_codes();
    $stmt = $conn->prepare("
        INSERT INTO fund_monitoring_object_codes (fiscal_year, object_code_name, created_by, updated_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_active = 1,
            updated_by = VALUES(updated_by)
    ");

    if (!$stmt) {
        return;
    }

    foreach ($codes as $code) {
        $stmt->bind_param('isii', $year, $code, $userId, $userId);
        $stmt->execute();
    }

    $stmt->close();
}

function fund_monitoring_seed_budget_items(mysqli $conn, int $year, ?int $userId = null): void
{
    fund_monitoring_ensure_schema($conn);
    fund_monitoring_seed_object_codes($conn, $year, $userId);

    $defaultSaroNumber = fund_monitoring_default_saro_number();
    $defaultPapName = fund_monitoring_default_pap_name();

    $refreshSeedStmt = $conn->prepare("
        UPDATE fund_monitoring_items
        SET saro_number = ?,
            pap_name = CASE
                WHEN pap_name = 'Unassigned Program / Activity / Project' OR pap_name = '' THEN ?
                ELSE pap_name
            END,
            updated_by = ?
        WHERE fiscal_year = ?
          AND saro_number = 'Pending SARO'
    ");

    if ($refreshSeedStmt) {
        $refreshSeedStmt->bind_param('ssii', $defaultSaroNumber, $defaultPapName, $userId, $year);
        $refreshSeedStmt->execute();
        $refreshSeedStmt->close();
    }

    $checkStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM fund_monitoring_items
        WHERE fiscal_year = ?
    ");

    if (!$checkStmt) {
        return;
    }

    $checkStmt->bind_param('i', $year);
    $checkStmt->execute();
    $result = db_stmt_fetch_one_assoc($checkStmt);
    $count = (int) (($result['total'] ?? 0));

    $checkStmt->close();

    if ($count > 0) {
        return;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO fund_monitoring_items (
            fiscal_year, saro_number, pap_name, object_code_name, authorized_appropriation, realignment,
            reason_obligation, reason_disbursement, display_order, created_by, updated_by
        )
        VALUES (?, ?, ?, ?, ?, 0.00, '', '', ?, ?, ?)
    ");

    if (!$insertStmt) {
        return;
    }

    $saroNumber = $defaultSaroNumber;
    $papName = $defaultPapName;
    $displayOrder = 1;

    foreach (fund_monitoring_default_budget_map() as $objectCodeName => $amount) {
        $insertStmt->bind_param(
            'isssdiii',
            $year,
            $saroNumber,
            $papName,
            $objectCodeName,
            $amount,
            $displayOrder,
            $userId,
            $userId
        );
        $insertStmt->execute();
        $displayOrder++;
    }

    $insertStmt->close();
}

function fund_monitoring_list_object_codes(mysqli $conn, int $year): array
{
    fund_monitoring_seed_object_codes($conn, $year);

    $rows = [];
    $stmt = $conn->prepare("
        SELECT id, object_code_name
        FROM fund_monitoring_object_codes
        WHERE fiscal_year = ? AND is_active = 1
        ORDER BY object_code_name ASC
    ");

    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param('i', $year);
    $stmt->execute();
    foreach (db_stmt_fetch_all_assoc($stmt) as $row) {
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'object_code_name' => (string) ($row['object_code_name'] ?? ''),
        ];
    }

    $stmt->close();

    return $rows;
}

function fund_monitoring_add_object_code(mysqli $conn, int $year, string $objectCodeName, ?int $userId = null): bool
{
    fund_monitoring_ensure_schema($conn);

    $objectCodeName = trim($objectCodeName);
    if ($objectCodeName === '') {
        return false;
    }

    $stmt = $conn->prepare("
        INSERT INTO fund_monitoring_object_codes (fiscal_year, object_code_name, created_by, updated_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_active = 1,
            updated_by = VALUES(updated_by)
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('isii', $year, $objectCodeName, $userId, $userId);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function fund_monitoring_normalize_amount($value): float
{
    if ($value === null) {
        return 0.0;
    }

    $normalized = str_replace(',', '', trim((string) $value));
    if ($normalized === '' || !is_numeric($normalized)) {
        return 0.0;
    }

    return (float) $normalized;
}

function fund_monitoring_get_item(mysqli $conn, int $id): ?array
{
    fund_monitoring_ensure_schema($conn);

    $stmt = $conn->prepare("
        SELECT id, fiscal_year, saro_number, pap_name, object_code_name, authorized_appropriation, realignment,
               display_order, reason_obligation, reason_disbursement, is_active, created_at, updated_at
        FROM fund_monitoring_items
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = db_stmt_fetch_one_assoc($stmt);

    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'fiscal_year' => (int) $row['fiscal_year'],
        'saro_number' => (string) $row['saro_number'],
        'pap_name' => (string) $row['pap_name'],
        'object_code_name' => (string) $row['object_code_name'],
        'authorized_appropriation' => (float) $row['authorized_appropriation'],
        'realignment' => (float) $row['realignment'],
        'display_order' => (int) $row['display_order'],
        'reason_obligation' => (string) ($row['reason_obligation'] ?? ''),
        'reason_disbursement' => (string) ($row['reason_disbursement'] ?? ''),
        'is_active' => (int) $row['is_active'],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function fund_monitoring_save_item(
    mysqli $conn,
    int $year,
    string $saroNumber,
    string $papName,
    string $objectCodeName,
    float $authorizedAppropriation,
    float $realignment,
    string $reasonObligation,
    string $reasonDisbursement,
    int $displayOrder,
    ?int $userId = null,
    ?int $recordId = null
): bool {
    fund_monitoring_ensure_schema($conn);

    $saroNumber = trim($saroNumber);
    $papName = trim($papName);
    $objectCodeName = trim($objectCodeName);

    if ($saroNumber === '' || $papName === '' || $objectCodeName === '') {
        return false;
    }

    if (!fund_monitoring_add_object_code($conn, $year, $objectCodeName, $userId)) {
        return false;
    }

    if ($recordId !== null && $recordId > 0) {
        $stmt = $conn->prepare("
            UPDATE fund_monitoring_items
            SET saro_number = ?, pap_name = ?, object_code_name = ?, authorized_appropriation = ?, realignment = ?,
                reason_obligation = ?, reason_disbursement = ?, display_order = ?, updated_by = ?
            WHERE id = ? AND fiscal_year = ?
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'sssddssiiii',
            $saroNumber,
            $papName,
            $objectCodeName,
            $authorizedAppropriation,
            $realignment,
            $reasonObligation,
            $reasonDisbursement,
            $displayOrder,
            $userId,
            $recordId,
            $year
        );
    } else {
        $stmt = $conn->prepare("
            INSERT INTO fund_monitoring_items (
                fiscal_year, saro_number, pap_name, object_code_name, authorized_appropriation, realignment,
                reason_obligation, reason_disbursement, display_order, created_by, updated_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'isssddssiii',
            $year,
            $saroNumber,
            $papName,
            $objectCodeName,
            $authorizedAppropriation,
            $realignment,
            $reasonObligation,
            $reasonDisbursement,
            $displayOrder,
            $userId,
            $userId
        );
    }

    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function fund_monitoring_save_month_entries(mysqli $conn, int $year, int $month, array $entries, ?int $userId = null): bool
{
    fund_monitoring_ensure_schema($conn);

    if ($month < 1 || $month > 12) {
        return false;
    }

    $lookupStmt = $conn->prepare("
        SELECT id
        FROM fund_monitoring_items
        WHERE id = ? AND fiscal_year = ? AND is_active = 1
        LIMIT 1
    ");
    $upsertStmt = $conn->prepare("
        INSERT INTO fund_monitoring_entries (item_id, entry_month, obligations, disbursement, updated_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            obligations = VALUES(obligations),
            disbursement = VALUES(disbursement),
            updated_by = VALUES(updated_by)
    ");

    if (!$lookupStmt || !$upsertStmt) {
        return false;
    }

    $conn->begin_transaction();

    try {
        foreach ($entries as $entry) {
            $itemId = isset($entry['item_id']) ? (int) $entry['item_id'] : 0;
            if ($itemId <= 0) {
                continue;
            }

            $lookupStmt->bind_param('ii', $itemId, $year);
            $lookupStmt->execute();
            $result = db_stmt_fetch_one_assoc($lookupStmt);
            $exists = (bool) $result;

            if (!$exists) {
                continue;
            }

            $obligations = fund_monitoring_normalize_amount($entry['obligations'] ?? 0);
            $disbursement = fund_monitoring_normalize_amount($entry['disbursement'] ?? 0);

            $upsertStmt->bind_param('iiddi', $itemId, $month, $obligations, $disbursement, $userId);
            if (!$upsertStmt->execute()) {
                throw new RuntimeException('Unable to save monthly entries.');
            }
        }

        $conn->commit();
        $lookupStmt->close();
        $upsertStmt->close();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        $lookupStmt->close();
        $upsertStmt->close();
        return false;
    }
}

function fund_monitoring_list_items_with_entries(mysqli $conn, int $year): array
{
    fund_monitoring_seed_object_codes($conn, $year);

    $items = [];
    $stmt = $conn->prepare("
        SELECT id, fiscal_year, saro_number, pap_name, object_code_name, authorized_appropriation, realignment,
               display_order, reason_obligation, reason_disbursement
        FROM fund_monitoring_items
        WHERE fiscal_year = ? AND is_active = 1
        ORDER BY display_order ASC, saro_number ASC, pap_name ASC, object_code_name ASC, id ASC
    ");

    if (!$stmt) {
        return $items;
    }

    $stmt->bind_param('i', $year);
    $stmt->execute();
    foreach (db_stmt_fetch_all_assoc($stmt) as $row) {
        $id = (int) $row['id'];
        $items[$id] = [
            'id' => $id,
            'fiscal_year' => (int) $row['fiscal_year'],
            'saro_number' => (string) $row['saro_number'],
            'pap_name' => (string) $row['pap_name'],
            'object_code_name' => (string) $row['object_code_name'],
            'authorized_appropriation' => (float) $row['authorized_appropriation'],
            'realignment' => (float) $row['realignment'],
            'adjusted_appropriation' => (float) $row['authorized_appropriation'] + (float) $row['realignment'],
            'display_order' => (int) $row['display_order'],
            'reason_obligation' => (string) ($row['reason_obligation'] ?? ''),
            'reason_disbursement' => (string) ($row['reason_disbursement'] ?? ''),
            'monthly' => [],
        ];
    }

    $stmt->close();

    if ($items === []) {
        return [];
    }

    $entryResult = $conn->query("
        SELECT item_id, entry_month, obligations, disbursement
        FROM fund_monitoring_entries
        WHERE item_id IN (" . implode(',', array_map('intval', array_keys($items))) . ")
    ");

    if ($entryResult instanceof mysqli_result) {
        while ($row = $entryResult->fetch_assoc()) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $entryMonth = (int) ($row['entry_month'] ?? 0);
            if (!isset($items[$itemId]) || $entryMonth < 1 || $entryMonth > 12) {
                continue;
            }

            $items[$itemId]['monthly'][$entryMonth] = [
                'obligations' => (float) ($row['obligations'] ?? 0),
                'disbursement' => (float) ($row['disbursement'] ?? 0),
            ];
        }
        $entryResult->free();
    }

    foreach ($items as &$item) {
        for ($month = 1; $month <= 12; $month++) {
            if (!isset($item['monthly'][$month])) {
                $item['monthly'][$month] = [
                    'obligations' => 0.0,
                    'disbursement' => 0.0,
                ];
            }
        }
        ksort($item['monthly']);
    }
    unset($item);

    return array_values($items);
}

function fund_monitoring_change_token(mysqli $conn, int $year): string
{
    fund_monitoring_ensure_schema($conn);

    $itemTotals = [
        'count' => 0,
        'authorized_sum' => '0.00',
        'realignment_sum' => '0.00',
        'latest' => '',
    ];
    $entryTotals = [
        'count' => 0,
        'obligations_sum' => '0.00',
        'disbursement_sum' => '0.00',
        'latest' => '',
    ];

    $itemStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(authorized_appropriation), 0.00) AS authorized_sum,
            COALESCE(SUM(realignment), 0.00) AS realignment_sum,
            COALESCE(DATE_FORMAT(MAX(updated_at), '%Y-%m-%d %H:%i:%s'), '') AS latest_update
        FROM fund_monitoring_items
        WHERE fiscal_year = ? AND is_active = 1
    ");

    if ($itemStmt) {
        $itemStmt->bind_param('i', $year);
        $itemStmt->execute();
        $row = db_stmt_fetch_one_assoc($itemStmt) ?: [];
        $itemTotals = [
            'count' => (int) ($row['total_count'] ?? 0),
            'authorized_sum' => (string) ($row['authorized_sum'] ?? '0.00'),
            'realignment_sum' => (string) ($row['realignment_sum'] ?? '0.00'),
            'latest' => (string) ($row['latest_update'] ?? ''),
        ];
        $itemStmt->close();
    }

    $entryStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(entries.obligations), 0.00) AS obligations_sum,
            COALESCE(SUM(entries.disbursement), 0.00) AS disbursement_sum,
            COALESCE(DATE_FORMAT(MAX(entries.updated_at), '%Y-%m-%d %H:%i:%s'), '') AS latest_update
        FROM fund_monitoring_entries AS entries
        INNER JOIN fund_monitoring_items AS items ON items.id = entries.item_id
        WHERE items.fiscal_year = ? AND items.is_active = 1
    ");

    if ($entryStmt) {
        $entryStmt->bind_param('i', $year);
        $entryStmt->execute();
        $row = db_stmt_fetch_one_assoc($entryStmt) ?: [];
        $entryTotals = [
            'count' => (int) ($row['total_count'] ?? 0),
            'obligations_sum' => (string) ($row['obligations_sum'] ?? '0.00'),
            'disbursement_sum' => (string) ($row['disbursement_sum'] ?? '0.00'),
            'latest' => (string) ($row['latest_update'] ?? ''),
        ];
        $entryStmt->close();
    }

    return hash('sha256', json_encode([
        'year' => $year,
        'items' => $itemTotals,
        'entries' => $entryTotals,
    ]));
}
