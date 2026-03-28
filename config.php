<?php
	require_once __DIR__ . '/security.php';
	require_once __DIR__ . '/env_helpers.php';
	require_once __DIR__ . '/audit_helpers.php';
	require_once __DIR__ . '/theme_helpers.php';
	require_once __DIR__ . '/vendor/autoload.php';

	security_configure_runtime_for_web();

	app_load_environment();

	security_enforce_same_origin();

	date_default_timezone_set('Asia/Manila');

	$host = app_env('DB_HOST', '127.0.0.1');
	$user = app_env('DB_USERNAME', 'root');
	$pass = app_env('DB_PASSWORD', '');
	$db   = app_env('DB_NAME', '');

	$conn = new mysqli($host, $user, $pass, $db);

	// Check connection
	if ($conn->connect_error) {
	    error_log("Database connection failed: " . $conn->connect_error);
	    http_response_code(500);
	    exit("Database connection failed.");
	}

	theme_ensure_schema($conn);

	audit_log_state_change_request($conn);
?>
