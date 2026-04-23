<?php
require_once __DIR__ . '/security.php';
security_bootstrap_session();
require_once __DIR__ . '/auth_helpers.php';

include('config.php');

auth_handle_page_access($conn);
?>
