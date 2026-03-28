<?php
// fetch_ph_holidays.php
// Fetch PH holidays from the Official Gazette and insert/update into your DB

date_default_timezone_set('Asia/Manila');
require '../config.php'; // adjust as needed

$year = date('Y');
$url = "https://www.officialgazette.gov.ph/nationwide-holidays/$year/";

$html = @file_get_contents($url);
if (!$html) {
    die("Error: Cannot access Official Gazette for $year.\n");
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Try to find holiday titles and dates in the content area
$holidayList = [];

$entries = $xpath->query("//article//p | //article//li");
foreach ($entries as $entry) {
    $text = trim($entry->textContent);
    if (preg_match('/([A-Za-z]+ \d{1,2}, \d{4})/i', $text, $match)) {
        $date = date('Y-m-d', strtotime($match[1]));
        $name = preg_replace('/—.*| - .*| \([^)]+\)/', '', $text); // clean up name
        $name = trim($name);
        $holidayList[] = [
            'name' => $name,
            'date' => $date,
            'type' => (stripos($text, 'special') !== false ? 'Special Non-Working' : 'Regular'),
            'source' => 'Official Gazette',
            'year' => $year
        ];
    }
}

// Save to DB
$stmt = $conn->prepare("INSERT INTO holidays (name, date, type, source, year)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE name=VALUES(name), type=VALUES(type), source=VALUES(source)");
$inserted = 0;

foreach ($holidayList as $h) {
    $stmt->bind_param("ssssi", $h['name'], $h['date'], $h['type'], $h['source'], $h['year']);
    if ($stmt->execute()) $inserted++;
}

echo "✅ $inserted holidays saved from Official Gazette for $year.\n";
?>
