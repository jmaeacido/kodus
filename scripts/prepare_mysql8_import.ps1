param(
    [Parameter(Mandatory = $true)]
    [string]$InputPath,

    [Parameter(Mandatory = $true)]
    [string]$OutputPath,

    [string]$TriggersOutputPath
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $InputPath)) {
    throw "Input dump not found: $InputPath"
}

$inputItem = Get-Item -LiteralPath $InputPath
$outputDir = Split-Path -Path $OutputPath -Parent

if ($outputDir -and -not (Test-Path -LiteralPath $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

$raw = Get-Content -LiteralPath $InputPath -Raw -Encoding UTF8

$zeroDateCount = ([regex]::Matches($raw, "'0000-00-00( 00:00:00)?'")).Count
$triggerPattern = '(?ms)\r?\n--\r?\n-- Triggers `[^`]+`\r?\n--\r?\n(?:DELIMITER \$\$.*?DELIMITER ;\r?\n?)+'
$triggerCount = ([regex]::Matches($raw, '(?im)^CREATE TRIGGER ')).Count
$hasUtf8mb4_0900 = $raw -match 'utf8mb4_0900_ai_ci'

$triggerSection = ''
$body = $raw
if ($triggerCount -gt 0) {
    $triggerMatch = [regex]::Match($raw, $triggerPattern)
    if ($triggerMatch.Success) {
        $triggerSection = $triggerMatch.Value.Trim()
        $body = $raw.Remove($triggerMatch.Index, $triggerMatch.Length)
    }
}

$preamble = @"
-- MySQL 8 / Navicat import-safe wrapper generated on $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
-- Source dump: $($inputItem.FullName)
-- Notes:
--   - Preserves legacy zero dates instead of converting them to NULL, because several target columns are NOT NULL.
--   - Relaxes session checks that commonly break GUI imports on stricter MySQL 8 sessions.
--   - Zero-date literals found: $zeroDateCount
--   - Triggers found: $triggerCount
--   - Contains utf8mb4_0900_ai_ci: $hasUtf8mb4_0900

SET NAMES utf8mb4;
SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;
SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS;
SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION;
SET @OLD_SQL_MODE=@@SESSION.sql_mode;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS;
SET @OLD_TIME_ZONE=@@SESSION.time_zone;
SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SET time_zone = '+00:00';

"@

$postamble = @"

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
SET SESSION sql_mode=@OLD_SQL_MODE;
SET time_zone=@OLD_TIME_ZONE;
SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;
SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;
SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;
"@

$wrapped = $preamble + $body.TrimEnd("`r", "`n") + "`r`n" + $postamble
Set-Content -LiteralPath $OutputPath -Value $wrapped -Encoding UTF8

$triggersWritten = $false
if ($TriggersOutputPath -and $triggerSection -ne '') {
    $triggersDir = Split-Path -Path $TriggersOutputPath -Parent
    if ($triggersDir -and -not (Test-Path -LiteralPath $triggersDir)) {
        New-Item -ItemType Directory -Path $triggersDir -Force | Out-Null
    }

    $triggerFile = @"
-- Trigger definitions extracted from $($inputItem.FullName)
-- Import this only if the target server allows CREATE TRIGGER
-- If binary logging blocks this with error 1419, ask an admin to set:
--   SET GLOBAL log_bin_trust_function_creators = 1;

$triggerSection
"@

    Set-Content -LiteralPath $TriggersOutputPath -Value $triggerFile -Encoding UTF8
    $triggersWritten = $true
}

$summary = [pscustomobject]@{
    InputPath        = $inputItem.FullName
    OutputPath       = (Resolve-Path -LiteralPath $OutputPath).Path
    TriggersPath     = if ($triggersWritten) { (Resolve-Path -LiteralPath $TriggersOutputPath).Path } else { $null }
    InputBytes       = $inputItem.Length
    OutputBytes      = (Get-Item -LiteralPath $OutputPath).Length
    ZeroDateLiterals = $zeroDateCount
    TriggerCount     = $triggerCount
    HasUtf8mb4_0900  = $hasUtf8mb4_0900
}

$summary | ConvertTo-Json -Compress
