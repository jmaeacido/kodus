<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/theme_helpers.php';
require_once __DIR__ . '/position_helpers.php';
require_once __DIR__ . '/area_helpers.php';
include('config.php');

security_require_method(['POST']);
security_require_csrf_token();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: ./");
    exit;
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$middleName = trim((string) ($_POST['middle_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$ext = trim((string) ($_POST['ext'] ?? ''));
$positionOption = trim((string) ($_POST['position_option'] ?? ''));
$customPosition = trim((string) ($_POST['custom_position'] ?? ''));
$position = $positionOption === '__custom__' ? $customPosition : $positionOption;
$positionAbr = kodus_position_abbreviation($position);
$area = trim((string) ($_POST['area'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$themePreference = theme_normalize_preference($_POST['theme_preference'] ?? 'light');

if ($firstName === '' || $lastName === '' || $position === '' || $positionAbr === '' || !kodus_is_assignment_area($area) || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['settings_flash'] = [
        'type' => 'error',
        'title' => 'Profile Not Saved',
        'message' => 'Please complete all required fields, select a valid area of assignment, enter a valid position and abbreviation, and provide a valid email address.'
    ];
    header("Location: settings");
    exit;
}

$update_query = "UPDATE users SET first_name=?, middle_name=?, last_name=?, ext=?, position=?, positionAbr=?, area=?, email=?, username=?, theme_preference=?";
$params = [$firstName, $middleName, $lastName, $ext, $position, $positionAbr, $area, $email, $username, $themePreference];

// Optional: handle password
if (!empty($password)) {
    if (!security_validate_password_strength($password)) {
        $_SESSION['settings_flash'] = [
            'type' => 'error',
            'title' => 'Weak Password',
            'message' => 'Use at least 8 characters with uppercase, lowercase, number, and special character.'
        ];
        header("Location: settings");
        exit;
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $update_query .= ", password=?";
    $params[] = $hashed;
}

// Optional: handle picture upload
if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png'];
    $filename = basename($_FILES['picture']['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $size = $_FILES['picture']['size'];
    $mime = security_detect_upload_mime($_FILES['picture']['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png'];

    if (in_array($ext, $allowed, true) && in_array($mime, $allowedMimes, true) && $size <= 10 * 1024 * 1024) {
        $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
        $newname = uniqid('', true) . '_' . $safeBaseName . '.' . $ext;
        $targetPath = "dist/img/" . $newname;

        if (move_uploaded_file($_FILES['picture']['tmp_name'], $targetPath)) {
            // Optional: Delete old picture if not default
            $oldPicture = (string) ($_SESSION['picture'] ?? '');
            $defaultPictures = ['default.png', 'default.webp'];
            if ($oldPicture !== '' && !in_array($oldPicture, $defaultPictures, true) && file_exists("dist/img/" . $oldPicture)) {
                unlink("dist/img/" . $oldPicture);
            }

            // Update DB and session
            $update_query .= ", picture=?";
            $params[] = $newname;
            $_SESSION['picture'] = $newname;
        }
    }
}

// Append WHERE clause at the end
$update_query .= " WHERE id=?";
$params[] = $userId;

// Prepare and execute
$stmt = $conn->prepare($update_query);
$redirectTarget = 'settings';

if (!$stmt) {
    $_SESSION['settings_flash'] = [
        'type' => 'error',
        'title' => 'Save Failed',
        'message' => 'We could not prepare your profile changes. Please try again.'
    ];
    header("Location: {$redirectTarget}");
    exit;
}

$types = str_repeat('s', count($params) - 1) . 'i';
$stmt->bind_param($types, ...$params);
$result = $stmt->execute();
$stmt->close();

// Redirect with success flag
if ($result) {
    if (!empty($password)) {
        password_policy_mark_compliant($conn, (int) $userId);
    }
    profile_review_mark_completed($conn, (int) $userId);
    $_SESSION['profile_review_required'] = false;
    unset($_SESSION['profile_review_login_prompt']);
    $_SESSION['settings_flash'] = [
        'type' => 'success',
        'title' => 'Changes Saved',
        'message' => 'Your profile and preferences have been updated.'
    ];
    theme_store_session_preference($themePreference);
    theme_store_client_preference($themePreference);
} else {
    $_SESSION['settings_flash'] = [
        'type' => 'error',
        'title' => 'Save Failed',
        'message' => 'We could not save your changes right now. Please try again.'
    ];
}
$conn->close();
header("Location: {$redirectTarget}");
exit;
