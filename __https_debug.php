<?php
header('Content-Type: application/json');
echo json_encode([
  'HTTPS' => $_SERVER['HTTPS'] ?? null,
  'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? null,
  'REQUEST_SCHEME' => $_SERVER['REQUEST_SCHEME'] ?? null,
  'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
  'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
]);
