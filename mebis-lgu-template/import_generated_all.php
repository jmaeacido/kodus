<?php

declare(strict_types=1);

ini_set('memory_limit', '1536M');
set_time_limit(0);

require_once __DIR__ . '/../security.php';
security_bootstrap_session();
security_configure_runtime_for_web();
security_require_method(['POST']);
security_require_csrf_token();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/generated_import.php';

auth_enforce_admin_generator_access();

function mebis_template_import_all_normalize_tokens(array $tokens): array
{
    return array_values(array_unique(array_filter(array_map(static function ($token) {
        $token = preg_replace('/[^a-f0-9]/i', '', (string) $token);
        return $token === '' ? null : $token;
    }, $tokens))));
}

function mebis_template_import_all_posted_tokens(): array
{
    $tokens = [];

    $tokensJson = (string) ($_POST['tokens_json'] ?? '');
    if ($tokensJson !== '') {
        $decodedTokens = json_decode($tokensJson, true);
        if (is_array($decodedTokens)) {
            $tokens = array_merge($tokens, $decodedTokens);
        }
    }

    foreach (['tokens', 'token', 'output_tokens'] as $field) {
        if (!isset($_POST[$field])) {
            continue;
        }

        $value = $_POST[$field];
        if (is_array($value)) {
            $tokens = array_merge($tokens, $value);
            continue;
        }

        $tokens[] = $value;
    }

    return mebis_template_import_all_normalize_tokens($tokens);
}

$tokens = mebis_template_import_all_posted_tokens();

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
