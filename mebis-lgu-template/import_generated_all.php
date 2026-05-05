<?php

declare(strict_types=1);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
security_configure_runtime_for_web();
security_require_method(['POST']);
security_require_csrf_token();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/generated_import.php';

auth_enforce_admin_generator_access();

$tokensJson = (string) ($_POST['tokens_json'] ?? '[]');
$decodedTokens = json_decode($tokensJson, true);
$tokens = is_array($decodedTokens) ? $decodedTokens : [];
$tokens = array_values(array_unique(array_filter(array_map(static function ($token) {
    $token = preg_replace('/[^a-f0-9]/i', '', (string) $token);
    return $token === '' ? null : $token;
}, $tokens))));

if ($tokens === []) {
    $_SESSION['mebis_template_error'] = 'No generated template files were selected for import.';
    header('Location: index');
    exit;
}

$imported = [];
$skipped = [];

try {
    foreach ($tokens as $token) {
        $result = mebis_generated_import_output($conn, $token);
        if (!empty($result['skipped'])) {
            $skipped[] = $result;
            continue;
        }

        $imported[] = $result;
    }

    if ($imported === [] && $skipped !== []) {
        $_SESSION['mebis_template_success'] = count($skipped) . ' generated template file(s) were already imported.';
    } else {
        $batchIds = array_map(static function ($result) {
            return '#' . (string) ($result['batch_id'] ?? '');
        }, $imported);
        $_SESSION['mebis_template_success'] = count($imported) . ' generated template file(s) imported as separate batches'
            . ($batchIds !== [] ? ': ' . implode(', ', $batchIds) : '')
            . ($skipped !== [] ? '. Skipped ' . count($skipped) . ' already imported file(s).' : '.');
    }
} catch (Throwable $e) {
    $_SESSION['mebis_template_error'] = $e->getMessage();
}

header('Location: index');
exit;
