<?php
// worker.php
// Usage: php worker.php <jobId>
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../config.php';

// Job ID from CLI
$jobId = intval($argv[1] ?? 0);
if ($jobId <= 0) {
    exit("Invalid job id\n");
}

$logFile   = __DIR__ . "/logs/job_{$jobId}.log";
$errorFile = __DIR__ . "/logs/job_{$jobId}.error.log";

// Fetch job
$stmt = $conn->prepare("SELECT * FROM deduplication_jobs WHERE id=?");
$stmt->bind_param("i", $jobId);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    exit("Job not found\n");
}

$filePath  = __DIR__ . "/uploads/" . $job['file_name'];
$rule      = $job['rule'];
$threshold = intval($job['threshold']);

// Update job → processing
$conn->query("UPDATE deduplication_jobs 
              SET status='processing', progress=0, last_activity=NOW() 
              WHERE id=$jobId");

// === Load CSV/XLSX ===
function loadData($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $rows = [];

    if ($ext === 'csv') {
        if (($handle = fopen($filePath, "r")) !== false) {
            $header = fgetcsv($handle);
            $rowNum = 2; // CSV starts at line 2 for first data row
            while (($data = fgetcsv($handle)) !== false) {
                $row = array_combine($header, $data);

                // ✅ Normalize birthDate
                if (!empty($row['birthDate'])) {
                    $row['birthDate'] = normalizeDate($row['birthDate']);
                }

                $row['_rowNumber'] = $rowNum++;
                $rows[] = $row;
            }
            fclose($handle);
        }
    } elseif ($ext === 'xlsx') {
        require_once __DIR__ . '/../vendor/autoload.php';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $header = [];
        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $cells = [];
            foreach ($cellIterator as $cell) {
                $val = $cell->getValue();

                // ✅ Convert Excel serial dates to Y-m-d
                if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($val)) {
                    $val = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)
                                ->format('Y-m-d');
                }

                $cells[] = trim((string)$val);
            }
            if ($rowIndex === 1) {
                $header = $cells;
            } else {
                $rowArr = array_combine($header, $cells);

                // ✅ Normalize birthDate
                if (!empty($rowArr['birthDate'])) {
                    $rowArr['birthDate'] = normalizeDate($rowArr['birthDate']);
                }

                //$rowArr['_rowNumber'] = $rowIndex;
                $rows[] = $rowArr;
            }
        }
    }
    return $rows;
}

function normalizeDate($value) {
    // Already Y-m-d? keep it
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    // Try parsing any format
    try {
        $dt = new DateTime($value);
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return $value; // fallback, leave as-is if not a valid date
    }
}

// === Deduplication logic ===
function matchScore($a, $b, $rule) {
    $strA = strtolower(trim($a));
    $strB = strtolower(trim($b));
    if ($rule === 'strict') {
        return $strA === $strB ? 100 : 0;
    } else {
        similar_text($strA, $strB, $percent);
        return $percent;
    }
}

try {
    $rows = loadData($filePath);
    $total = count($rows);

    if ($total < 2) {
        $conn->query("UPDATE deduplication_jobs 
                      SET status='done', progress=100, last_activity=NOW() 
                      WHERE id=$jobId");
        exit;
    }

    $processed = 0;
    $groupId = 1;

    $insertStmt = $conn->prepare("
        INSERT INTO deduplication_results 
        (job_id, group_id, row_data, created_at, similarity) 
        VALUES (?, ?, ?, NOW(), ?)
    ");

    // Compare each pair
    for ($i = 0; $i < $total; $i++) {
        for ($j = $i + 1; $j < $total; $j++) {
            $name1 = $rows[$i]['lastName'] . ' ' . $rows[$i]['firstName'] . ' ' . $rows[$i]['middleName'] . ' ' . $rows[$i]['ext'] . ' ' . $rows[$i]['birthDate'];
            $name2 = $rows[$j]['lastName'] . ' ' . $rows[$j]['firstName'] . ' ' . $rows[$j]['middleName'] . ' ' . $rows[$j]['ext'] . ' ' . $rows[$j]['birthDate'];

            $score = matchScore($name1, $name2, $rule);
            if ($score >= $threshold) {
                foreach ([$rows[$i], $rows[$j]] as $record) {
                    unset($record['_rowNumber']);

                    $json = json_encode($record, JSON_UNESCAPED_UNICODE);
                    $insertStmt->bind_param("iisd", $jobId, $groupId, $json, $score);
                    $insertStmt->execute();
                }
                $groupId++;
            }
        }

        $processed++;
        $progress = intval(($processed / $total) * 100);
        $conn->query("UPDATE deduplication_jobs 
                      SET progress=$progress, last_activity=NOW() 
                      WHERE id=$jobId");
    }
    $insertStmt->close();

    // Mark as done
    $conn->query("UPDATE deduplication_jobs 
                  SET status='done', progress=100, last_activity=NOW() 
                  WHERE id=$jobId");

} catch (Exception $e) {
    $err = $conn->real_escape_string($e->getMessage());
    $conn->query("UPDATE deduplication_jobs 
                  SET status='failed', last_activity=NOW() 
                  WHERE id=$jobId");
    file_put_contents(__DIR__ . "/logs/job_$jobId.error.log",
        date('c') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}