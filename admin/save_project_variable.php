<?php
require_once '../security.php';
security_bootstrap_session();
security_require_csrf_token();

require_once '../config.php';
require_once __DIR__ . '/../project_variable_helpers.php';

$redirectTarget = 'project_variables';

function project_variables_request_expects_json(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function project_variables_respond(array $payload, string $redirectTarget): void
{
    if (project_variables_request_expects_json()) {
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
        'message' => 'You are not authorized to update project variables.',
    ];
    $_SESSION['project_variables_flash'] = $flash;
    project_variables_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
}

$recordId = isset($_POST['record_id']) && $_POST['record_id'] !== '' ? (int) $_POST['record_id'] : null;
$fiscalYear = isset($_POST['fiscal_year']) ? (int) $_POST['fiscal_year'] : 0;
$variableKey = strtolower(trim((string) ($_POST['variable_key'] ?? '')));
$variableLabel = trim((string) ($_POST['variable_label'] ?? ''));
$valueType = trim((string) ($_POST['value_type'] ?? 'number'));
$valueNumberRaw = trim((string) ($_POST['value_number'] ?? ''));
$valueText = trim((string) ($_POST['value_text'] ?? ''));
$unit = trim((string) ($_POST['unit'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($fiscalYear < 2000 || $fiscalYear > 2100) {
    $flash = [
        'type' => 'danger',
        'message' => 'Fiscal year must be between 2000 and 2100.',
    ];
    $_SESSION['project_variables_flash'] = $flash;
    project_variables_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
}

if ($recordId !== null && $recordId > 0) {
    $existingRecord = project_variable_get_by_id($conn, $recordId);
    if (!$existingRecord) {
        $flash = [
            'type' => 'danger',
            'message' => 'The selected project variable record could not be found.',
        ];
        $_SESSION['project_variables_flash'] = $flash;
        project_variables_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
    }

    $variableKey = (string) ($existingRecord['variable_key'] ?? $variableKey);
}

if ($variableKey === '' || !preg_match('/^[a-z0-9_]+$/', $variableKey)) {
    $flash = [
        'type' => 'danger',
        'message' => 'Variable key must use lowercase letters, numbers, and underscores only.',
    ];
    $_SESSION['project_variables_flash'] = $flash;
    project_variables_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
}

if ($variableLabel === '') {
    $flash = [
        'type' => 'danger',
        'message' => 'Variable label is required.',
    ];
    $_SESSION['project_variables_flash'] = $flash;
    project_variables_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
}

if (!in_array($valueType, ['number', 'text'], true)) {
    $flash = [
        'type' => 'danger',
        'message' => 'Invalid value type selected.',
    ];
    $_SESSION['project_variables_flash'] = $flash;
    project_variables_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
}

$valueNumber = null;
if ($valueType === 'number') {
    if ($valueNumberRaw === '' || !is_numeric($valueNumberRaw)) {
        $flash = [
            'type' => 'danger',
            'message' => 'A numeric value is required for number variables.',
        ];
        $_SESSION['project_variables_flash'] = $flash;
        project_variables_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
    }
    $valueNumber = (float) $valueNumberRaw;
    $valueText = null;
} else {
    if ($valueText === '') {
        $flash = [
            'type' => 'danger',
            'message' => 'A text value is required for text variables.',
        ];
        $_SESSION['project_variables_flash'] = $flash;
        project_variables_respond(['success' => false, 'message' => $flash['message']], $redirectTarget);
    }
    $valueNumber = null;
}

$success = project_variable_save(
    $conn,
    $fiscalYear,
    $variableKey,
    $variableLabel,
    $valueType,
    $valueNumber,
    $valueText,
    $unit,
    $notes,
    (int) $userId,
    $recordId
);

$flash = $success
    ? ['type' => 'success', 'message' => 'Project variable saved successfully.']
    : ['type' => 'danger', 'message' => 'Unable to save the project variable right now. The fiscal year and variable key combination may already exist.'];

$_SESSION['project_variables_flash'] = $flash;
project_variables_respond(['success' => $success, 'message' => $flash['message']], $redirectTarget);
