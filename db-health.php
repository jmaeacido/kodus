<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/env_helpers.php';
require_once __DIR__ . '/vendor/autoload.php';

security_configure_runtime_for_web();
app_load_environment();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$host = app_env('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
$user = app_env('DB_USERNAME', 'root') ?? 'root';
$pass = app_env('DB_PASSWORD', '') ?? '';
$db = app_env('DB_NAME', '') ?? '';

$response = [
    'ok' => false,
    'php_version' => PHP_VERSION,
    'mysqli_loaded' => extension_loaded('mysqli'),
    'env_files' => [
        '.env_exists' => is_file(__DIR__ . '/.env'),
    ],
    'database_config' => [
        'host' => $host,
        'database' => $db,
        'username_present' => $user !== '',
        'password_present' => $pass !== '',
    ],
    'database_value_lengths' => [
        'host' => strlen($host),
        'database' => strlen($db),
        'username' => strlen($user),
        'password' => strlen($pass),
    ],
    'bootstrap' => [
        'config_loaded' => false,
        'config_error' => null,
    ],
    'login_checks' => [
        'users_table_exists' => false,
        'required_columns' => [],
        'login_query_prepare_ok' => false,
        'login_query_execute_ok' => false,
        'active_user_count' => null,
        'query_error' => null,
    ],
];

if (!extension_loaded('mysqli')) {
    http_response_code(500);
    $response['error'] = 'The mysqli PHP extension is not loaded.';
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    $response['error'] = 'Database connection failed.';
    $response['connect_errno'] = $conn->connect_errno;
    $response['connect_error'] = $conn->connect_error;
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

$queryOk = false;
$queryError = null;
$result = @$conn->query('SELECT 1 AS db_ok');
if ($result instanceof mysqli_result) {
    $row = $result->fetch_assoc();
    $queryOk = isset($row['db_ok']) && (int) $row['db_ok'] === 1;
    $result->free();
} else {
    $queryError = $conn->error;
}

$response['ok'] = $queryOk;
$response['server_info'] = $conn->server_info;
$response['host_info'] = $conn->host_info;

try {
    require_once __DIR__ . '/config.php';
    $response['bootstrap']['config_loaded'] = true;
} catch (Throwable $e) {
    $response['bootstrap']['config_error'] = $e->getMessage();
}

$usersTableResult = @$conn->query("SHOW TABLES LIKE 'users'");
if ($usersTableResult instanceof mysqli_result) {
    $response['login_checks']['users_table_exists'] = $usersTableResult->num_rows > 0;
    $usersTableResult->free();
}

$requiredColumns = ['id', 'username', 'password', 'deleted_at', 'userType'];
foreach ($requiredColumns as $columnName) {
    $columnResult = @$conn->query("SHOW COLUMNS FROM users LIKE '" . $conn->real_escape_string($columnName) . "'");
    $response['login_checks']['required_columns'][$columnName] = $columnResult instanceof mysqli_result && $columnResult->num_rows > 0;
    if ($columnResult instanceof mysqli_result) {
        $columnResult->free();
    }
}

$countStmt = @$conn->prepare('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
if ($countStmt instanceof mysqli_stmt) {
    $response['login_checks']['login_query_prepare_ok'] = true;
    if (@$countStmt->execute()) {
        $response['login_checks']['login_query_execute_ok'] = true;
        @$countStmt->bind_result($activeUserCount);
        if (@$countStmt->fetch()) {
            $response['login_checks']['active_user_count'] = (int) $activeUserCount;
        }
    } else {
        $response['login_checks']['query_error'] = $countStmt->error;
    }
    $countStmt->close();
} else {
    $response['login_checks']['query_error'] = $conn->error;
}

$response['ok'] = $queryOk
    && $response['bootstrap']['config_loaded'] === true
    && $response['login_checks']['users_table_exists'] === true
    && !in_array(false, $response['login_checks']['required_columns'], true)
    && $response['login_checks']['login_query_prepare_ok'] === true
    && $response['login_checks']['login_query_execute_ok'] === true;

if (!$queryOk) {
    http_response_code(500);
    $response['error'] = 'Connected to the database server, but the validation query failed.';
    $response['query_error'] = $queryError;
    $conn->close();
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

if (!$response['ok']) {
    http_response_code(500);
    $response['error'] = 'Database connectivity is working, but one or more login dependencies failed.';
    $conn->close();
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

$conn->close();
echo json_encode($response, JSON_PRETTY_PRINT);
