<?php

$outputPath = __DIR__ . '/kodus-app-summary.pdf';

function pdf_escape(string $text): string
{
    return str_replace(
        ['\\', '(', ')'],
        ['\\\\', '\(', '\)'],
        $text
    );
}

function text_cmd(string $font, float $size, float $x, float $y, string $text): string
{
    $safe = pdf_escape($text);
    return "BT /{$font} {$size} Tf 1 0 0 1 {$x} {$y} Tm ({$safe}) Tj ET";
}

$lines = [
    ['F2', 20, 44, 770, 'KODUS App Summary'],
    ['F1', 9, 44, 754, 'Generated from repo evidence only.'],

    ['F2', 11, 44, 730, 'What It Is'],
    ['F1', 9.5, 44, 716, 'KODUS (KliMalasakit Online Document Updating System) is a PHP/MySQL web app for managing'],
    ['F1', 9.5, 44, 704, 'beneficiary records, document workflows, messaging, and summary reporting.'],

    ['F2', 11, 44, 684, 'Who It Is For'],
    ['F1', 9.5, 44, 670, 'Primary persona: KliMalasakit staff and admins who maintain beneficiary data, review reports,'],
    ['F1', 9.5, 44, 658, 'track documents and payouts, manage events, and communicate with users.'],

    ['F2', 11, 44, 638, 'What It Does'],
    ['F1', 9, 52, 624, '- Login with session timeout, remember-me cookies, password reset, and optional email 2FA.'],
    ['F1', 9, 52, 612, '- Requires fiscal year selection before access; dashboard metrics are year-filtered.'],
    ['F1', 9, 52, 600, '- Manages MEB beneficiary masterlists with validation plus Excel import/export.'],
    ['F1', 9, 52, 588, '- Tracks incoming documents, outgoing documents, and payout records.'],
    ['F1', 9, 52, 576, '- Shows charts and reports for sex, NHTS-PR, sectoral, and PWD data.'],
    ['F1', 9, 52, 564, '- Provides calendar scheduling, inbox/contact messaging, crossmatch, and deduplication tools.'],

    ['F2', 11, 44, 542, 'How It Works'],
    ['F1', 9, 44, 528, 'UI: AdminLTE, jQuery, DataTables, Chart.js, FullCalendar, and AJAX-driven widgets.'],
    ['F1', 9, 44, 516, 'App layer: PHP entry points such as index.php, home.php, pages/*, inbox/*, crossmatch/*,'],
    ['F1', 9, 44, 504, 'and deduplication/*; sessions and auth checks are centralized in header.php.'],
    ['F1', 9, 44, 492, 'Config/data: .env is loaded with phpdotenv; config.php opens a mysqli connection to MySQL.'],
    ['F1', 9, 44, 480, 'Services: PHPMailer handles login/contact/reset/2FA email; PhpSpreadsheet handles Excel files.'],
    ['F1', 9, 44, 468, 'Data flow: browser -> PHP pages/APIs -> MySQL tables/files -> JSON/HTML/Excel -> browser widgets.'],

    ['F2', 11, 44, 446, 'How To Run'],
    ['F1', 9, 52, 432, '1. Configure .env with DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, and SMTP settings.'],
    ['F1', 9, 52, 420, '2. Import kodus_db.sql into MySQL and keep the DB name aligned with .env (default: kodus_db).'],
    ['F1', 9, 52, 408, '3. Serve this folder through Apache/Laragon so .htaccess extensionless routes work.'],
    ['F1', 9, 52, 396, '4. Open /select_year, choose a year, then sign in at /.'],
    ['F1', 9, 52, 384, '5. Full PHP deployment guide: Not found in repo. Top-level Dockerfile is for AdminLTE npm dev only.'],
];

$streamParts = [];
$streamParts[] = '0.15 w';
$streamParts[] = '0.87 0.90 0.95 RG';
$streamParts[] = '44 746 m 568 746 l S';
$streamParts[] = '44 440 m 568 440 l S';

foreach ($lines as [$font, $size, $x, $y, $text]) {
    $streamParts[] = text_cmd($font, $size, $x, $y, $text);
}

$stream = implode("\n", $streamParts) . "\n";

$objects = [];
$objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
$objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
$objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n";
$objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
$objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
$objects[] = "6 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream\nendobj\n";

$pdf = "%PDF-1.4\n";
$offsets = [0];

foreach ($objects as $object) {
    $offsets[] = strlen($pdf);
    $pdf .= $object;
}

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";

for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}

$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n{$xrefOffset}\n%%EOF";

file_put_contents($outputPath, $pdf);

echo $outputPath . PHP_EOL;
