<?php

declare(strict_types=1);

function parse_location_insert_values(string $line, string $table): ?array
{
    $pattern = "/^INSERT INTO `{$table}` VALUES \\((.*)\\);$/";
    if (!preg_match($pattern, trim($line), $matches)) {
        return null;
    }

    $values = str_getcsv($matches[1], ',', "'", '\\');
    return array_map(static fn ($value): string => trim((string) $value), $values);
}

function sql_string(string $value): string
{
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
}

function municipality_key(string $province, string $municipality): string
{
    return $province . '|' . $municipality;
}

function load_unique_municipalities(string $path): array
{
    $rows = [];
    $seen = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $values = parse_location_insert_values($line, 'municipality');
        if ($values === null || count($values) < 3) {
            continue;
        }

        [, $province, $municipality] = $values;
        $dedupeKey = strtolower($province . '|' . $municipality);
        if (isset($seen[$dedupeKey])) {
            continue;
        }

        $seen[$dedupeKey] = true;
        $rows[] = [
            'id' => municipality_key($province, $municipality),
            'province' => $province,
            'municipality' => $municipality,
        ];
    }

    return $rows;
}

function load_normalized_barangays(string $path, array $municipalities): array
{
    $rawRows = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $values = parse_location_insert_values($line, 'barangay');
        if ($values === null || count($values) < 3) {
            continue;
        }
        $rawRows[] = [
            'legacy_municipality' => $values[1],
            'barangay' => $values[2],
        ];
    }

    if (count($rawRows) % 2 === 0) {
        $half = (int) (count($rawRows) / 2);
        if (array_slice($rawRows, 0, $half) === array_slice($rawRows, $half)) {
            $rawRows = array_slice($rawRows, 0, $half);
        }
    }

    $municipalityNames = array_map(static fn (array $row): string => $row['municipality'], $municipalities);
    $uniqueByName = [];
    foreach ($municipalities as $municipality) {
        $uniqueByName[strtolower($municipality['municipality'])][] = $municipality;
    }

    $rows = [];
    $seen = [];
    $cursor = 0;
    $municipalityCount = count($municipalities);

    foreach ($rawRows as $rawRow) {
        $legacyMunicipality = $rawRow['legacy_municipality'];
        $matched = null;

        for ($index = $cursor; $index < $municipalityCount; $index++) {
            if ($municipalityNames[$index] === $legacyMunicipality) {
                $matched = $municipalities[$index];
                $cursor = $index;
                break;
            }
        }

        if ($matched === null) {
            $matches = $uniqueByName[strtolower($legacyMunicipality)] ?? [];
            if (count($matches) === 1) {
                $matched = $matches[0];
            }
        }

        if ($matched === null) {
            throw new RuntimeException("Could not resolve barangay municipality: {$legacyMunicipality}");
        }

        $key = strtolower($matched['id'] . '|' . $rawRow['barangay']);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $rows[] = [
            'id' => $rawRow['barangay'],
            'municipality_id' => $matched['id'],
            'barangay' => $rawRow['barangay'],
        ];
    }

    return $rows;
}

function write_municipality_dump(string $path, array $municipalities): void
{
    $lines = [
        '/*',
        ' Navicat Premium Dump SQL',
        '',
        ' Source Server         : crg-kodus-dev',
        ' Source Server Type    : MySQL',
        ' Source Server Version : 80040 (8.0.40-0ubuntu0.22.04.1)',
        ' Source Host           : 172.31.240.134:3306',
        ' Source Schema         : kodus-dev',
        '',
        ' Target Server Type    : MySQL',
        ' Target Server Version : 80040 (8.0.40-0ubuntu0.22.04.1)',
        ' File Encoding         : 65001',
        '',
        ' Date: 13/05/2026 10:18:40',
        '*/',
        '',
        'SET NAMES utf8mb4;',
        'SET FOREIGN_KEY_CHECKS = 0;',
        '',
        '-- ----------------------------',
        '-- Table structure for municipality',
        '-- ----------------------------',
        'DROP TABLE IF EXISTS `municipality`;',
        'CREATE TABLE `municipality`  (',
        '  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,',
        '  `province_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,',
        '  `municipality_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,',
        '  PRIMARY KEY (`id`) USING BTREE,',
        '  KEY `idx_municipality_province_id` (`province_id`) USING BTREE',
        ') ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;',
        '',
        '-- ----------------------------',
        '-- Records of municipality',
        '-- ----------------------------',
    ];

    foreach ($municipalities as $row) {
        $lines[] = sprintf(
            'INSERT INTO `municipality` VALUES (%s, %s, %s);',
            sql_string($row['id']),
            sql_string($row['province']),
            sql_string($row['municipality'])
        );
    }

    $lines[] = '';
    $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';

    file_put_contents($path, implode("\r\n", $lines) . "\r\n");
}

function write_barangay_dump(string $path, array $barangays): void
{
    $lines = [
        '/*',
        ' Navicat Premium Dump SQL',
        '',
        ' Source Server         : crg-kodus-dev',
        ' Source Server Type    : MySQL',
        ' Source Server Version : 80040 (8.0.40-0ubuntu0.22.04.1)',
        ' Source Host           : 172.31.240.134:3306',
        ' Source Schema         : kodus-dev',
        '',
        ' Target Server Type    : MySQL',
        ' Target Server Version : 80040 (8.0.40-0ubuntu0.22.04.1)',
        ' File Encoding         : 65001',
        '',
        ' Date: 13/05/2026 10:18:20',
        '*/',
        '',
        'SET NAMES utf8mb4;',
        'SET FOREIGN_KEY_CHECKS = 0;',
        '',
        '-- ----------------------------',
        '-- Table structure for barangay',
        '-- ----------------------------',
        'DROP TABLE IF EXISTS `barangay`;',
        'CREATE TABLE `barangay`  (',
        '  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,',
        '  `municipality_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,',
        '  `brgy_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,',
        '  PRIMARY KEY (`municipality_id`, `brgy_name`) USING BTREE',
        ') ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;',
        '',
        '-- ----------------------------',
        '-- Records of barangay',
        '-- ----------------------------',
    ];

    foreach ($barangays as $row) {
        $lines[] = sprintf(
            'INSERT INTO `barangay` VALUES (%s, %s, %s);',
            sql_string($row['id']),
            sql_string($row['municipality_id']),
            sql_string($row['barangay'])
        );
    }

    $lines[] = '';
    $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';

    file_put_contents($path, implode("\r\n", $lines) . "\r\n");
}

$baseDir = dirname(__DIR__);
$municipalityPath = $baseDir . '/docs/sql/municipality.sql';
$barangayPath = $baseDir . '/docs/sql/barangay.sql';

$municipalities = load_unique_municipalities($municipalityPath);
$barangays = load_normalized_barangays($barangayPath, $municipalities);

write_municipality_dump($municipalityPath, $municipalities);
write_barangay_dump($barangayPath, $barangays);

echo sprintf(
    "Normalized SQL dumps: %d municipalities, %d barangays.%s",
    count($municipalities),
    count($barangays),
    PHP_EOL
);
