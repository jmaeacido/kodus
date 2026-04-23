<?php
// helpers/progress.php
// Handles progress tracking for crossmatch

function getProgressFile() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return __DIR__ . '/../uploads/progress_' . session_id() . '.json';
}

function initProgress() {
    $file = getProgressFile();
    $data = [
        "percent" => 0,
        "done" => false,
        "message" => "Starting..."
    ];
    file_put_contents($file, json_encode($data));
}

function updateProgress($percent, $done = false, $message = "") {
    $file = getProgressFile();
    $data = [
        "percent" => $percent,
        "done" => $done,
        "message" => $message
    ];
    file_put_contents($file, json_encode($data));
}

function readProgress() {
    $file = getProgressFile();
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [
        "percent" => 0,
        "done" => false,
        "message" => "Starting..."
    ];
}

function clearProgress() {
    $file = getProgressFile();
    if (file_exists($file)) {
        unlink($file);
    }
}
