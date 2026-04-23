<?php

declare(strict_types=1);

require_once __DIR__ . '/filesystem_cache.php';

use MebisConsolidator\Helpers\FilesystemCache;
use PhpOffice\PhpSpreadsheet\Settings;

function mebis_bootstrap_spreadsheet_runtime(): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mebis-phpspreadsheet-cache' . DIRECTORY_SEPARATOR . bin2hex(random_bytes(6));
    Settings::setCache(new FilesystemCache($cacheDirectory));
    $initialized = true;
}
