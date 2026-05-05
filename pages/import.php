<?php
    require_once __DIR__ . '/../security.php';
    security_bootstrap_session();
    security_configure_runtime_for_web();
    security_require_method(['POST']);
    security_require_csrf_token();
    require_once __DIR__ . '/../auth_helpers.php';
    require_once __DIR__ . '/../socket_helpers.php';
    require_once __DIR__ . '/../app_notification_helpers.php';
    require_once __DIR__ . '/../base_url.php';
    require_once __DIR__ . '/../config.php';

    require '../vendor/autoload.php'; // Load PhpSpreadsheet library
    use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
    use PhpOffice\PhpSpreadsheet\IOFactory;
    use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// Initialize variables for messages
$errorMsg = "";
$successMsg = "";

function meb_import_is_ajax_request(): bool {
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function meb_import_redirect_with_flash(string $type, string $message): void {
    $redirectUrl = app_url('pages/data-tracking-meb');

    $_SESSION['meb_import_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    if (meb_import_is_ajax_request()) {
        security_send_json([
            'success' => $type === 'success',
            'type' => $type,
            'message' => $message,
            'redirect' => $redirectUrl,
        ], $type === 'success' ? 200 : 400);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

function meb_import_normalize_header($value) {
    $value = strtoupper(trim((string)$value));
    $value = str_replace(["\n", "\r", "\t"], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function meb_import_expected_columns() {
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
        [
            'label' => 'POOR BASED ON NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) Listahanan 3 (P)',
            'aliases' => [
                'POOR BASED ON NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) LISTAHANAN 3 (P)',
                'NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) POOR',
            ],
        ],
        [
            'label' => 'IDENTIFIED POOR, MARGINALIZED & DISADVANTAGED BASED ON THE ASSESSMENT OF LSWDO (NON)',
            'aliases' => [
                'IDENTIFIED POOR, MARGINALIZED & DISADVANTAGED BASED ON THE ASSESSMENT OF LSWDO (NON)',
                'NATIONAL HOUSEHOLD TARGETING SYSTEM FOR POVERTY REDUCTION (NHTS-PR) NON-POOR BUT CONSIDERED POOR BY LSWDO ASSESSMENT',
            ],
        ],
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
        [
            'label' => 'Out of School Youth (OSY)',
            'aliases' => [
                'OUT OF SCHOOL YOUTH (OSY)',
                'OUT-OF-SCHOOL YOUTH (OSY)',
            ],
        ],
        ['label' => 'Former Rebel (FR)', 'aliases' => ['FORMER REBEL (FR)']],
        [
            'label' => 'YAKAP Bayan/ Person Who Used Drugs (YB/PWUD)',
            'aliases' => [
                'YAKAP BAYAN/ PERSON WHO USED DRUGS (YB/PWUD)',
                'YAKAP BAYAN/ DRUG SURENDEREE (YB/DS)',
            ],
        ],
        ['label' => 'LGBTQIA+', 'aliases' => ['LGBTQIA+']],
    ];
}

function meb_import_headers_match(array $fileColumns, array $expectedColumns): bool {
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

function meb_import_birthdate_value($value) {
    if ($value === null || $value === '') {
        return "NULL";
    }

    if (is_numeric($value)) {
        $numeric = (float)$value;
        if ($numeric >= 1 && $numeric <= 60000) {
            try {
                return ExcelDate::excelToDateTimeObject($numeric)->format('Y-m-d');
            } catch (Exception $e) {
            }
        }
    }

    $text = trim((string)$value);
    if ($text === '') {
        return "NULL";
    }

    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return "NULL";
    }

    return date("Y-m-d", $timestamp);
}

function meb_import_normalize_fourps($value): string {
    $normalized = strtoupper(trim((string) $value));

    if ($normalized === 'G') {
        return 'G';
    }

    if ($normalized === 'M' || in_array($normalized, ['✓', 'ÂŒ“', 'Ã¢Å“â€œ', 'YES', 'Y', 'TRUE', '1'], true)) {
        return 'M';
    }

    return '';
}

// Process posted import request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auth_handle_page_access($conn);
    auth_apply_security_headers();

    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        meb_import_redirect_with_flash('error', 'Access denied. Admins only.');
    }

    // Get the latest batch_id
    $sql = "SELECT MAX(batch_id) AS latest_batch_id FROM meb";
    $result = $conn->query($sql);
    $latestBatchId = 10001; // Default starting batch_id

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!is_null($row['latest_batch_id'])) {
            $latestBatchId = intval($row['latest_batch_id']) + 1;
        }
    }

    if (strlen($latestBatchId) > 5) {
        meb_import_redirect_with_flash('error', 'Batch ID overflow. Please reset the database.');
    }

    $batchId = str_pad($latestBatchId, 5, '0', STR_PAD_LEFT);

    // Define expected column headers and accepted legacy aliases
    $expectedColumns = meb_import_expected_columns();

    // Check if a file is uploaded
    if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['excelFile']['tmp_name'];
        $fileName = $_FILES['excelFile']['name'];
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($fileExtension, ['xls', 'xlsx'])) {
            try {
                $spreadsheet = IOFactory::load($fileTmpPath);
                $sheet = $spreadsheet->getActiveSheet();
                $expectedColumnCount = count($expectedColumns);
                $highestRow = $sheet->getHighestDataRow();
                $highestColumn = Coordinate::stringFromColumnIndex($expectedColumnCount);
                $data = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, null, true, true, false);

                // Validate column headers
                $fileColumns = array_map(function ($column) {
                    return is_null($column) ? '' : trim($column);
                }, $data[0]); // Get the first row as headers

                if (!meb_import_headers_match($fileColumns, $expectedColumns)) {
                    $errorMsg = "Column mismatch! Expected columns: " . implode(", ", array_column($expectedColumns, 'label')) . ".";
                } else {

                    // Skip the header row and insert data into the database
                    $isFirstRow = true;
                    $rowCount = 0;

                    $insertStmt = $conn->prepare(
                        "INSERT INTO meb (
                            `lastName`, `firstName`, `middleName`, `ext`, `purok`, `barangay`, `lgu`, `province`,
                            `birthDate`, `age`, `sex`, `civilStatus`, `nhts1`, `nhts2`, `fourPs`, `F`, `FF`, `IS`,
                            `IP`, `SC`, `SP`, `LW`, `PW`, `PWD`, `OSY`, `FR`, `ybDs`, `lgbtqia`, `batch_id`
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    if (!$insertStmt) {
                        $errorMsg = "Unable to prepare the import statement.";
                    }

                    foreach ($data as $row) {
                        if ($errorMsg !== '') {
                            break;
                        }

                        if ($isFirstRow) {
                            $isFirstRow = false;
                            continue;
                        }

                        // Map the Excel columns to database table columns
                        $lastName = isset($row[0]) ? trim((string) $row[0]) : '';
                        $firstName = isset($row[1]) ? trim((string) $row[1]) : '';
                        $middleName = isset($row[2]) ? trim((string) $row[2]) : '';
                        $ext = isset($row[3]) ? trim((string) $row[3]) : '';
                        $purok = isset($row[4]) ? trim((string) $row[4]) : '';
                        $barangay = isset($row[5]) ? trim((string) $row[5]) : '';
                        $lgu = isset($row[6]) ? trim((string) $row[6]) : '';
                        $province = isset($row[7]) ? trim((string) $row[7]) : '';
                        $birthDate = meb_import_birthdate_value($row[8] ?? null);  // Default to null if not set
                        $age = isset($row[9]) ? (int)$row[9] : 0;  // Default to 0 if not set
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

                        $birthDateValue = $birthDate !== "NULL" ? $birthDate : null;
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

                        if ($insertStmt->execute() === TRUE) {
                            $rowCount++;
                        } else {
                            $errorMsg = "Import failed while saving one of the rows.";
                        }
                    }

                    if ($insertStmt) {
                        $insertStmt->close();
                    }

                    if ($errorMsg === '' && $rowCount > 0) {
                        $successMsg = "Data imported successfully! Batch ID: $batchId";
                        $generatedImportToken = preg_replace('/[^a-f0-9]/i', '', (string) ($GLOBALS['mebis_generated_import_token'] ?? ''));
                        if ($generatedImportToken !== '') {
                            require_once __DIR__ . '/../mebis-lgu-template/helpers/history.php';
                            mebis_template_mark_output_imported(
                                $conn,
                                $generatedImportToken,
                                (string) $batchId,
                                isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
                            );
                        }

                        app_notification_create($conn, [
                            'category' => 'meb',
                            'title' => 'MEB batch imported',
                            'message' => app_notification_actor_name_from_session() . " imported {$rowCount} MEB records in batch {$batchId}.",
                            'url' => app_notification_build_url('pages/meb-batch-summary?batch_id=' . rawurlencode((string) $batchId)),
                            'icon_class' => 'fas fa-file-import',
                            'color_class' => 'text-success',
                            'actor_user_id' => (int) ($_SESSION['user_id'] ?? 0),
                            'actor_name' => app_notification_actor_name_from_session(),
                        ]);
                        kodus_socket_broadcast('kodus.meb', 'meb.changed', [
                            'action' => 'imported',
                            'batch_id' => (string) $batchId,
                            'row_count' => (int) $rowCount,
                            'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
                        ]);
                        kodus_socket_broadcast('kodus.meb', 'meb.validation.changed', [
                            'action' => 'imported',
                            'batch_id' => (string) $batchId,
                            'row_count' => (int) $rowCount,
                            'actor_id' => (int) ($_SESSION['user_id'] ?? 0),
                        ]);
                    } elseif ($errorMsg === '') {
                        $errorMsg = "No data was imported. Please check your file.";
                    }
                }
            } catch (Exception $e) {
                $errorMsg = "Error loading Excel file: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Invalid file type. Please upload an Excel file (.xls or .xlsx).";
        }
    } else {
        $errorMsg = "No file selected. Please choose an Excel file to import.";
    }

    $conn->close();

    if ($errorMsg !== '') {
        meb_import_redirect_with_flash('error', $errorMsg);
    }

    if ($successMsg !== '') {
        meb_import_redirect_with_flash('success', $successMsg);
    }
}
?>
