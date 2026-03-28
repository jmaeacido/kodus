<?php
require_once __DIR__ . '/../security.php';
security_bootstrap_session();
include('../config.php');

header('Content-Type: application/json');

function format_relative_activity(int $timestamp): string
{
    $seconds = max(0, time() - $timestamp);

    if ($seconds < 60) {
        return 'just now';
    }

    $minutes = (int) floor($seconds / 60);
    if ($minutes < 60) {
        return $minutes . ' min' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    $hours = (int) floor($minutes / 60);
    if ($hours < 24) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = (int) floor($hours / 24);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function classify_user_presence(?string $lastActivity, int $isOnline): array
{
    if (!$lastActivity) {
        return [
            'label' => 'Offline',
            'badge' => 'secondary',
            'detail' => 'No activity recorded',
        ];
    }

    $lastActivityTs = strtotime($lastActivity);
    if ($lastActivityTs === false) {
        return [
            'label' => 'Offline',
            'badge' => 'secondary',
            'detail' => 'Activity unavailable',
        ];
    }

    $secondsSinceActive = time() - $lastActivityTs;
    if ($isOnline === 1 && $secondsSinceActive <= 300) {
        return [
            'label' => 'Online',
            'badge' => 'success',
            'detail' => 'Active just now',
        ];
    }

    if ($secondsSinceActive <= 1800) {
        return [
            'label' => 'Idle',
            'badge' => 'warning',
            'detail' => 'Last active ' . format_relative_activity($lastActivityTs),
        ];
    }

    return [
        'label' => 'Offline',
        'badge' => 'secondary',
        'detail' => 'Last active ' . format_relative_activity($lastActivityTs),
    ];
}

// Get logged-in user ID
$userId = $_SESSION['user_id'] ?? null;

// Redirect if not logged in
if (!$userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

// Ensure user is admin
$stmt = $conn->prepare("SELECT userType FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userType);
$stmt->fetch();
$stmt->close();

if ($userType !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

// Fetch all users with richer presence tracking
$users = $conn->query("
    SELECT id, last_activity, is_online
    FROM users
    ORDER BY id ASC
");

$payload = [
    'success' => true,
    'users' => [],
    'summary' => [
        'online' => 0,
        'idle' => 0,
        'offline' => 0,
        'deactivated' => 0,
    ],
];

$deactivatedResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE deleted_at IS NOT NULL");
if ($deactivatedResult) {
    $deactivatedRow = $deactivatedResult->fetch_assoc();
    $payload['summary']['deactivated'] = (int) ($deactivatedRow['total'] ?? 0);
}

while ($row = $users->fetch_assoc()) {
    $presence = classify_user_presence($row['last_activity'], (int) $row['is_online']);

    if ($presence['label'] === 'Online') {
        $payload['summary']['online']++;
    } elseif ($presence['label'] === 'Idle') {
        $payload['summary']['idle']++;
    } else {
        $payload['summary']['offline']++;
    }

    $payload['users'][] = [
        'id' => (int) $row['id'],
        'status' => $presence['label'],
        'badge' => $presence['badge'],
        'activity_detail' => $presence['detail'],
        'activity_timestamp' => $row['last_activity']
            ? date("F d, Y h:ia", strtotime($row['last_activity']))
            : 'No timestamp available',
    ];
}

echo json_encode($payload);
