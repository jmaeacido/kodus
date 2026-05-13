<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function parse_insert_values(string $line, string $table): ?array
{
    $pattern = "/^INSERT INTO `{$table}` VALUES \\((.*)\\);$/";
    if (!preg_match($pattern, trim($line), $matches)) {
        return null;
    }

    $values = str_getcsv($matches[1], ',', "'", '\\');
    return array_map(static fn ($value): string => trim((string) $value), $values);
}

function load_municipalities(string $path): array
{
    $municipalities = [];
    $seen = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $values = parse_insert_values($line, 'municipality');
        if ($values === null || count($values) < 3) {
            continue;
        }

        [$legacyId, $province, $municipality] = $values;
        $key = strtolower($province . '|' . $municipality);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $municipalities[] = [
            'id' => location_municipality_id($province, $municipality),
            'legacy_id' => $legacyId,
            'province' => $province,
            'municipality' => $municipality,
        ];
    }

    return $municipalities;
}

function location_municipality_id(string $province, string $municipality): string
{
    return $province . '|' . $municipality;
}

function load_barangays(string $path, array $municipalities): array
{
    $rawRows = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $values = parse_insert_values($line, 'barangay');
        if ($values === null || count($values) < 3) {
            continue;
        }
        $rawRows[] = [
            'id' => $values[0],
            'legacy_municipality_id' => $values[1],
            'name' => $values[2],
        ];
    }

    if (count($rawRows) % 2 === 0) {
        $half = (int) (count($rawRows) / 2);
        if (array_slice($rawRows, 0, $half) === array_slice($rawRows, $half)) {
            $rawRows = array_slice($rawRows, 0, $half);
        }
    }

    $municipalityNames = array_map(static fn (array $row): string => $row['municipality'], $municipalities);
    $uniqueByLegacyName = [];
    foreach ($municipalities as $municipality) {
        $legacyKey = strtolower($municipality['legacy_id']);
        $uniqueByLegacyName[$legacyKey][] = $municipality;
    }

    $barangays = [];
    $seen = [];
    $cursor = 0;
    $municipalityCount = count($municipalities);

    foreach ($rawRows as $row) {
        $legacyMunicipality = $row['legacy_municipality_id'];
        $matched = null;

        for ($index = $cursor; $index < $municipalityCount; $index++) {
            if ($municipalityNames[$index] === $legacyMunicipality) {
                $matched = $municipalities[$index];
                $cursor = $index;
                break;
            }
        }

        if ($matched === null) {
            $legacyKey = strtolower($legacyMunicipality);
            if (count($uniqueByLegacyName[$legacyKey] ?? []) === 1) {
                $matched = $uniqueByLegacyName[$legacyKey][0];
            }
        }

        if ($matched === null) {
            throw new RuntimeException("Could not resolve barangay municipality: {$legacyMunicipality}");
        }

        $key = strtolower($matched['id'] . '|' . $row['name']);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $barangays[] = [
            'id' => $row['name'],
            'municipality_id' => $matched['id'],
            'name' => $row['name'],
        ];
    }

    return $barangays;
}

$baseDir = dirname(__DIR__);
$municipalities = load_municipalities($baseDir . '/docs/sql/municipality.sql');
$barangays = load_barangays($baseDir . '/docs/sql/barangay.sql', $municipalities);

if ($municipalities === [] || $barangays === []) {
    throw new RuntimeException('No reference rows were loaded from the SQL dumps.');
}

$conn->begin_transaction();

try {
    $conn->query('DELETE FROM barangay');
    $conn->query('DELETE FROM municipality');

    $municipalityStmt = $conn->prepare('INSERT INTO municipality (id, province_id, municipality_name) VALUES (?, ?, ?)');
    if (!$municipalityStmt) {
        throw new RuntimeException('Could not prepare municipality insert: ' . $conn->error);
    }

    foreach ($municipalities as $municipality) {
        $municipalityStmt->bind_param('sss', $municipality['id'], $municipality['province'], $municipality['municipality']);
        $municipalityStmt->execute();
    }
    $municipalityStmt->close();

    $barangayStmt = $conn->prepare('INSERT INTO barangay (id, municipality_id, brgy_name) VALUES (?, ?, ?)');
    if (!$barangayStmt) {
        throw new RuntimeException('Could not prepare barangay insert: ' . $conn->error);
    }

    foreach ($barangays as $barangay) {
        $barangayStmt->bind_param('sss', $barangay['id'], $barangay['municipality_id'], $barangay['name']);
        $barangayStmt->execute();
    }
    $barangayStmt->close();

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    throw $exception;
}

echo sprintf(
    "Repaired location reference data: %d municipalities, %d barangays.%s",
    count($municipalities),
    count($barangays),
    PHP_EOL
);
