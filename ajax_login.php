<?php
require_once __DIR__ . '/security.php';
security_send_json([
    'success' => false,
    'message' => 'Legacy login endpoint disabled. Use the standard login form.'
], 410);
