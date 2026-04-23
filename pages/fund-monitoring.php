<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/../fund_monitoring_helpers.php';

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color:red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$userType = (string) ($_SESSION['user_type'] ?? 'user');
$isAdmin = $userType === 'admin';
$currentCalendarYear = (int) date('Y');
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : (($year === $currentCalendarYear) ? (int) date('n') : 1);
$selectedMonth = max(1, min(12, $selectedMonth));
$monthLabels = fund_monitoring_month_labels();

if ($year === $currentCalendarYear) {
    fund_monitoring_seed_budget_items($conn, $year, $userId > 0 ? $userId : null);
} else {
    fund_monitoring_seed_object_codes($conn, $year, $userId > 0 ? $userId : null);
}

$flash = $_SESSION['fund_monitoring_flash'] ?? null;
unset($_SESSION['fund_monitoring_flash']);

$objectCodes = fund_monitoring_list_object_codes($conn, $year);
$items = fund_monitoring_list_items_with_entries($conn, $year);
$fundMonitoringRefreshToken = fund_monitoring_change_token($conn, $year);

function fm_currency(float $value): string
{
    return number_format($value, 2);
}

function fm_matrix_currency(float $value): string
{
    return abs($value) < 0.00001 ? '-' : fm_currency($value);
}

function fm_percent(float $numerator, float $denominator): float
{
    if (abs($denominator) < 0.00001) {
        return 0.0;
    }

    return ($numerator / $denominator) * 100;
}

$grandTotals = [
    'authorized' => 0.0,
    'realignment' => 0.0,
    'adjusted' => 0.0,
    'monthly' => [],
    'quarterly' => [],
    'total_obligations' => 0.0,
    'total_disbursement' => 0.0,
];

for ($month = 1; $month <= 12; $month++) {
    $grandTotals['monthly'][$month] = ['obligations' => 0.0, 'disbursement' => 0.0];
}
for ($quarter = 1; $quarter <= 4; $quarter++) {
    $grandTotals['quarterly'][$quarter] = ['obligations' => 0.0, 'disbursement' => 0.0];
}

$preparedItems = [];
foreach ($items as $item) {
    $quarterly = [];
    $totalObligations = 0.0;
    $totalDisbursement = 0.0;

    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $quarterly[$quarter] = ['obligations' => 0.0, 'disbursement' => 0.0];
    }

    foreach ($item['monthly'] as $month => $monthlyValues) {
        $obligations = (float) ($monthlyValues['obligations'] ?? 0);
        $disbursement = (float) ($monthlyValues['disbursement'] ?? 0);
        $quarterIndex = (int) ceil($month / 3);

        $quarterly[$quarterIndex]['obligations'] += $obligations;
        $quarterly[$quarterIndex]['disbursement'] += $disbursement;
        $totalObligations += $obligations;
        $totalDisbursement += $disbursement;

        $grandTotals['monthly'][$month]['obligations'] += $obligations;
        $grandTotals['monthly'][$month]['disbursement'] += $disbursement;
    }

    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $grandTotals['quarterly'][$quarter]['obligations'] += $quarterly[$quarter]['obligations'];
        $grandTotals['quarterly'][$quarter]['disbursement'] += $quarterly[$quarter]['disbursement'];
    }

    $varianceObligations = $item['adjusted_appropriation'] - $totalObligations;
    $varianceDisbursement = $item['adjusted_appropriation'] - $totalDisbursement;

    $item['quarterly'] = $quarterly;
    $item['total_obligations'] = $totalObligations;
    $item['total_disbursement'] = $totalDisbursement;
    $item['variance_obligations'] = $varianceObligations;
    $item['variance_disbursement'] = $varianceDisbursement;
    $item['utilization_obligations'] = fm_percent($totalObligations, $item['adjusted_appropriation']);
    $item['utilization_disbursement'] = fm_percent($totalDisbursement, $item['adjusted_appropriation']);

    $grandTotals['authorized'] += $item['authorized_appropriation'];
    $grandTotals['realignment'] += $item['realignment'];
    $grandTotals['adjusted'] += $item['adjusted_appropriation'];
    $grandTotals['total_obligations'] += $totalObligations;
    $grandTotals['total_disbursement'] += $totalDisbursement;

    $preparedItems[] = $item;
}

$grandTotals['variance_obligations'] = $grandTotals['adjusted'] - $grandTotals['total_obligations'];
$grandTotals['variance_disbursement'] = $grandTotals['adjusted'] - $grandTotals['total_disbursement'];
$grandTotals['utilization_obligations'] = fm_percent($grandTotals['total_obligations'], $grandTotals['adjusted']);
$grandTotals['utilization_disbursement'] = fm_percent($grandTotals['total_disbursement'], $grandTotals['adjusted']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | Fund Monitoring</title>
  <style>
    :root {
      --fm-bg:
        radial-gradient(circle at top right, rgba(40, 167, 69, 0.12), transparent 24%),
        radial-gradient(circle at left bottom, rgba(23, 162, 184, 0.12), transparent 20%),
        linear-gradient(180deg, #f7fbff 0%, #edf4fb 100%);
      --fm-panel: rgba(255, 255, 255, 0.94);
      --fm-panel-strong: #ffffff;
      --fm-border: rgba(15, 23, 42, 0.09);
      --fm-text: #1f2d3d;
      --fm-muted: #5f7488;
      --fm-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
      --fm-accent: #0d6efd;
      --fm-good: #198754;
      --fm-warn: #dc3545;
      --fm-soft: rgba(13, 110, 253, 0.06);
      --fm-band-a: #f4f8ff;
      --fm-band-b: #eefaf6;
      --fm-band-a-strong: #e8f1ff;
      --fm-band-b-strong: #def5ea;
    }

    body[data-theme="dark"] {
      --fm-bg:
        radial-gradient(circle at top right, rgba(40, 167, 69, 0.14), transparent 24%),
        radial-gradient(circle at left bottom, rgba(23, 162, 184, 0.14), transparent 20%),
        linear-gradient(180deg, #0f172a 0%, #111827 100%);
      --fm-panel: rgba(17, 24, 39, 0.92);
      --fm-panel-strong: #111c2d;
      --fm-border: rgba(148, 163, 184, 0.16);
      --fm-text: #e8eef5;
      --fm-muted: #9fb0c2;
      --fm-shadow: 0 22px 48px rgba(0, 0, 0, 0.3);
      --fm-accent: #7dc4ff;
      --fm-good: #7CFC9B;
      --fm-warn: #ff9f9f;
      --fm-soft: rgba(125, 196, 255, 0.08);
      --fm-band-a: #1b3554;
      --fm-band-b: #1c4338;
      --fm-band-a-strong: #23456e;
      --fm-band-b-strong: #255549;
    }

    .fund-monitoring-page .content-wrapper { background: var(--fm-bg); }
    .fm-card, .fm-stat {
      border: 1px solid var(--fm-border);
      background: var(--fm-panel);
      color: var(--fm-text);
      border-radius: 1rem;
      box-shadow: var(--fm-shadow);
    }
    .fm-hero { padding: 1.4rem; margin-bottom: 1rem; background: linear-gradient(135deg, var(--fm-panel-strong) 0%, rgba(13, 110, 253, 0.08) 100%); }
    .fm-hero h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
    .fm-hero p { margin: 0.5rem 0 0; color: var(--fm-muted); max-width: 860px; }
    .fm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
    .fm-stat { padding: 1rem 1.1rem; }
    .fm-stat span { display: block; }
    .fm-stat-label { color: var(--fm-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.4rem; }
    .fm-stat-value { color: var(--fm-text); font-size: 1.25rem; font-weight: 700; }
    .fm-section { margin-bottom: 1rem; padding: 0.95rem 1rem; }
    .fm-section h3 { margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 700; color: var(--fm-text); }
    .fm-section p, .fm-note { color: var(--fm-muted); }
    .fm-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.9rem; align-items: end; }
    .fm-table-wrap { overflow: auto; border-radius: 1rem; border: 1px solid var(--fm-border); background: var(--fm-panel); }
    .fm-table { width: 100%; min-width: 2600px; border-collapse: separate; border-spacing: 0; color: var(--fm-text); font-size: 0.78rem; }
    .fm-table th, .fm-table td { border-right: 1px solid var(--fm-border); border-bottom: 1px solid var(--fm-border); padding: 0.5rem 0.45rem; vertical-align: middle; white-space: nowrap; background: var(--fm-panel); }
    .fm-table th { background: rgba(13, 110, 253, 0.08); font-weight: 700; text-align: center; }
    body[data-theme="dark"] .fm-table th { background: rgba(125, 196, 255, 0.1); }
    .fm-table tbody tr:nth-child(odd) td { background: rgba(13, 110, 253, 0.03); }
    .fm-table tbody tr:nth-child(even) td { background: rgba(23, 162, 184, 0.06); }
    body[data-theme="dark"] .fm-table tbody tr:nth-child(odd) td { background: rgba(125, 196, 255, 0.08); }
    body[data-theme="dark"] .fm-table tbody tr:nth-child(even) td { background: rgba(32, 201, 151, 0.1); }
    .fm-table thead .fm-band-a { background: var(--fm-band-a-strong); }
    .fm-table thead .fm-band-b { background: var(--fm-band-b-strong); }
    .fm-table tbody .fm-band-a,
    .fm-table thead tr.fm-header-total .fm-band-a { background: var(--fm-band-a); }
    .fm-table tbody .fm-band-b,
    .fm-table thead tr.fm-header-total .fm-band-b { background: var(--fm-band-b); }
    .fm-sticky-saro { min-width: 150px; width: 150px; }
    .fm-sticky-object { min-width: 220px; width: 220px; }
    .fm-table .fm-sticky-saro {
      position: sticky;
      left: 0;
      z-index: 30;
      background: #ffffff;
      font-weight: 700;
    }
    .fm-table .fm-sticky-object {
      position: sticky;
      left: 150px;
      z-index: 29;
      background: #ffffff;
      font-weight: 700;
    }
    body[data-theme="dark"] .fm-table .fm-sticky-saro { background: #111c2d; }
    body[data-theme="dark"] .fm-table .fm-sticky-object { background: #111c2d; }
    .fm-table tbody .fm-sticky-saro { z-index: 20; }
    .fm-table tbody .fm-sticky-object { z-index: 19; }
    .fm-table tbody tr:nth-child(odd) .fm-sticky-saro,
    .fm-table tbody tr:nth-child(odd) .fm-sticky-object { background: #f7fbff; }
    .fm-table tbody tr:nth-child(even) .fm-sticky-saro,
    .fm-table tbody tr:nth-child(even) .fm-sticky-object { background: #eef7fb; }
    body[data-theme="dark"] .fm-table tbody tr:nth-child(odd) .fm-sticky-saro,
    body[data-theme="dark"] .fm-table tbody tr:nth-child(odd) .fm-sticky-object { background: #18304d; }
    body[data-theme="dark"] .fm-table tbody tr:nth-child(even) .fm-sticky-saro,
    body[data-theme="dark"] .fm-table tbody tr:nth-child(even) .fm-sticky-object { background: #1b3a34; }
    .fm-table thead .fm-sticky-saro { z-index: 40; }
    .fm-table thead .fm-sticky-object { z-index: 39; }
    .fm-table thead tr.fm-header-total td { background: rgba(25, 135, 84, 0.12); font-weight: 700; }
    .fm-table thead tr.fm-header-total .fm-sticky-saro,
    .fm-table thead tr.fm-header-total .fm-sticky-object {
      background: #dff3e6;
    }
    .fm-table thead tr.fm-header-total .fm-sticky-saro { z-index: 42; }
    .fm-table thead tr.fm-header-total .fm-sticky-object { z-index: 41; }
    body[data-theme="dark"] .fm-table thead tr.fm-header-total td { background: rgba(124, 252, 155, 0.16); }
    body[data-theme="dark"] .fm-table thead tr.fm-header-total .fm-sticky-saro,
    body[data-theme="dark"] .fm-table thead tr.fm-header-total .fm-sticky-object {
      background: #245040;
    }
    .fm-chip-list { display: flex; flex-wrap: wrap; gap: 0.45rem; max-height: 180px; overflow: auto; padding: 0.2rem 0; }
    .fm-chip { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.7rem; border: 1px solid var(--fm-border); border-radius: 999px; background: var(--fm-soft); color: var(--fm-text); font-size: 0.82rem; }
    .fm-chip input { margin: 0; }
    .fm-month-table input[type="number"] { min-width: 120px; }
    .fm-actions { display: flex; flex-wrap: wrap; gap: 0.65rem; }
    .fm-number-negative { color: var(--fm-warn); font-weight: 700; }
    .fm-number-positive { color: var(--fm-good); font-weight: 700; }
    .fm-empty { padding: 2rem; text-align: center; color: var(--fm-muted); }
    .fm-toolbar { display: flex; justify-content: space-between; align-items: end; gap: 0.9rem; flex-wrap: wrap; margin-bottom: 0.85rem; }
    .fm-toolbar-copy h3 { margin-bottom: 0.15rem; }
    .fm-toolbar-copy p { margin: 0; font-size: 0.88rem; }
    .fm-toolbar-controls { display: flex; gap: 0.75rem; align-items: end; flex-wrap: wrap; }
    .fm-toolbar-field { min-width: 180px; }
    .fm-modal-table { min-width: 1380px; }
    .fm-table thead tr.fm-header-groups th { font-size: 0.82rem; letter-spacing: 0.03em; }
    .fm-table thead tr.fm-header-units th { font-size: 0.75rem; text-transform: uppercase; }
    .fm-filter-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.65rem; }
    .fm-transaction-status-row td { text-align: center; color: var(--fm-muted); padding: 1.2rem 0.8rem; }
    .fm-total-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 140px; }
    .fm-total-label { font-size: 0.68rem; letter-spacing: 0.04em; text-transform: uppercase; color: var(--fm-muted); }
    .fm-total-value { font-weight: 700; color: var(--fm-text); }
    .swal2-popup.fm-swal-popup {
      border-radius: 22px;
      padding: 1.25rem;
      background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(240, 247, 255, 0.98));
      color: #1f2d3d;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
      border: 1px solid rgba(13, 110, 253, 0.12);
    }
    body[data-theme="dark"] .swal2-popup.fm-swal-popup {
      background:
        linear-gradient(135deg, rgba(17, 28, 45, 0.98), rgba(19, 39, 65, 0.98));
      color: #e8eef5;
      border-color: rgba(125, 196, 255, 0.2);
      box-shadow: 0 26px 70px rgba(0, 0, 0, 0.38);
    }
    .swal2-popup.fm-swal-popup .swal2-title {
      font-size: 1.2rem;
      font-weight: 700;
      color: inherit;
    }
    .swal2-popup.fm-swal-popup .swal2-html-container {
      margin-top: 0.45rem;
      color: inherit;
      line-height: 1.55;
    }
    .swal2-popup.fm-swal-popup .swal2-confirm {
      border-radius: 999px !important;
      padding: 0.68rem 1.25rem !important;
      font-weight: 700 !important;
      box-shadow: 0 14px 28px rgba(13, 110, 253, 0.22);
    }
    .swal2-popup.fm-swal-popup .swal2-icon {
      border-width: 0 !important;
      transform: scale(0.92);
      margin-top: 0.2rem;
      margin-bottom: 0.35rem;
    }
    .swal2-popup.fm-swal-popup.swal2-icon-success .swal2-confirm {
      background: linear-gradient(135deg, #198754, #27ae60) !important;
    }
    .swal2-popup.fm-swal-popup.swal2-icon-error .swal2-confirm {
      background: linear-gradient(135deg, #dc3545, #ff6b6b) !important;
    }
    .swal2-popup.fm-swal-popup.swal2-icon-warning .swal2-confirm {
      background: linear-gradient(135deg, #f59f00, #fcbf49) !important;
      color: #1f2d3d !important;
      box-shadow: 0 14px 28px rgba(245, 159, 0, 0.24);
    }
    .swal2-popup.fm-swal-popup.swal2-icon-info .swal2-confirm,
    .swal2-popup.fm-swal-popup.swal2-icon-question .swal2-confirm {
      background: linear-gradient(135deg, #0d6efd, #46a6ff) !important;
    }
    .fund-monitoring-page .content-header h1, .fund-monitoring-page .breadcrumb-item.active, .fund-monitoring-page .breadcrumb-item a, .fund-monitoring-page label, .fund-monitoring-page .card-title { color: var(--fm-text) !important; }
    .fund-monitoring-page .form-control, .fund-monitoring-page .custom-select, .fund-monitoring-page textarea, .fund-monitoring-page select { background: var(--fm-panel-strong); color: var(--fm-text); border-color: var(--fm-border); }
    .fund-monitoring-page .form-control::placeholder, .fund-monitoring-page textarea::placeholder { color: var(--fm-muted); }
    .fund-monitoring-page .modal-content { background: var(--fm-panel-strong); color: var(--fm-text); border-color: var(--fm-border); }
    .fund-monitoring-page .modal-header, .fund-monitoring-page .modal-footer { border-color: var(--fm-border); }
    @media (max-width: 1600px) {
      .fm-hero { padding: 1.15rem; }
      .fm-stat { padding: 0.9rem 1rem; }
      .fm-section { padding: 0.85rem 0.9rem; }
      .fm-grid { gap: 0.85rem; }
      .fm-form-grid { gap: 0.8rem; }
      .fm-table th, .fm-table td { padding: 0.45rem 0.4rem; }
    }
    @media (max-width: 1366px) {
      .fm-hero { margin-bottom: 0.85rem; }
      .fm-hero h1 { font-size: 1.35rem; }
      .fm-hero p { font-size: 0.9rem; }
      .fm-stat-label { font-size: 0.76rem; }
      .fm-stat-value { font-size: 1.12rem; }
      .fm-section h3 { font-size: 0.98rem; }
      .fm-toolbar-copy p { font-size: 0.82rem; }
      .fm-toolbar,
      .fm-toolbar-controls {
        gap: 0.6rem;
      }
      .fm-table { font-size: 0.74rem; }
      .fm-modal-table { min-width: 1280px; }
    }
    @media (max-width: 1280px) {
      .fm-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
      .fm-hero { padding: 1rem; }
      .fm-stat { padding: 0.8rem 0.85rem; }
      .fm-section { padding: 0.8rem 0.85rem; }
      .fm-toolbar-field { min-width: 160px; }
      .fm-table { min-width: 2320px; }
      .fm-sticky-saro { min-width: 130px; width: 130px; }
      .fm-sticky-object { min-width: 200px; width: 200px; }
      .fm-table .fm-sticky-object { left: 130px; }
    }
    @media (max-width: 1024px) {
      .fm-hero h1 { font-size: 1.18rem; }
      .fm-hero p,
      .fm-note,
      .fm-toolbar-copy p {
        font-size: 0.8rem;
      }
      .fm-grid {
        gap: 0.7rem;
      }
      .fm-stat-value {
        font-size: 1rem;
      }
      .fm-toolbar-field {
        min-width: 145px;
      }
    }
    @media (max-width: 992px) { .fm-toolbar { align-items: stretch; } .fm-toolbar-controls { width: 100%; } }
    @media (max-width: 768px) { .fm-hero h1 { font-size: 1.25rem; } }
  </style>
</head>
<body class="fund-monitoring-page">
<div class="wrapper" id="fund-monitoring-live-root" data-refresh-token="<?= htmlspecialchars($fundMonitoringRefreshToken, ENT_QUOTES, 'UTF-8') ?>">
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Fund Monitoring</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">Fund Monitoring</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="fm-card fm-hero">
          <h1>Monthly fund utilization tracker for fiscal year <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?></h1>
          <p>Monitor authorized appropriations, realignment, adjusted appropriation, monthly accomplishments, quarterly rollups, utilization rates, and variance reasons in one annual workspace. The current-year baseline is prefilled with your common object codes and budget amounts, and you can revise SARO and object-code details anytime.</p>
        </div>

        <div class="fm-grid" id="fund-monitoring-summary-region">
          <div class="fm-stat">
            <span class="fm-stat-label">Tracked Object Codes</span>
            <span class="fm-stat-value"><?= number_format(count($preparedItems)) ?></span>
          </div>
          <div class="fm-stat">
            <span class="fm-stat-label">Adjusted Appropriation</span>
            <span class="fm-stat-value">PHP <?= fm_currency($grandTotals['adjusted']) ?></span>
          </div>
          <div class="fm-stat">
            <span class="fm-stat-label">Total Obligations</span>
            <span class="fm-stat-value">PHP <?= fm_currency($grandTotals['total_obligations']) ?></span>
          </div>
          <div class="fm-stat">
            <span class="fm-stat-label">Total Disbursement</span>
            <span class="fm-stat-value">PHP <?= fm_currency($grandTotals['total_disbursement']) ?></span>
          </div>
        </div>

        <div class="fm-card fm-section">
          <div class="fm-toolbar">
            <div class="fm-toolbar-copy">
              <h3>Annual Fund Monitoring Matrix</h3>
              <p><?= $isAdmin ? 'Use the month selector and action buttons to keep the matrix current without leaving the page.' : 'Use the month selector to review fund monitoring data across the fiscal year.' ?></p>
            </div>
            <div class="fm-toolbar-controls">
              <?php if ($isAdmin): ?>
              <div class="fm-toolbar-field">
                <label for="entry_month" class="mb-1">Editing Month</label>
                <select class="form-control" name="entry_month" id="entry_month">
                  <?php foreach ($monthLabels as $monthNumber => $monthLabel): ?>
                  <option value="<?= $monthNumber ?>" <?= $selectedMonth === $monthNumber ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              <div class="fm-actions">
                <?php if ($isAdmin && $preparedItems !== []): ?>
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#transactionModal">Update Transactions</button>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#fundItemModal" data-mode="create">Add Item</button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div id="fund-monitoring-matrix-region">
          <?php if ($preparedItems === []): ?>
          <div class="fm-empty">No annual fund records to display yet.</div>
          <?php else: ?>
          <div class="fm-table-wrap">
            <table class="fm-table">
              <thead>
                <tr class="fm-header-groups">
                  <th rowspan="3" class="fm-sticky-saro">SARO No.</th>
                  <th rowspan="3" class="fm-sticky-object">Object Code</th>
                  <th rowspan="3">Authorized</th>
                  <th rowspan="3">Realignment</th>
                  <th rowspan="3">Adjusted</th>
                  <th colspan="24" class="fm-band-a">Monthly Accomplishment</th>
                  <th colspan="8" class="fm-band-b">Quarterly Accomplishment</th>
                  <th colspan="2" rowspan="2" class="fm-band-a">Total Accomplishment</th>
                  <th colspan="2" rowspan="2" class="fm-band-b">Variance</th>
                  <th colspan="2" rowspan="2" class="fm-band-a">% Fund Utilization</th>
                  <th colspan="2" rowspan="2" class="fm-band-b">Reasons for Variance</th>
                </tr>
                <tr class="fm-header-groups">
                  <?php foreach ($monthLabels as $monthLabel): ?>
                  <?php $monthBandClass = ((int) array_search($monthLabel, array_values($monthLabels), true) + 1) % 2 === 1 ? 'fm-band-a' : 'fm-band-b'; ?>
                  <th colspan="2" class="<?= $monthBandClass ?>"><?= htmlspecialchars(strtoupper($monthLabel), ENT_QUOTES, 'UTF-8') ?></th>
                  <?php endforeach; ?>
                  <?php for ($quarter = 1; $quarter <= 4; $quarter++): ?>
                  <th colspan="2" class="<?= $quarter % 2 === 1 ? 'fm-band-a' : 'fm-band-b' ?>">Q<?= $quarter ?></th>
                  <?php endfor; ?>
                </tr>
                <tr class="fm-header-units">
                  <?php foreach ($monthLabels as $monthNumber => $monthLabel): ?>
                  <?php $monthBandClass = $monthNumber % 2 === 1 ? 'fm-band-a' : 'fm-band-b'; ?>
                  <th class="<?= $monthBandClass ?>">Obligations</th>
                  <th class="<?= $monthBandClass ?>">Disbursement</th>
                  <?php endforeach; ?>
                  <?php for ($quarter = 1; $quarter <= 4; $quarter++): ?>
                  <th class="<?= $quarter % 2 === 1 ? 'fm-band-a' : 'fm-band-b' ?>">Obligations</th>
                  <th class="<?= $quarter % 2 === 1 ? 'fm-band-a' : 'fm-band-b' ?>">Disbursement</th>
                  <?php endfor; ?>
                  <th class="fm-band-a">Obligations</th>
                  <th class="fm-band-a">Disbursement</th>
                  <th class="fm-band-b">Obligations</th>
                  <th class="fm-band-b">Disbursement</th>
                  <th class="fm-band-a">Obligations</th>
                  <th class="fm-band-a">Disbursement</th>
                  <th class="fm-band-b">Obligations</th>
                  <th class="fm-band-b">Disbursement</th>
                </tr>
                <tr class="fm-header-total">
                  <td class="fm-sticky-saro">TOTAL</td>
                  <td class="fm-sticky-object"></td>
                  <td class="text-right"><?= fm_matrix_currency($grandTotals['authorized']) ?></td>
                  <td class="text-right"><?= fm_matrix_currency($grandTotals['realignment']) ?></td>
                  <td class="text-right"><?= fm_matrix_currency($grandTotals['adjusted']) ?></td>
                  <?php foreach ($monthLabels as $monthNumber => $monthLabel): ?>
                  <?php $monthBandClass = $monthNumber % 2 === 1 ? 'fm-band-a' : 'fm-band-b'; ?>
                  <td class="text-right <?= $monthBandClass ?>"><?= fm_matrix_currency((float) $grandTotals['monthly'][$monthNumber]['obligations']) ?></td>
                  <td class="text-right <?= $monthBandClass ?>"><?= fm_matrix_currency((float) $grandTotals['monthly'][$monthNumber]['disbursement']) ?></td>
                  <?php endforeach; ?>
                  <?php for ($quarter = 1; $quarter <= 4; $quarter++): ?>
                  <td class="text-right <?= $quarter % 2 === 1 ? 'fm-band-a' : 'fm-band-b' ?>"><?= fm_matrix_currency((float) $grandTotals['quarterly'][$quarter]['obligations']) ?></td>
                  <td class="text-right <?= $quarter % 2 === 1 ? 'fm-band-a' : 'fm-band-b' ?>"><?= fm_matrix_currency((float) $grandTotals['quarterly'][$quarter]['disbursement']) ?></td>
                  <?php endfor; ?>
                  <td class="text-right fm-band-a"><?= fm_matrix_currency($grandTotals['total_obligations']) ?></td>
                  <td class="text-right fm-band-a"><?= fm_matrix_currency($grandTotals['total_disbursement']) ?></td>
                  <td class="text-right fm-band-b"><?= fm_matrix_currency($grandTotals['variance_obligations']) ?></td>
                  <td class="text-right fm-band-b"><?= fm_matrix_currency($grandTotals['variance_disbursement']) ?></td>
                  <td class="text-right fm-band-a"><?= number_format($grandTotals['utilization_obligations'], 2) ?>%</td>
                  <td class="text-right fm-band-a"><?= number_format($grandTotals['utilization_disbursement'], 2) ?>%</td>
                  <td class="fm-band-b">-</td>
                  <td class="fm-band-b">-</td>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($preparedItems as $item): ?>
                <?php
                  $rowJson = htmlspecialchars(json_encode([
                      'id' => $item['id'],
                      'saro_number' => $item['saro_number'],
                      'object_code_name' => $item['object_code_name'],
                      'authorized_appropriation' => $item['authorized_appropriation'],
                      'realignment' => $item['realignment'],
                      'display_order' => $item['display_order'],
                      'reason_obligation' => $item['reason_obligation'],
                      'reason_disbursement' => $item['reason_disbursement'],
                  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                  <td class="fm-sticky-saro">
                    <div><?= htmlspecialchars($item['saro_number'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-link btn-sm p-0 mt-1 fm-edit-item" data-item="<?= $rowJson ?>">Edit</button>
                    <?php endif; ?>
                  </td>
                  <td class="fm-sticky-object"><?= htmlspecialchars($item['object_code_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-right"><?= fm_matrix_currency($item['authorized_appropriation']) ?></td>
                  <td class="text-right <?= $item['realignment'] < 0 ? 'fm-number-negative' : ($item['realignment'] > 0 ? 'fm-number-positive' : '') ?>"><?= fm_matrix_currency($item['realignment']) ?></td>
                  <td class="text-right"><?= fm_matrix_currency($item['adjusted_appropriation']) ?></td>
                  <?php foreach ($monthLabels as $monthNumber => $monthLabel): ?>
                  <?php $monthBandClass = $monthNumber % 2 === 1 ? 'fm-band-a' : 'fm-band-b'; ?>
                  <td class="text-right <?= $monthBandClass ?>"><?= fm_matrix_currency((float) $item['monthly'][$monthNumber]['obligations']) ?></td>
                  <td class="text-right <?= $monthBandClass ?>"><?= fm_matrix_currency((float) $item['monthly'][$monthNumber]['disbursement']) ?></td>
                  <?php endforeach; ?>
                  <?php for ($quarter = 1; $quarter <= 4; $quarter++): ?>
                  <td class="text-right <?= $quarter % 2 === 1 ? 'fm-band-a' : 'fm-band-b' ?>"><?= fm_matrix_currency((float) $item['quarterly'][$quarter]['obligations']) ?></td>
                  <td class="text-right <?= $quarter % 2 === 1 ? 'fm-band-a' : 'fm-band-b' ?>"><?= fm_matrix_currency((float) $item['quarterly'][$quarter]['disbursement']) ?></td>
                  <?php endfor; ?>
                  <td class="text-right fm-band-a"><?= fm_matrix_currency($item['total_obligations']) ?></td>
                  <td class="text-right fm-band-a"><?= fm_matrix_currency($item['total_disbursement']) ?></td>
                  <td class="text-right fm-band-b <?= $item['variance_obligations'] < 0 ? 'fm-number-negative' : '' ?>"><?= fm_matrix_currency($item['variance_obligations']) ?></td>
                  <td class="text-right fm-band-b <?= $item['variance_disbursement'] < 0 ? 'fm-number-negative' : '' ?>"><?= fm_matrix_currency($item['variance_disbursement']) ?></td>
                  <td class="text-right fm-band-a"><?= number_format($item['utilization_obligations'], 2) ?>%</td>
                  <td class="text-right fm-band-a"><?= number_format($item['utilization_disbursement'], 2) ?>%</td>
                  <td class="fm-band-b"><?= htmlspecialchars($item['reason_obligation'] !== '' ? $item['reason_obligation'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="fm-band-b"><?= htmlspecialchars($item['reason_disbursement'] !== '' ? $item['reason_disbursement'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal fade" id="fundItemModal" tabindex="-1" role="dialog" aria-labelledby="fundItemModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="post" action="save_fund_monitoring.php" id="fundItemForm">
        <div class="modal-header">
          <h5 class="modal-title" id="fundItemModalLabel">Add Fund Monitoring Item</h5>
          <button type="button" class="close text-reset" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="save_item">
          <input type="hidden" name="record_id" id="fund-record-id" value="">
          <input type="hidden" name="pap_name" id="fund-pap-name" value="">
          <div class="fm-form-grid">
            <div><label for="fund-saro-number">SARO Number</label><input type="text" class="form-control" name="saro_number" id="fund-saro-number" required></div>
            <div><label for="fund-display-order">Display Order</label><input type="number" class="form-control" name="display_order" id="fund-display-order" min="0" value="0"></div>
            <div>
              <label for="fund-object-code">Object Code</label>
              <select class="form-control" name="object_code_name" id="fund-object-code" required>
                <option value="">Select object code</option>
                <?php foreach ($objectCodes as $code): ?>
                <option value="<?= htmlspecialchars($code['object_code_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($code['object_code_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
                <option value="__custom__">Custom object code</option>
              </select>
            </div>
            <div id="fund-custom-code-wrap" style="display:none;"><label for="fund-custom-code">Custom Object Code</label><input type="text" class="form-control" name="custom_object_code_name" id="fund-custom-code"></div>
            <div><label for="fund-authorized">Authorized Appropriation</label><input type="number" step="0.01" min="0" class="form-control" name="authorized_appropriation" id="fund-authorized" required></div>
            <div><label for="fund-realignment">Realignment</label><input type="number" step="0.01" class="form-control" name="realignment" id="fund-realignment" value="0.00"></div>
            <div style="grid-column: 1 / -1;"><label for="fund-reason-obligation">Reason for Variance - Obligations</label><textarea class="form-control" name="reason_obligation" id="fund-reason-obligation" rows="2"></textarea></div>
            <div style="grid-column: 1 / -1;"><label for="fund-reason-disbursement">Reason for Variance - Disbursement</label><textarea class="form-control" name="reason_disbursement" id="fund-reason-disbursement" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Item</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="transactionModal" tabindex="-1" role="dialog" aria-labelledby="transactionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <form method="post" action="save_fund_monitoring.php" id="transactionForm">
        <div class="modal-header">
          <h5 class="modal-title" id="transactionModalLabel">Add / Edit Transactions</h5>
          <button type="button" class="close text-reset" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(security_get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="save_month_entries">
          <div class="fm-form-grid mb-3">
            <div>
              <label for="transaction-entry-month">Month / Period</label>
              <select class="form-control" name="entry_month" id="transaction-entry-month">
                <?php foreach ($monthLabels as $monthNumber => $monthLabel): ?>
                <option value="<?= $monthNumber ?>" <?= $selectedMonth === $monthNumber ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="transaction-search">Search Object Codes</label>
              <input type="text" class="form-control" id="transaction-search" placeholder="Filter rows by object code or SARO">
            </div>
          </div>

          <div class="fm-filter-actions">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="transaction-select-all">Select All</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="transaction-deselect-all">Deselect All</button>
          </div>

          <div class="fm-chip-list mb-3" id="transaction-code-filters">
            <?php foreach ($objectCodes as $code): ?>
            <label class="fm-chip">
              <input type="checkbox" class="fm-transaction-filter" value="<?= htmlspecialchars($code['object_code_name'], ENT_QUOTES, 'UTF-8') ?>" checked>
              <span><?= htmlspecialchars($code['object_code_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <?php endforeach; ?>
          </div>

          <div class="fm-table-wrap fm-month-table">
            <table class="fm-table fm-modal-table">
              <thead>
                <tr>
                  <th>SARO No.</th>
                  <th>Object Code</th>
                  <th>Obligations</th>
                  <th>Disbursement</th>
                  <th>Total Obligations</th>
                  <th>Total Disbursement</th>
                  <th>Adjusted Appropriation</th>
                </tr>
              </thead>
              <tbody id="transaction-modal-body">
                <tr class="fm-transaction-status-row">
                  <td colspan="7">Open the modal to load transactions.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Transactions</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="<?php echo $app_root; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $app_root; ?>dist/js/adminlte.min.js"></script>
<script src="<?php echo $app_root; ?>plugins/sweetalert2/sweetalert2.min.js"></script>
<script>
  (function () {
    const flashPayload = <?php echo json_encode($flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const liveRoot = document.getElementById('fund-monitoring-live-root');
    const summaryRegion = document.getElementById('fund-monitoring-summary-region');
    const matrixRegion = document.getElementById('fund-monitoring-matrix-region');
    const liveStatusEndpoint = 'fund-monitoring-status.php';
    let liveRefreshToken = liveRoot ? (liveRoot.getAttribute('data-refresh-token') || '') : '';
    let liveRefreshInFlight = false;
    let livePollHandle = null;
    const monthSelect = document.getElementById('entry_month');
    if (monthSelect) {
      monthSelect.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('month', this.value);
        window.location.href = url.toString();
      });
    }

    if (flashPayload && flashPayload.message) {
      const flashTypeMap = {
        success: 'success',
        danger: 'error',
        warning: 'warning',
        info: 'info'
      };
      Swal.fire({
        customClass: {
          popup: 'fm-swal-popup'
        },
        icon: flashTypeMap[flashPayload.type] || 'info',
        title: flashPayload.type === 'success' ? 'Saved Successfully' : flashPayload.type === 'danger' ? 'Something Went Wrong' : flashPayload.type === 'warning' ? 'Please Check This' : 'Update',
        html: '<div>' + String(flashPayload.message)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;') + '</div>',
        confirmButtonText: flashPayload.type === 'success' ? 'Continue' : 'Close',
        buttonsStyling: true,
        showClass: {
          popup: 'animate__animated animate__fadeInDown animate__faster'
        },
        hideClass: {
          popup: 'animate__animated animate__fadeOutUp animate__faster'
        }
      });
    }

    function refreshSummaryAndMatrix() {
      if (!liveRoot || !summaryRegion || !matrixRegion || liveRefreshInFlight) {
        return Promise.resolve();
      }

      liveRefreshInFlight = true;

      return fetch(window.location.href, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error('Unable to refresh fund monitoring view.');
          }
          return response.text();
        })
        .then((html) => {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const nextRoot = doc.getElementById('fund-monitoring-live-root');
          const nextSummary = doc.getElementById('fund-monitoring-summary-region');
          const nextMatrix = doc.getElementById('fund-monitoring-matrix-region');

          if (!nextRoot || !nextSummary || !nextMatrix) {
            throw new Error('Refreshed fund monitoring markup is incomplete.');
          }

          summaryRegion.innerHTML = nextSummary.innerHTML;
          matrixRegion.innerHTML = nextMatrix.innerHTML;
          liveRefreshToken = nextRoot.getAttribute('data-refresh-token') || liveRefreshToken;
          liveRoot.setAttribute('data-refresh-token', liveRefreshToken);
        })
        .catch(() => {
          // Keep the current view if refresh fails; polling will try again later.
        })
        .finally(() => {
          liveRefreshInFlight = false;
        });
    }

    function pollFundMonitoringChanges() {
      if (!liveRoot || document.hidden || liveRefreshInFlight) {
        return;
      }

      fetch(liveStatusEndpoint, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error('Unable to check for updates.');
          }
          return response.json();
        })
        .then((payload) => {
          const nextToken = payload && typeof payload.token === 'string' ? payload.token : '';
          if (nextToken !== '' && liveRefreshToken !== '' && nextToken !== liveRefreshToken) {
            return refreshSummaryAndMatrix();
          }
          if (nextToken !== '') {
            liveRefreshToken = nextToken;
            liveRoot.setAttribute('data-refresh-token', liveRefreshToken);
          }
          return null;
        })
        .catch(() => {
          // Ignore transient polling errors and retry on the next interval.
        });
    }

    if (liveRoot) {
      livePollHandle = window.setInterval(pollFundMonitoringChanges, 30000);
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
          pollFundMonitoringChanges();
        }
      });
    }

    const transactionMonthSelect = document.getElementById('transaction-entry-month');
    const transactionSearch = document.getElementById('transaction-search');
    const transactionFilters = Array.from(document.querySelectorAll('.fm-transaction-filter'));
    const transactionModal = document.getElementById('transactionModal');
    const transactionModalBody = document.getElementById('transaction-modal-body');
    const transactionSelectAllButton = document.getElementById('transaction-select-all');
    const transactionDeselectAllButton = document.getElementById('transaction-deselect-all');
    const transactionForm = document.getElementById('transactionForm');
    const transactionRowsEndpoint = 'fund-monitoring-transactions.php';
    const currencyFormatter = new Intl.NumberFormat('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
    let transactionRows = [];
    let transactionRowsLoadedMonth = null;
    let transactionRowsLoading = false;

    function setTransactionModalStatus(message) {
      if (!transactionModalBody) return;
      transactionModalBody.innerHTML = '<tr class="fm-transaction-status-row"><td colspan="7">' +
        String(message || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;') +
        '</td></tr>';
      transactionRows = [];
    }

    function toAmount(value) {
      const parsed = Number.parseFloat(value);
      return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0;
    }

    function refreshTransactionRowTotals(row) {
      if (!row) return;

      const baseTotalObligations = toAmount(row.getAttribute('data-base-total-obligations'));
      const baseTotalDisbursement = toAmount(row.getAttribute('data-base-total-disbursement'));
      const baseMonthObligations = toAmount(row.getAttribute('data-base-month-obligations'));
      const baseMonthDisbursement = toAmount(row.getAttribute('data-base-month-disbursement'));
      const obligationsInput = row.querySelector('.fm-transaction-obligations');
      const disbursementInput = row.querySelector('.fm-transaction-disbursement');
      const totalObligationsNode = row.querySelector('.fm-transaction-total-obligations');
      const totalDisbursementNode = row.querySelector('.fm-transaction-total-disbursement');
      const liveObligations = toAmount(obligationsInput ? obligationsInput.value : 0);
      const liveDisbursement = toAmount(disbursementInput ? disbursementInput.value : 0);
      const annualObligations = Math.max(baseTotalObligations - baseMonthObligations + liveObligations, 0);
      const annualDisbursement = Math.max(baseTotalDisbursement - baseMonthDisbursement + liveDisbursement, 0);

      if (totalObligationsNode) {
        totalObligationsNode.textContent = currencyFormatter.format(annualObligations);
      }
      if (totalDisbursementNode) {
        totalDisbursementNode.textContent = currencyFormatter.format(annualDisbursement);
      }
    }

    function refreshAllTransactionRowTotals() {
      transactionRows.forEach(refreshTransactionRowTotals);
    }

    function bindTransactionRows() {
      transactionRows = transactionModalBody ? Array.from(transactionModalBody.querySelectorAll('.fm-transaction-row')) : [];

      transactionRows.forEach((row) => {
        const obligationsInput = row.querySelector('.fm-transaction-obligations');
        const disbursementInput = row.querySelector('.fm-transaction-disbursement');
        if (obligationsInput) {
          obligationsInput.addEventListener('input', function () {
            refreshTransactionRowTotals(row);
          });
          obligationsInput.addEventListener('change', function () {
            refreshTransactionRowTotals(row);
          });
        }
        if (disbursementInput) {
          disbursementInput.addEventListener('input', function () {
            refreshTransactionRowTotals(row);
          });
          disbursementInput.addEventListener('change', function () {
            refreshTransactionRowTotals(row);
          });
        }
      });

      refreshAllTransactionRowTotals();
      applyTransactionFilters();
    }

    function setTransactionFormEnabled(enabled) {
      if (!transactionForm) return;
      Array.from(transactionForm.querySelectorAll('button, input, select, textarea')).forEach((element) => {
        if (element.name === 'csrf_token' || element.name === 'action') {
          return;
        }
        if (element.id === 'transaction-entry-month') {
          element.disabled = false;
          return;
        }
        if (element.classList.contains('close') || element.getAttribute('data-dismiss') === 'modal') {
          element.disabled = false;
          return;
        }
        element.disabled = !enabled;
      });
    }

    function loadTransactionRows(forceReload) {
      if (!transactionMonthSelect || !transactionModalBody || transactionRowsLoading) {
        return;
      }

      const month = String(transactionMonthSelect.value || '');
      if (!forceReload && transactionRowsLoadedMonth === month && transactionRows.length > 0) {
        applyTransactionFilters();
        return;
      }

      transactionRowsLoading = true;
      setTransactionFormEnabled(false);
      setTransactionModalStatus('Loading transactions...');

      fetch(transactionRowsEndpoint + '?month=' + encodeURIComponent(month), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error('Unable to load transaction rows.');
          }
          return response.text();
        })
        .then((html) => {
          transactionModalBody.innerHTML = html.trim() !== '' ? html : '<tr class="fm-transaction-status-row"><td colspan="7">No transactions found for this fiscal year.</td></tr>';
          transactionRowsLoadedMonth = month;
          bindTransactionRows();
          setTransactionFormEnabled(true);
        })
        .catch(() => {
          transactionRowsLoadedMonth = null;
          setTransactionModalStatus('Unable to load transactions right now. Please try again.');
          setTransactionFormEnabled(false);
        })
        .finally(() => {
          transactionRowsLoading = false;
        });
    }

    function applyTransactionFilters() {
      const selectedCodes = new Set(transactionFilters.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value.toLowerCase()));
      const searchText = (transactionSearch ? transactionSearch.value : '').trim().toLowerCase();
      transactionRows.forEach((row) => {
        const rowCode = (row.getAttribute('data-object-code') || '').toLowerCase();
        const rowSearch = (row.getAttribute('data-search') || '').toLowerCase();
        const matchesCode = selectedCodes.size === 0 ? false : selectedCodes.has(rowCode);
        const matchesSearch = searchText === '' || rowSearch.indexOf(searchText) !== -1;
        row.style.display = (matchesCode && matchesSearch) ? '' : 'none';
      });
    }

    if (transactionSearch) transactionSearch.addEventListener('input', applyTransactionFilters);
    transactionFilters.forEach((checkbox) => checkbox.addEventListener('change', applyTransactionFilters));
    applyTransactionFilters();
    setTransactionFormEnabled(false);

    if (transactionSelectAllButton) {
      transactionSelectAllButton.addEventListener('click', function () {
        transactionFilters.forEach((checkbox) => {
          checkbox.checked = true;
        });
        applyTransactionFilters();
      });
    }

    if (transactionDeselectAllButton) {
      transactionDeselectAllButton.addEventListener('click', function () {
        transactionFilters.forEach((checkbox) => {
          checkbox.checked = false;
        });
        applyTransactionFilters();
      });
    }

    if (transactionMonthSelect) {
      transactionMonthSelect.addEventListener('change', function () {
        loadTransactionRows(true);
      });
    }

    if (transactionModal) {
      $('#transactionModal').on('show.bs.modal', function () {
        loadTransactionRows(false);
      });
    }

    function hideModalById(modalId) {
      const modalElement = document.getElementById(modalId);
      if (!modalElement) return;

      if (
        window.bootstrap &&
        window.bootstrap.Modal &&
        typeof window.bootstrap.Modal.getInstance === 'function'
      ) {
        const modalInstance =
          window.bootstrap.Modal.getInstance(modalElement) ||
          (typeof window.bootstrap.Modal.getOrCreateInstance === 'function'
            ? window.bootstrap.Modal.getOrCreateInstance(modalElement)
            : new window.bootstrap.Modal(modalElement));
        modalInstance.hide();
        return;
      }

      if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
        window.jQuery(modalElement).modal('hide');
      }
    }

    $(document).on('click', '#fundItemModal [data-dismiss="modal"], #fundItemModal [data-bs-dismiss="modal"]', function () {
      hideModalById('fundItemModal');
    });

    $(document).on('click', '#transactionModal [data-dismiss="modal"], #transactionModal [data-bs-dismiss="modal"]', function () {
      hideModalById('transactionModal');
    });

    const objectCodeSelect = document.getElementById('fund-object-code');
    const customCodeWrap = document.getElementById('fund-custom-code-wrap');
    const customCodeInput = document.getElementById('fund-custom-code');
    const hiddenPapInput = document.getElementById('fund-pap-name');
    function toggleCustomCode() {
      if (!objectCodeSelect || !customCodeWrap || !customCodeInput) return;
      const useCustom = objectCodeSelect.value === '__custom__';
      customCodeWrap.style.display = useCustom ? '' : 'none';
      customCodeInput.required = useCustom;
      if (!useCustom) customCodeInput.value = '';
      if (hiddenPapInput) {
        hiddenPapInput.value = useCustom ? customCodeInput.value : objectCodeSelect.value;
      }
    }
    if (objectCodeSelect) {
      objectCodeSelect.addEventListener('change', toggleCustomCode);
      toggleCustomCode();
    }
    if (customCodeInput) {
      customCodeInput.addEventListener('input', toggleCustomCode);
    }

    $(document).on('click', '.fm-edit-item', function () {
      const itemPayload = JSON.parse($(this).attr('data-item') || '{}');
      $('#fundItemModalLabel').text('Edit Fund Monitoring Item');
      $('#fund-record-id').val(itemPayload.id || '');
      $('#fund-saro-number').val(itemPayload.saro_number || '');
      $('#fund-pap-name').val(itemPayload.object_code_name || '');
      $('#fund-authorized').val(Number(itemPayload.authorized_appropriation || 0).toFixed(2));
      $('#fund-realignment').val(Number(itemPayload.realignment || 0).toFixed(2));
      $('#fund-display-order').val(itemPayload.display_order || 0);
      $('#fund-reason-obligation').val(itemPayload.reason_obligation || '');
      $('#fund-reason-disbursement').val(itemPayload.reason_disbursement || '');

      if (objectCodeSelect) {
        const desiredValue = itemPayload.object_code_name || '';
        const hasExactOption = Array.from(objectCodeSelect.options).some((option) => option.value === desiredValue);
        objectCodeSelect.value = hasExactOption ? desiredValue : (desiredValue ? '__custom__' : '');
        if (customCodeInput) customCodeInput.value = hasExactOption ? '' : desiredValue;
        toggleCustomCode();
      }

      $('#fundItemModal').modal('show');
    });

    $('#fundItemModal').on('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      if (!trigger || trigger.getAttribute('data-mode') !== 'create') return;
      $('#fundItemModalLabel').text('Add Fund Monitoring Item');
      $('#fund-record-id').val('');
      $('#fund-saro-number').val('');
      $('#fund-pap-name').val('');
      $('#fund-authorized').val('');
      $('#fund-realignment').val('0.00');
      $('#fund-display-order').val('0');
      $('#fund-reason-obligation').val('');
      $('#fund-reason-disbursement').val('');
      if (objectCodeSelect) objectCodeSelect.value = '';
      if (customCodeInput) customCodeInput.value = '';
      toggleCustomCode();
    });
  })();
</script>
</body>
</html>
