<?php

declare(strict_types=1);

require_once __DIR__ . '/history.php';

function mebis_request_letter_templates(): array
{
    return [
        '4ps' => [
            'key' => '4ps',
            'label' => '4Ps',
            'filename_prefix' => '4Ps_Request_Letter',
            'template_path' => dirname(__DIR__, 2) . '/pages/17206 - Memo Request for Validation Batch 13_4Ps.docx',
            'drn_number_default' => '17206',
            'source_link_default' => 'https://cutt.ly/KtXWxMky',
        ],
        'nhts-pr' => [
            'key' => 'nhts-pr',
            'label' => 'NHTS-PR',
            'filename_prefix' => 'NHTS-PR_Request_Letter',
            'template_path' => dirname(__DIR__, 2) . '/pages/17207 - Memo Request for Validation Batch 13_NHTS-PR.docx',
            'drn_number_default' => '17207',
            'source_link_default' => 'https://cutt.ly/DtXWchz2',
        ],
    ];
}

function mebis_request_letter_template(string $key): ?array
{
    $templates = mebis_request_letter_templates();
    return $templates[$key] ?? null;
}

function mebis_request_letter_manual_fields(string $templateKey): array
{
    $template = mebis_request_letter_template($templateKey);
    if ($template === null) {
        return [];
    }

    return [
        [
            'name' => 'drn_month',
            'label' => 'DRN Month Code',
            'type' => 'text',
            'max_length' => 2,
            'placeholder' => date('m'),
            'default' => date('m'),
            'context' => 'Red text in the DRN after REQ-26-',
        ],
        [
            'name' => 'drn_number',
            'label' => 'DRN Request Number',
            'type' => 'text',
            'max_length' => 20,
            'placeholder' => (string) $template['drn_number_default'],
            'default' => (string) $template['drn_number_default'],
            'context' => 'Red request number in the DRN',
        ],
        [
            'name' => 'batch_number',
            'label' => 'Batch Number',
            'type' => 'text',
            'max_length' => 10,
            'placeholder' => '13',
            'default' => '13',
            'context' => 'Red batch number in the subject and source-link label',
        ],
        [
            'name' => 'request_date',
            'label' => 'Request Date',
            'type' => 'date',
            'max_length' => 10,
            'placeholder' => date('Y-m-d'),
            'default' => date('Y-m-d'),
            'context' => 'Date line in the memorandum',
        ],
        [
            'name' => 'source_link',
            'label' => 'Source Link',
            'type' => 'url',
            'max_length' => 2048,
            'placeholder' => (string) $template['source_link_default'],
            'default' => (string) $template['source_link_default'],
            'context' => 'Red URL in the Source Link column',
        ],
    ];
}

function mebis_request_letter_validate_manual_fields(array $input, string $templateKey): array
{
    $values = [];
    foreach (mebis_request_letter_manual_fields($templateKey) as $field) {
        $name = (string) $field['name'];
        $label = (string) $field['label'];
        $value = trim((string) ($input[$name] ?? ''));

        if ($value === '') {
            throw new RuntimeException($label . ' is required.');
        }

        $maxLength = (int) ($field['max_length'] ?? 255);
        if (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') > $maxLength : strlen($value) > $maxLength) {
            throw new RuntimeException($label . ' is too long.');
        }

        $values[$name] = $value;
    }

    if (!preg_match('/^\d{2}$/', $values['drn_month'])) {
        throw new RuntimeException('DRN Month Code must be exactly two digits.');
    }

    if (!preg_match('/^[A-Za-z0-9-]+$/', $values['drn_number'])) {
        throw new RuntimeException('DRN Request Number may contain only letters, numbers, and hyphens.');
    }

    if (!preg_match('/^[A-Za-z0-9-]+$/', $values['batch_number'])) {
        throw new RuntimeException('Batch Number may contain only letters, numbers, and hyphens.');
    }

    $date = DateTime::createFromFormat('!Y-m-d', $values['request_date']);
    if (!$date || $date->format('Y-m-d') !== $values['request_date']) {
        throw new RuntimeException('Request Date must be a valid date.');
    }
    $values['request_date_display'] = strtoupper($date->format('d F Y'));

    if (!filter_var($values['source_link'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $values['source_link'])) {
        throw new RuntimeException('Source Link must be a valid http or https URL.');
    }

    return $values;
}

function mebis_request_letter_summary_from_output(array $entry): array
{
    $filename = (string) ($entry['filename'] ?? '');
    $path = mebis_outputs_dir() . '/' . $filename;
    if ($filename === '' || !is_file($path)) {
        throw new RuntimeException('Saved name-matching file was not found on disk.');
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Unable to read the saved name-matching file.');
    }

    try {
        $headerRow = fgetcsv($handle);
        if (!is_array($headerRow)) {
            throw new RuntimeException('Saved name-matching file is empty.');
        }

        if (isset($headerRow[0])) {
            $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRow[0]);
        }

        $headers = array_map(static fn($value): string => trim((string) $value), $headerRow);
        $provinceIndex = array_search('province_name', $headers, true);
        $municipalityIndex = array_search('city_name', $headers, true);

        if ($provinceIndex === false || $municipalityIndex === false) {
            throw new RuntimeException('Saved name-matching file is missing province_name or city_name columns.');
        }

        $summary = [];
        while (($row = fgetcsv($handle)) !== false) {
            $province = trim((string) ($row[$provinceIndex] ?? ''));
            $municipality = trim((string) ($row[$municipalityIndex] ?? ''));
            if ($province === '' && $municipality === '') {
                continue;
            }

            $key = strtoupper($province . '|' . $municipality);
            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'province_name' => $province,
                    'city_name' => $municipality,
                    'row_count' => 0,
                ];
            }
            $summary[$key]['row_count']++;
        }
    } finally {
        fclose($handle);
    }

    $rows = array_values($summary);
    usort($rows, static function (array $a, array $b): int {
        $provinceComparison = strcasecmp((string) $a['province_name'], (string) $b['province_name']);
        return $provinceComparison !== 0
            ? $provinceComparison
            : strcasecmp((string) $a['city_name'], (string) $b['city_name']);
    });

    if ($rows === []) {
        throw new RuntimeException('Saved name-matching file has no municipality rows to place in the request letter.');
    }

    return $rows;
}

function mebis_request_letter_set_paragraph_text(DOMDocument $dom, DOMXPath $xp, DOMElement $paragraph, string $text, array $options = []): void
{
    while ($paragraph->firstChild) {
        $paragraph->removeChild($paragraph->firstChild);
    }

    $bold = !empty($options['bold']);
    $underline = !empty($options['underline']);
    $justification = (string) ($options['justification'] ?? '');
    $fontSize = (string) ($options['font_size'] ?? '');
    $color = strtoupper(ltrim((string) ($options['color'] ?? ''), '#'));

    $pPr = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:pPr');
    if ($justification !== '') {
        $jc = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:jc');
        $jc->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', $justification);
        $pPr->appendChild($jc);
    }
    $paragraph->appendChild($pPr);

    $run = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
    $rPr = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:rPr');
    $fonts = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:rFonts');
    $fonts->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:ascii', 'Arial');
    $fonts->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:hAnsi', 'Arial');
    $fonts->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:cs', 'Arial');
    $rPr->appendChild($fonts);
    if ($bold) {
        $rPr->appendChild($dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:b'));
        $rPr->appendChild($dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:bCs'));
    }
    if ($underline) {
        $u = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:u');
        $u->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', 'single');
        $rPr->appendChild($u);
    }
    if ($fontSize !== '' && ctype_digit($fontSize)) {
        $size = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:sz');
        $size->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', $fontSize);
        $rPr->appendChild($size);
        $sizeCs = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:szCs');
        $sizeCs->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', $fontSize);
        $rPr->appendChild($sizeCs);
    }
    if ($color !== '' && preg_match('/^[A-F0-9]{6}$/', $color)) {
        $colorNode = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:color');
        $colorNode->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', $color);
        $rPr->appendChild($colorNode);
    }
    $run->appendChild($rPr);
    $textNode = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
    if (preg_match('/^\s|\s$/', $text) === 1) {
        $textNode->setAttribute('xml:space', 'preserve');
    }
    $textNode->appendChild($dom->createTextNode($text));
    $run->appendChild($textNode);
    $paragraph->appendChild($run);
}

function mebis_request_letter_set_cell_text(DOMDocument $dom, DOMXPath $xp, DOMElement $cell, array $lines, array $options = []): void
{
    foreach (iterator_to_array($xp->query('./w:p', $cell)) as $paragraph) {
        $cell->removeChild($paragraph);
    }

    foreach (array_values($lines) as $line) {
        $lineOptions = $options;
        $lineText = $line;
        if (is_array($line)) {
            $lineText = (string) ($line['text'] ?? '');
            $lineOptions = array_merge($options, is_array($line['options'] ?? null) ? $line['options'] : []);
        }
        if (!isset($lineOptions['justification'])) {
            $lineOptions['justification'] = 'center';
        }

        $paragraph = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
        mebis_request_letter_set_paragraph_text($dom, $xp, $paragraph, (string) $lineText, $lineOptions);
        $cell->appendChild($paragraph);
    }
}

function mebis_request_letter_row_cells(DOMXPath $xp, DOMElement $row): array
{
    $cells = [];
    foreach ($xp->query('./w:tc', $row) as $cell) {
        if ($cell instanceof DOMElement) {
            $cells[] = $cell;
        }
    }
    return $cells;
}

function mebis_request_letter_set_cell_vertical_merge(DOMDocument $dom, DOMXPath $xp, DOMElement $cell, string $mode): void
{
    $tcPr = $xp->query('./w:tcPr', $cell)->item(0);
    if (!$tcPr instanceof DOMElement) {
        $tcPr = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:tcPr');
        $cell->insertBefore($tcPr, $cell->firstChild);
    }

    foreach (iterator_to_array($xp->query('./w:vMerge', $tcPr)) as $existingMerge) {
        $tcPr->removeChild($existingMerge);
    }

    if (!in_array($mode, ['restart', 'continue'], true)) {
        return;
    }

    $vMerge = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:vMerge');
    if ($mode === 'restart') {
        $vMerge->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', 'restart');
    }
    $tcPr->appendChild($vMerge);
}

function mebis_request_letter_province_key(string $province): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($province));
    $normalized = $normalized ?? '';

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($normalized, 'UTF-8')
        : strtoupper($normalized);
}

function mebis_request_letter_apply_document_values(string $documentXml, array $manualValues, array $summaryRows): string
{
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;
    if (!$dom->loadXML($documentXml)) {
        throw new RuntimeException('Unable to parse the request-letter template.');
    }

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $drn = sprintf(
        'DRN: CARAGA-FO-DRMD-DRRMS-A-REQ-26-%s-%s-S',
        $manualValues['drn_month'],
        $manualValues['drn_number']
    );

    foreach ($xp->query('//w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) {
            continue;
        }

        $text = '';
        foreach ($xp->query('.//w:t', $paragraph) as $textNode) {
            $text .= $textNode->textContent;
        }

        if (str_starts_with(trim($text), 'DRN:')) {
            mebis_request_letter_set_paragraph_text($dom, $xp, $paragraph, $drn, [
                'bold' => true,
                'underline' => true,
                'font_size' => '16',
                'justification' => 'right',
            ]);
            break;
        }
    }

    $memoTable = $xp->query('//w:tbl')->item(0);
    if ($memoTable instanceof DOMElement) {
        $rows = iterator_to_array($xp->query('./w:tr', $memoTable));
        if (isset($rows[2]) && $rows[2] instanceof DOMElement) {
            $cells = mebis_request_letter_row_cells($xp, $rows[2]);
            if (isset($cells[2])) {
                mebis_request_letter_set_cell_text($dom, $xp, $cells[2], [
                    'REQUEST FOR VALIDATION (BATCH ' . $manualValues['batch_number'] . ')',
                ], [
                    'bold' => true,
                    'justification' => 'left',
                ]);
            }
        }
        if (isset($rows[3]) && $rows[3] instanceof DOMElement) {
            $cells = mebis_request_letter_row_cells($xp, $rows[3]);
            if (isset($cells[2])) {
                mebis_request_letter_set_cell_text($dom, $xp, $cells[2], [
                    $manualValues['request_date_display'],
                ], [
                    'bold' => true,
                    'justification' => 'left',
                ]);
            }
        }
    }

    $locationsTable = $xp->query('//w:tbl')->item(1);
    if (!$locationsTable instanceof DOMElement) {
        throw new RuntimeException('The request-letter template does not contain the municipality table.');
    }

    $tableRows = iterator_to_array($xp->query('./w:tr', $locationsTable));
    if (!isset($tableRows[1], $tableRows[2]) || !$tableRows[1] instanceof DOMElement || !$tableRows[2] instanceof DOMElement) {
        throw new RuntimeException('The request-letter municipality table is not in the expected format.');
    }

    $dataTemplate = $tableRows[1];
    $totalRow = $tableRows[2];
    $locationsTable->removeChild($dataTemplate);

    $provinceCounts = [];
    foreach ($summaryRows as $row) {
        $provinceKey = mebis_request_letter_province_key((string) ($row['province_name'] ?? ''));
        if ($provinceKey === '') {
            continue;
        }
        $provinceCounts[$provinceKey] = ($provinceCounts[$provinceKey] ?? 0) + 1;
    }

    $seenProvinceRows = [];
    $hasMultipleRows = count($summaryRows) > 1;
    $total = 0;
    foreach (array_values($summaryRows) as $rowIndex => $row) {
        $total += (int) ($row['row_count'] ?? 0);
        $newRow = $dataTemplate->cloneNode(true);
        if (!$newRow instanceof DOMElement) {
            continue;
        }
        $cells = mebis_request_letter_row_cells($xp, $newRow);
        if (count($cells) >= 4) {
            $province = (string) ($row['province_name'] ?? '');
            $provinceKey = mebis_request_letter_province_key($province);
            $provinceCount = $provinceKey !== '' ? (int) ($provinceCounts[$provinceKey] ?? 0) : 0;
            $provinceSeenCount = $provinceKey !== '' ? (int) ($seenProvinceRows[$provinceKey] ?? 0) : 0;

            if ($provinceCount > 1) {
                if ($provinceSeenCount === 0) {
                    mebis_request_letter_set_cell_text($dom, $xp, $cells[0], [$province]);
                    mebis_request_letter_set_cell_vertical_merge($dom, $xp, $cells[0], 'restart');
                } else {
                    mebis_request_letter_set_cell_text($dom, $xp, $cells[0], ['']);
                    mebis_request_letter_set_cell_vertical_merge($dom, $xp, $cells[0], 'continue');
                }
                $seenProvinceRows[$provinceKey] = $provinceSeenCount + 1;
            } else {
                mebis_request_letter_set_cell_text($dom, $xp, $cells[0], [$province]);
            }

            mebis_request_letter_set_cell_text($dom, $xp, $cells[1], [(string) ($row['city_name'] ?? '')]);
            mebis_request_letter_set_cell_text($dom, $xp, $cells[2], [(string) ((int) ($row['row_count'] ?? 0))]);

            if ($hasMultipleRows && $rowIndex > 0) {
                mebis_request_letter_set_cell_text($dom, $xp, $cells[3], ['']);
                mebis_request_letter_set_cell_vertical_merge($dom, $xp, $cells[3], 'continue');
            } else {
                mebis_request_letter_set_cell_text($dom, $xp, $cells[3], [
                    'Batch ' . $manualValues['batch_number'] . ' Link:',
                    [
                        'text' => $manualValues['source_link'],
                        'options' => [
                            'color' => '1F497D',
                        ],
                    ],
                ]);
                if ($hasMultipleRows) {
                    mebis_request_letter_set_cell_vertical_merge($dom, $xp, $cells[3], 'restart');
                }
            }
        }
        $locationsTable->insertBefore($newRow, $totalRow);
    }

    $totalCells = mebis_request_letter_row_cells($xp, $totalRow);
    if (isset($totalCells[1])) {
        mebis_request_letter_set_cell_text($dom, $xp, $totalCells[1], [(string) $total], ['bold' => true]);
    }

    return $dom->saveXML();
}

function mebis_request_letter_replace_footer_local_number(string $footerXml): string
{
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;
    if (!$dom->loadXML($footerXml)) {
        return $footerXml;
    }

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    foreach ($xp->query('//w:p') as $paragraph) {
        if (!$paragraph instanceof DOMElement) {
            continue;
        }

        $text = '';
        foreach ($xp->query('.//w:t', $paragraph) as $textNode) {
            $text .= $textNode->textContent;
        }

        if (!str_contains($text, 'local 238')) {
            continue;
        }

        foreach ($xp->query('.//w:t', $paragraph) as $textNode) {
            $textNode->nodeValue = str_replace('238', '1628', $textNode->nodeValue);
        }
    }

    return $dom->saveXML();
}

function mebis_request_letter_build_docx(array $entry, string $templateKey, array $manualValues): array
{
    $template = mebis_request_letter_template($templateKey);
    if ($template === null) {
        throw new RuntimeException('Unknown request-letter template.');
    }

    $templatePath = (string) $template['template_path'];
    if (!is_file($templatePath)) {
        throw new RuntimeException('Request-letter template file was not found.');
    }

    $summaryRows = mebis_request_letter_summary_from_output($entry);

    $safePrefix = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $template['filename_prefix']);
    $filename = $safePrefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.docx';
    $requestLetterDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kodus-mebis-request-letters';
    if (!is_dir($requestLetterDir) && !mkdir($requestLetterDir, 0777, true) && !is_dir($requestLetterDir)) {
        throw new RuntimeException('Unable to prepare the request-letter work folder.');
    }
    @chmod($requestLetterDir, 02775);

    $outputPath = tempnam($requestLetterDir, 'request_letter_');
    if ($outputPath === false) {
        throw new RuntimeException('Unable to prepare a request-letter work file.');
    }

    if (!copy($templatePath, $outputPath)) {
        @unlink($outputPath);
        throw new RuntimeException('Unable to prepare a request-letter copy.');
    }

    $zip = new ZipArchive();
    if ($zip->open($outputPath) !== true) {
        @unlink($outputPath);
        throw new RuntimeException('Unable to open the generated request-letter copy.');
    }

    $documentXml = $zip->getFromName('word/document.xml');
    if (!is_string($documentXml) || $documentXml === '') {
        $zip->close();
        @unlink($outputPath);
        throw new RuntimeException('The request-letter template is missing word/document.xml.');
    }

    $updatedXml = mebis_request_letter_apply_document_values($documentXml, $manualValues, $summaryRows);
    $zip->addFromString('word/document.xml', $updatedXml);

    foreach (['word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'] as $footerName) {
        $footerXml = $zip->getFromName($footerName);
        if (is_string($footerXml) && $footerXml !== '') {
            $zip->addFromString($footerName, mebis_request_letter_replace_footer_local_number($footerXml));
        }
    }
    $zip->close();

    return [
        'path' => $outputPath,
        'filename' => $filename,
        'rows' => array_sum(array_map(static fn(array $row): int => (int) ($row['row_count'] ?? 0), $summaryRows)),
        'municipalities' => count($summaryRows),
    ];
}
