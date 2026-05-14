<?php

declare(strict_types=1);

ini_set('memory_limit', '1024M');
set_time_limit(180);

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_helpers.php';
require_once __DIR__ . '/helpers/generator.php';

security_bootstrap_session();
security_require_method(['POST']);
security_require_csrf_token();
security_enforce_same_origin();
auth_enforce_admin_generator_access($conn);

function cash_advance_generation_error(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

try {
    if (!isset($_SESSION['selected_year'])) {
        throw new RuntimeException('Fiscal year not selected. Please select a fiscal year and try again.');
    }

    $selectedYear = (int) $_SESSION['selected_year'];
    $source = (string) ($_POST['source'] ?? 'database');
    if (!in_array($source, ['database', 'upload'], true)) {
        $source = 'database';
    }

    $manualValues = [];
    foreach (array_keys(cash_advance_manual_fields()) as $fieldName) {
        $manualValues[$fieldName] = cash_advance_post_value($_POST, $fieldName);
    }

    $includeTimeTally = true;
    if ($source === 'upload') {
        $manualBeneficiaryRate = (float) str_replace(',', '', trim((string) ($_POST['upload_beneficiary_amount'] ?? '')));
        if ($manualBeneficiaryRate <= 0) {
            throw new RuntimeException('Please enter a valid amount per beneficiary for the uploaded workbook.');
        }

        $isRrpCftw = (string) ($_POST['upload_rrp_cftw'] ?? 'yes') === 'yes';
        $includeTimeTally = (string) ($_POST['upload_include_tts'] ?? 'yes') === 'yes';
        if (!$isRrpCftw) {
            $manualValues['custom_particulars'] = cash_advance_post_value($_POST, 'custom_particulars');
            $manualValues['custom_atp_statement'] = cash_advance_post_value($_POST, 'custom_atp_statement');
            $manualValues['custom_tts_certification'] = cash_advance_post_value($_POST, 'custom_tts_certification');

            if ($manualValues['custom_particulars'] === '') {
                throw new RuntimeException('Please enter the Activity/Purpose/Particulars for the uploaded package.');
            }

            if ($manualValues['custom_atp_statement'] === '') {
                throw new RuntimeException('Please enter the Authority to Pay statement for the uploaded package.');
            }

            if ($includeTimeTally && $manualValues['custom_tts_certification'] === '') {
                throw new RuntimeException('Please enter the Time Tally Sheet certification or exclude TTS from the package.');
            }
        }

        $dataset = cash_advance_build_dataset_from_uploaded_meb($conn, $_FILES['meb_file'] ?? [], $selectedYear, $manualBeneficiaryRate);
        $payoutDate = '';
        $implementationDate = '';
    } else {
        $locations = cash_advance_location_options($conn, $selectedYear);
        $location = cash_advance_decode_location(trim((string) ($_POST['location'] ?? '')), $locations);
        $dataset = cash_advance_build_dataset($conn, $location['province'], $location['municipality'], $selectedYear);
        $payoutDate = cash_advance_payout_schedule_date($conn, $location['province'], $location['municipality'], $selectedYear);
        $implementationDate = cash_advance_latest_implementation_date($conn, $location['province'], $location['municipality']);
    }

    $context = array_merge($dataset, [
        'manual' => $manualValues,
        'payout_date' => $payoutDate,
        'implementation_date' => $implementationDate,
        'include_time_tally' => $includeTimeTally,
        'user_initials' => cash_advance_user_initials($_SESSION),
    ]);

    $package = cash_advance_generate_zip($context, $context['barangay_names']);

    audit_log(
        $conn,
        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'Generate Cash Advance Requirements',
        sprintf(
            'Generated cash advance requirement package for %s, %s fiscal year %d with %d beneficiaries across %d barangay(s), total amount %s.',
            (string) $context['municipality'],
            (string) $context['province'],
            $selectedYear,
            (int) $context['total_beneficiaries'],
            count($context['barangays']),
            number_format((float) $context['total_amount'], 2)
        ),
        security_get_client_ip()
    );

    $downloadName = (string) ($package['filename'] ?? 'Cash Advance Requirements.zip');
    $path = (string) ($package['path'] ?? '');
    if ($path === '' || !is_file($path)) {
        throw new RuntimeException('Generated package was not found.');
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($path);
    cash_advance_cleanup_generated_package($package);
    exit;
} catch (Throwable $exception) {
    if (isset($package) && is_array($package)) {
        cash_advance_cleanup_generated_package($package);
    }
    cash_advance_generation_error($exception->getMessage(), 400);
}
