<?php

$root = dirname(__DIR__, 4);
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($uriPath);

function capture_require_php(string $file): bool
{
    $previousCwd = getcwd();
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: basename($file);
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    chdir(dirname($file));
    require $file;
    if ($previousCwd !== false) {
        chdir($previousCwd);
    }
    return true;
}

if ($path === '/__capture_login') {
    require_once $root . '/security.php';
    security_bootstrap_session();
    require_once $root . '/auth_helpers.php';
    require_once $root . '/config.php';

    $username = preg_replace('/[^a-zA-Z0-9_\\-]/', '', (string) ($_GET['user'] ?? 'annex_capture_admin'));
    $year = preg_replace('/[^0-9]/', '', (string) ($_GET['year'] ?? '2026'));
    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = db_stmt_fetch_one_assoc($stmt);
    $stmt->close();

    if (!$user) {
        http_response_code(404);
        echo 'capture user not found';
        return true;
    }

    $_SESSION['selected_year'] = $year !== '' ? $year : '2026';
    auth_store_user_session($user);
    $_SESSION['profile_review_required'] = false;
    echo 'ok';
    return true;
}
$candidate = realpath($root . $path);

if ($candidate !== false && is_file($candidate)) {
    if (strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) === 'php') {
        return capture_require_php($candidate);
    }
    return false;
}

$extensionless = realpath($root . $path . '.php');
if ($extensionless !== false && is_file($extensionless)) {
    return capture_require_php($extensionless);
}

$indexCandidate = realpath($root . rtrim($path, '/') . '/index.php');
if ($indexCandidate !== false && is_file($indexCandidate)) {
    return capture_require_php($indexCandidate);
}

return false;
