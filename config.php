<?php
	require_once __DIR__ . '/security.php';
	require_once __DIR__ . '/env_helpers.php';
	require_once __DIR__ . '/db_stmt_helpers.php';
	require_once __DIR__ . '/avatar_helpers.php';
	require_once __DIR__ . '/audit_helpers.php';
	require_once __DIR__ . '/meb_change_history_helpers.php';
	require_once __DIR__ . '/password_policy_helpers.php';
	require_once __DIR__ . '/sso_helpers.php';
	require_once __DIR__ . '/theme_helpers.php';
	require_once __DIR__ . '/two_factor_helpers.php';
	require_once __DIR__ . '/error_helpers.php';
	require_once __DIR__ . '/app_location_helpers.php';
	require_once __DIR__ . '/vendor/autoload.php';

	security_configure_runtime_for_web();

	app_load_environment();

	security_enforce_same_origin();
	security_bootstrap_session();

	app_apply_current_timezone();

	$host = app_env('DB_HOST', '127.0.0.1');
	$user = app_env('DB_USERNAME', 'root');
	$pass = app_env('DB_PASSWORD', '');
	$db   = app_env('DB_NAME', '');

	$conn = new mysqli($host, $user, $pass, $db);

	// Check connection
	if ($conn->connect_error) {
	    $message = "Database connection failed: " . $conn->connect_error;
	    error_log($message);
	    throw new RuntimeException($message);
	}

	if (!$conn->set_charset('utf8mb4')) {
	    $message = "Failed to set database charset to utf8mb4: " . $conn->error;
	    error_log($message);
	    throw new RuntimeException($message);
	}

	app_apply_mysql_timezone($conn);

	theme_ensure_schema($conn);
	password_policy_ensure_schema($conn);
	sso_ensure_schema($conn);
	two_factor_ensure_schema($conn);
	kodus_app_settings_ensure_schema($conn);
	meb_change_history_ensure_schema($conn);

	audit_log_state_change_request($conn);
	kodus_enforce_maintenance_mode($conn);

	set_exception_handler(static function (Throwable $exception): void {
	    $connection = $GLOBALS['conn'] ?? null;
	    kodus_abort($connection instanceof mysqli ? $connection : null, 500, [
	        'detail' => 'An unhandled application exception was encountered.',
	        'exception' => $exception,
	        'redirect' => '',
	    ]);
	});

	register_shutdown_function(static function (): void {
	    $error = error_get_last();
	    if (!is_array($error)) {
	        return;
	    }

	    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
	    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
	        return;
	    }

	    $connection = $GLOBALS['conn'] ?? null;
	    kodus_abort($connection instanceof mysqli ? $connection : null, 500, [
	        'detail' => 'A fatal application error interrupted the request.',
	        'fatal' => $error,
	        'redirect' => '',
	    ]);
	});
?>
