<?php

function kodus_position_map(): array
{
    return [
        'Administrative Aide-I' => 'AAide-I',
        'Administrative Aide-II' => 'AAide-II',
        'Administrative Aide-III' => 'AAide-III',
        'Administrative Aide-IV' => 'AAide-IV',
        'Administrative Assistant-I' => 'AdAs-I',
        'Administrative Assistant-II' => 'AdAs-II',
        'Administrative Assistant-III' => 'AdAs-III',
        'Project Development Officer-I' => 'PDO-I',
        'Project Development Officer-II' => 'PDO-II',
        'Project Development Officer-III' => 'PDO-III',
        'Social Welfare Officer-I' => 'SWO-I',
        'Social Welfare Officer-II' => 'SWO-II',
        'Social Welfare Officer-III' => 'SWO-III',
    ];
}

function kodus_position_map_with_custom(mysqli $conn): array
{
    $map = kodus_position_map();
    $excludedPositions = [
        'SSO User',
    ];
    $result = $conn->query("
        SELECT DISTINCT position, positionAbr
        FROM users
        WHERE position IS NOT NULL
          AND TRIM(position) <> ''
        ORDER BY position ASC
    ");

    if (!$result) {
        return $map;
    }

    while ($row = $result->fetch_assoc()) {
        $position = trim((string) ($row['position'] ?? ''));
        if ($position === '' || in_array($position, $excludedPositions, true) || isset($map[$position])) {
            continue;
        }

        $abbreviation = trim((string) ($row['positionAbr'] ?? ''));
        $map[$position] = $abbreviation !== '' ? $abbreviation : kodus_position_custom_abbreviation($position);
    }

    return $map;
}

function kodus_position_custom_abbreviation(string $position): string
{
    $position = trim(preg_replace('/\s+/', ' ', $position) ?? '');
    if ($position === '') {
        return '';
    }

    $suffix = '';
    if (preg_match('/(?:^|\s|-)((?:X|IX|IV|V?I{1,3}))$/i', $position, $matches, PREG_OFFSET_CAPTURE)) {
        $suffix = strtoupper((string) $matches[1][0]);
        $position = trim(substr($position, 0, (int) $matches[1][1]));
    }

    $tokens = preg_split('/[\s\-\/&,()]+/', $position) ?: [];
    $stopWords = ['and', 'of', 'the', 'for', 'in', 'on', 'to', 'with', 'at', 'by'];
    $letters = [];

    foreach ($tokens as $token) {
        $token = preg_replace('/[^A-Za-z]/', '', $token) ?? '';
        if ($token === '') {
            continue;
        }

        if (in_array(strtolower($token), $stopWords, true)) {
            continue;
        }

        $letters[] = strtoupper($token[0]);
    }

    $base = implode('', $letters);
    if ($base === '') {
        return $suffix !== '' ? $suffix : '';
    }

    return $suffix !== '' ? $base . '-' . $suffix : $base;
}

function kodus_position_abbreviation(string $position): string
{
    $position = trim($position);
    if ($position === '') {
        return '';
    }

    $map = kodus_position_map();
    if (isset($map[$position])) {
        return $map[$position];
    }

    return kodus_position_custom_abbreviation($position);
}
