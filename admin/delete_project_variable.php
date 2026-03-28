<?php
require_once '../security.php';
security_bootstrap_session();
security_require_csrf_token();

require_once '../config.php';
require_once __DIR__ . '/../project_variable_helpers.php';

$redirectTarget = 'project_variables';

function project_variables_delete_request_expects_json(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function project_variables_delete_respond(array $payload, string $redirectTarget): void
{
    if (project_variables_delete_request_expects_json()) {
        security_send_json($payload, !empty($payload['success']) ? 200 : 400);
    }

    header("Location: {$redirectTarget}");
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$userType = $_SESSION['user_type'] ?? '';

if (!$userId || $userType !== 'admin') {
    $flash = [
        'type' => 'danger',
        'message' => 'You are not authorized to delete project variables.',
    ];
    $_SESSION['project_variables_flash'] = $flash;
    project_variables_delete_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
}

$recordId = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;

if ($recordId <= 0) {
    $flash = [
        'type' => 'danger',
        'message' => 'Invalid project variable selected for deletion.',
    ];
    $_SESSION['project_variables_flash'] = $flash;
    project_variables_delete_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
}

$success = project_variable_delete($conn, $recordId);

$flash = $success
    ? ['type' => 'success', 'message' => 'Project variable deleted successfully.']
    : ['type' => 'danger', 'message' => 'Unable to delete the selected project variable right now.'];

$_SESSION['project_variables_flash'] = $flash;
project_variables_delete_respond(['success' => $success, 'message' => $flash['message']], $redirectTarget);
