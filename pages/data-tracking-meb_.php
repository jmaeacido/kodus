<?php
require_once __DIR__ . '/../header.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied. Admins only.";
    exit;
}

header('Location: ' . $app_root . 'pages/data-tracking-meb');
exit;
