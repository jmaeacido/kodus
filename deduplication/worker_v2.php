<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers/validator.php';
require_once __DIR__ . '/../vendor/autoload.php';

$jobId = (int)($argv[1] ?? 0);
if ($jobId <= 0) {
    exit("Invalid job id\n");
}

$errorFile = __DIR__ . "/logs/job_{$jobId}.error.log";

function updateDedupJob(mysqli $conn, int $jobId, string $status, int $progress): void {
    $stmt = $conn->prepare("
        UPDATE deduplication_jobs
        SET status = ?, progress = ?, last_activity = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $status, $progress, $jobId);
    $stmt->execute();
    $stmt->close();
}

function isDedupCancelled(mysqli $conn, int $jobId): bool {
    $stmt = $conn->prepare("SELECT status FROM deduplication_jobs WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($row['status'] ?? '') === 'cancelled';
}

function dedupNormalize(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function dedupSimilarity(string $a, string $b): float {
    $a = dedupNormalize($a);
    $b = dedupNormalize($b);

    if ($a === '' && $b === '') {
        return 100.0;
    }

    similar_text($a, $b, $similarity);
    return round($similarity, 2);
}

function dedupBirthValue(array $row): string {
    return trim((string)($row['birthDate'] ?? ''));
}

function dedupAddressValue(array $row): string {
    return trim(
        implode(' ', [
            $row['barangay'] ?? '',
            $row['lgu'] ?? '',
            $row['province'] ?? '',
        ])
    );
}

function dedupNameValue(array $row): string {
    return trim(
        implode(' ', [
            $row['lastName'] ?? '',
            $row['firstName'] ?? '',
            $row['middleName'] ?? '',
            $row['ext'] ?? '',
        ])
    );
}

function dedupScore(array $a, array $b, string $rule): float {
    if ($rule === 'strict') {
        $left = dedupNormalize(dedupNameValue($a) . ' ' . dedupBirthValue($a) . ' ' . dedupAddressValue($a));
        $right = dedupNormalize(dedupNameValue($b) . ' ' . dedupBirthValue($b) . ' ' . dedupAddressValue($b));
        return $left !== '' && $left === $right ? 100.0 : 0.0;
    }

    $nameScore = dedupSimilarity(dedupNameValue($a), dedupNameValue($b));
    $birthA = dedupBirthValue($a);
    $birthB = dedupBirthValue($b);
    $birthScore = ($birthA !== '' && $birthB !== '')
        ? ($birthA === $birthB ? 100.0 : dedupSimilarity($birthA, $birthB))
        : 0.0;
    $addressScore = dedupSimilarity(dedupAddressValue($a), dedupAddressValue($b));

    return round(($nameScore * 0.70) + ($birthScore * 0.20) + ($addressScore * 0.10), 2);
}

function findRoot(array &$parent, int $node): int {
    if ($parent[$node] !== $node) {
        $parent[$node] = findRoot($parent, $parent[$node]);
    }
    return $parent[$node];
}

function unionRoots(array &$parent, array &$rank, int $left, int $right): void {
    $rootLeft = findRoot($parent, $left);
    $rootRight = findRoot($parent, $right);

    if ($rootLeft === $rootRight) {
        return;
    }

    if ($rank[$rootLeft] < $rank[$rootRight]) {
        $parent[$rootLeft] = $rootRight;
    } elseif ($rank[$rootLeft] > $rank[$rootRight]) {
        $parent[$rootRight] = $rootLeft;
    } else {
        $parent[$rootRight] = $rootLeft;
        $rank[$rootLeft]++;
    }
}

try {
    $stmt = $conn->prepare("SELECT * FROM deduplication_jobs WHERE id = ?");
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$job) {
        exit("Job not found\n");
    }

    $filePath = __DIR__ . "/uploads/" . $job['file_name'];
    if (!is_file($filePath)) {
        throw new RuntimeException('Uploaded file is missing.');
    }

    updateDedupJob($conn, $jobId, 'processing', 0);

    $deleteStmt = $conn->prepare("DELETE FROM deduplication_results WHERE job_id = ?");
    $deleteStmt->bind_param("i", $jobId);
    $deleteStmt->execute();
    $deleteStmt->close();

    $rows = validateAndParseFile($filePath);
    $total = count($rows);

    if ($total < 2) {
        updateDedupJob($conn, $jobId, 'done', 100);
        exit;
    }

    $parent = [];
    $rank = [];
    $bestScores = array_fill(0, $total, 0.0);
    for ($i = 0; $i < $total; $i++) {
        $parent[$i] = $i;
        $rank[$i] = 0;
    }

    $threshold = (int)$job['threshold'];
    $rule = (string)$job['rule'];
    $totalComparisons = (int)(($total * ($total - 1)) / 2);
    $completedComparisons = 0;
    $lastProgress = 0;
    $progressTick = max(200, (int)floor($totalComparisons / 100));

    for ($i = 0; $i < $total; $i++) {
        for ($j = $i + 1; $j < $total; $j++) {
            $score = dedupScore($rows[$i], $rows[$j], $rule);
            if ($score >= $threshold) {
                unionRoots($parent, $rank, $i, $j);
                $bestScores[$i] = max($bestScores[$i], $score);
                $bestScores[$j] = max($bestScores[$j], $score);
            }

            $completedComparisons++;
            if ($completedComparisons % $progressTick === 0 || $completedComparisons === $totalComparisons) {
                if (isDedupCancelled($conn, $jobId)) {
                    exit;
                }

                $progress = min(99, (int)floor(($completedComparisons / max(1, $totalComparisons)) * 100));
                if ($progress > $lastProgress) {
                    updateDedupJob($conn, $jobId, 'processing', $progress);
                    $lastProgress = $progress;
                }
            }
        }
    }

    if (isDedupCancelled($conn, $jobId)) {
        exit;
    }

    $clusters = [];
    foreach (array_keys($rows) as $index) {
        $root = findRoot($parent, $index);
        $clusters[$root][] = $index;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO deduplication_results (job_id, group_id, row_data, created_at, similarity)
        VALUES (?, ?, ?, NOW(), ?)
    ");

    $groupId = 1;
    foreach ($clusters as $indices) {
        if (count($indices) < 2) {
            continue;
        }

        foreach ($indices as $index) {
            $rowJson = json_encode($rows[$index], JSON_UNESCAPED_UNICODE);
            $similarity = $bestScores[$index] > 0 ? $bestScores[$index] : (float)$threshold;
            $insertStmt->bind_param("iisd", $jobId, $groupId, $rowJson, $similarity);
            $insertStmt->execute();
        }

        $groupId++;
    }

    $insertStmt->close();
    updateDedupJob($conn, $jobId, 'done', 100);
} catch (Throwable $e) {
    updateDedupJob($conn, $jobId, 'failed', 100);
    file_put_contents(
        $errorFile,
        date('c') . " - ERROR: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
}
