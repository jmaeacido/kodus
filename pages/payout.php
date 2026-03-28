<?php
include('../header.php');
include('../sidenav.php');
require_once __DIR__ . '/../project_variable_helpers.php';

if (!isset($_SESSION['selected_year'])) {
    echo "<p style='color:red;'>Fiscal year not selected. Please go back and select a year.</p>";
    exit;
}

$year = (int) $_SESSION['selected_year'];
$userType = $_SESSION['user_type'] ?? 'user';
$dailyWageRate = project_variable_get_number($conn, 'daily_wage_rate', $year, 0);
$payoutDays = (int) round(project_variable_get_number($conn, 'working_days', $year, 20));
$payoutDays = $payoutDays > 0 ? $payoutDays : 20;
$beneficiaryPayoutRate = $dailyWageRate * $payoutDays;

$stmt = $conn->prepare("
    SELECT id, province, lgu, barangay, benesNumber, amount, paid, payoutDate
    FROM breakdown
    WHERE YEAR(payoutDate) = ?
    ORDER BY province, lgu, barangay
");
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$groups = [];
$totalBenes = 0;
$totalAmount = 0;
$totalPaid = 0;
$totalUnpaid = 0;

while ($row = $result->fetch_assoc()) {
    $row['id'] = (int) $row['id'];
    $row['province'] = trim((string) $row['province']);
    $row['lgu'] = trim((string) $row['lgu']);
    $row['barangay'] = trim((string) $row['barangay']);
    $row['benesNumber'] = (int) $row['benesNumber'];
    $row['paid'] = (int) $row['paid'];
    $row['amount'] = (float) $row['amount'];
    $row['unpaid'] = max($row['benesNumber'] - $row['paid'], 0);
    $row['payoutDateIso'] = !empty($row['payoutDate']) ? date('Y-m-d', strtotime((string) $row['payoutDate'])) : '';
    $row['payoutDateDisplay'] = !empty($row['payoutDate']) ? date('F d, Y', strtotime((string) $row['payoutDate'])) : '';

    $groupKey = strtolower($row['province'] . '|' . $row['lgu']);
    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = [
            'province' => $row['province'],
            'lgu' => $row['lgu'],
            'barangayCount' => 0,
            'benesNumber' => 0,
            'amount' => 0.0,
            'paid' => 0,
            'unpaid' => 0,
            'records' => [],
            'dateKeys' => [],
            'latestPayoutDateIso' => '',
        ];
    }

    $groups[$groupKey]['barangayCount']++;
    $groups[$groupKey]['benesNumber'] += $row['benesNumber'];
    $groups[$groupKey]['amount'] += $row['amount'];
    $groups[$groupKey]['paid'] += $row['paid'];
    $groups[$groupKey]['unpaid'] += $row['unpaid'];
    $groups[$groupKey]['records'][] = [
        'id' => $row['id'],
        'province' => $row['province'],
        'lgu' => $row['lgu'],
        'barangay' => $row['barangay'],
        'benesNumber' => $row['benesNumber'],
        'amount' => $row['amount'],
        'paid' => $row['paid'],
        'unpaid' => $row['unpaid'],
        'payoutDateIso' => $row['payoutDateIso'],
        'payoutDateDisplay' => $row['payoutDateDisplay'],
    ];

    if ($row['payoutDateIso'] !== '') {
        $groups[$groupKey]['dateKeys'][$row['payoutDateIso']] = true;
        if ($groups[$groupKey]['latestPayoutDateIso'] === '' || strcmp($row['payoutDateIso'], $groups[$groupKey]['latestPayoutDateIso']) > 0) {
            $groups[$groupKey]['latestPayoutDateIso'] = $row['payoutDateIso'];
        }
    }

    $totalBenes += $row['benesNumber'];
    $totalAmount += $row['amount'];
    $totalPaid += $row['paid'];
    $totalUnpaid += $row['unpaid'];
}

$stmt->close();

$groupRows = array_values($groups);
usort($groupRows, static function (array $left, array $right): int {
    return [$left['province'], $left['lgu']] <=> [$right['province'], $right['lgu']];
});

foreach ($groupRows as &$group) {
    $dateCount = count($group['dateKeys']);
    if ($dateCount === 0) {
        $group['payoutDateDisplay'] = 'No payout date set';
    } elseif ($dateCount === 1) {
        $group['payoutDateDisplay'] = date('F d, Y', strtotime((string) $group['latestPayoutDateIso']));
    } else {
        $group['payoutDateDisplay'] = 'Multiple payout dates';
    }
    unset($group['dateKeys']);
}
unset($group);

$totalAmountPaid = $totalPaid * $beneficiaryPayoutRate;
$totalAmountUnpaid = $totalUnpaid * $beneficiaryPayoutRate;
?>

<script>
const userType = <?php echo json_encode($userType); ?>;
const selectedYear = <?php echo json_encode($year); ?>;
const dailyWageRate = <?php echo json_encode($dailyWageRate); ?>;
const payoutDays = <?php echo json_encode($payoutDays); ?>;
const beneficiaryPayoutRate = <?php echo json_encode($beneficiaryPayoutRate); ?>;

function formatCurrency(value) {
    return `&#8369;${Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString();
}

function getDateInputValue(value) {
    return value ? String(value).split('T')[0] : '';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payout</title>
<style>
  .kodus-detail-modal,
  .payout-edit-shell { text-align: left; color: var(--kodus-detail-text); }
  .kodus-detail-hero,
  .payout-edit-hero {
    display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; padding: 1.25rem;
    border: 1px solid var(--kodus-detail-border); border-radius: 18px;
    background: linear-gradient(135deg, var(--kodus-detail-hero-start) 0%, var(--kodus-detail-hero-end) 100%);
  }
  .kodus-detail-eyebrow,
  .payout-edit-eyebrow,
  .payout-edit-section h6,
  .payout-edit-meta-label,
  .payout-linked-header span { margin: 0 0 0.35rem; color: var(--kodus-detail-muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
  .kodus-detail-title,
  .payout-edit-title { margin: 0; color: var(--kodus-detail-text); font-size: 1.35rem; font-weight: 700; }
  .kodus-detail-subtitle,
  .payout-edit-subtitle,
  .payout-edit-note,
  .payout-edit-section-note { margin: 0.35rem 0 0; color: var(--kodus-detail-muted); line-height: 1.55; }
  .kodus-detail-pill,
  .payout-edit-badge { display: inline-flex; align-items: center; justify-content: center; padding: 0.55rem 0.85rem; border-radius: 999px; background: var(--kodus-detail-badge-bg); color: var(--kodus-detail-badge-text); font-size: 0.85rem; font-weight: 700; white-space: nowrap; }
  .kodus-detail-grid,
  .payout-edit-meta,
  .payout-edit-summary-grid,
  .payout-edit-grid { display: grid; gap: 0.85rem; }
  .kodus-detail-grid,
  .payout-edit-summary-grid,
  .payout-edit-meta { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
  .payout-edit-grid { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
  .kodus-detail-stat,
  .payout-edit-meta-card,
  .payout-edit-summary-card,
  .payout-edit-section,
  .payout-linked-row { border: 1px solid var(--kodus-detail-border); border-radius: 16px; background: var(--kodus-detail-panel-strong); box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12); }
  .kodus-detail-stat,
  .payout-edit-meta-card,
  .payout-edit-summary-card,
  .payout-edit-section,
  .payout-linked-row { padding: 1rem; }
  .kodus-detail-label,
  .payout-edit-meta-label,
  .payout-edit-summary-label { display: block; margin-bottom: 0.4rem; color: var(--kodus-detail-muted); font-size: 0.82rem; font-weight: 600; }
  .kodus-detail-value,
  .payout-edit-meta-value,
  .payout-edit-summary-value { display: block; color: var(--kodus-detail-text); font-size: 1.08rem; line-height: 1.3; font-weight: 700; }
  .kodus-detail-value--positive,
  .payout-metric-positive { color: var(--kodus-detail-positive-text); }
  .kodus-detail-value--warning,
  .payout-metric-warning { color: var(--kodus-detail-warning-text); }
  .kodus-detail-section { margin-top: 1rem; padding: 1rem; border: 1px solid var(--kodus-detail-border); border-radius: 16px; background: var(--kodus-detail-panel-strong); }
  .kodus-detail-section-title { margin: 0 0 0.8rem; color: var(--kodus-detail-text); font-size: 0.95rem; font-weight: 700; }
  .kodus-detail-table,
  .payout-detail-records { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; border: 1px solid var(--kodus-detail-border); border-radius: 16px; }
  .kodus-detail-table th,
  .kodus-detail-table td,
  .payout-detail-records th,
  .payout-detail-records td { padding: 0.8rem 0.95rem; text-align: left; border-bottom: 1px solid var(--kodus-detail-border); color: var(--kodus-detail-text); }
  .kodus-detail-table th,
  .payout-detail-records th { background: var(--kodus-detail-panel); color: var(--kodus-detail-muted); font-weight: 700; }
  .kodus-detail-table tr:last-child th,
  .kodus-detail-table tr:last-child td,
  .payout-detail-records tr:last-child td { border-bottom: 0; }
  .payout-edit-section h6 { margin-bottom: 0.8rem; color: var(--kodus-detail-text); }
  .payout-edit-field label { display: block; margin-bottom: 0.45rem; color: var(--kodus-detail-text); font-size: 0.84rem; font-weight: 700; }
  .payout-edit-field input { min-height: 44px; border-radius: 12px; }
  .payout-linked-header,
  .payout-linked-row-header { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
  .payout-linked-header { margin-bottom: 1rem; }
  .payout-linked-header h6,
  .payout-row-title { margin: 0; color: var(--kodus-detail-text); font-weight: 700; }
  .payout-linked-list { display: grid; gap: 0.9rem; }
  .payout-linked-row-header { margin-bottom: 0.9rem; }
  .payout-row-toggle { width: 2.1rem; min-width: 2.1rem; height: 2.1rem; padding: 0; border-radius: 999px; }
  .payout-linked-row.is-collapsed .payout-linked-row-body { display: none; }
  .payout-linked-row.is-collapsed .payout-row-toggle i { transform: rotate(-90deg); }
  .payout-row-toggle i { transition: transform 0.2s ease; }
  .payout-row-meta { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; margin-top: 0.25rem; color: var(--kodus-detail-muted); font-size: 0.82rem; }
  .swal2-popup.kodus-edit-popup,
  .swal2-popup.kodus-detail-popup { border-radius: 24px; padding: 1.25rem; }
  @media (max-width: 768px) {
    .kodus-detail-hero, .payout-edit-hero, .payout-linked-header, .payout-linked-row-header { flex-direction: column; align-items: flex-start; }
    .payout-detail-records th, .payout-detail-records td, .kodus-detail-table th, .kodus-detail-table td { display: block; width: 100%; }
    .payout-detail-records th, .kodus-detail-table th { border-bottom: 0; padding-bottom: 0.2rem; }
    .payout-detail-records td, .kodus-detail-table td { padding-top: 0; }
  }
</style>
</head>
<body>
<div class="wrapper"><div class="content-wrapper"><div class="content"><div class="container-fluid"><div class="row"><div class="col-lg-12"><div class="card card-primary card-outline"><div class="card-header d-flex align-items-center"><h5 class="m-0 flex-grow-1">Payout Details by Municipality</h5><button id="exportBtn" class="btn btn-info btn-sm">Export to Excel</button></div><div class="card-body">
<?php if ($dailyWageRate <= 0): ?>
<div class="alert alert-warning">No project variable is configured yet for <strong>daily_wage_rate</strong> in fiscal year <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>. Ask an administrator to update <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/admin/project_variables">Project Variables</a>.</div>
<?php endif; ?>
<?php if ($payoutDays <= 0): ?>
<div class="alert alert-warning">No project variable is configured yet for <strong>working_days</strong> in fiscal year <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>. Ask an administrator to update <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/admin/project_variables">Project Variables</a>.</div>
<?php endif; ?>
<div class="table-container"><table id="sectoralTable" class="table table-bordered table-striped" style="text-align:center; width:100%;"><thead><tr><th>Action</th><th>Province</th><th>City or Municipality</th><th>Barangays</th><th>No. of Partner-Beneficiaries</th><th>Amount</th><th>Payout Date</th><th>Paid</th><th>Amount Paid</th><th>Unpaid</th><th>Amount Unpaid</th></tr><tr style="font-weight:bold;"><td colspan="4">Total</td><td><?= number_format($totalBenes) ?></td><td><?= number_format($totalAmount, 2) ?></td><td></td><td><?= number_format($totalPaid) ?></td><td><?= number_format($totalAmountPaid, 2) ?></td><td><?= number_format($totalUnpaid) ?></td><td><?= number_format($totalAmountUnpaid, 2) ?></td></tr></thead><tbody style="font-size:12px;">
<?php foreach ($groupRows as $group): ?>
<?php $groupJson = htmlspecialchars(json_encode($group, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>
<tr><td><span class="kodus-row-actions"><button class="btn btn-info btn-sm details-btn" data-group="<?= $groupJson; ?>"><i class="nav-icon fas fa-eye" aria-hidden="true"></i></button></span></td><td><?= htmlspecialchars($group['province']); ?></td><td><?= htmlspecialchars($group['lgu']); ?></td><td><?= number_format($group['barangayCount']); ?></td><td><?= number_format($group['benesNumber']); ?></td><td><?= number_format($group['amount'], 2); ?></td><td><?= htmlspecialchars($group['payoutDateDisplay']); ?></td><td><?= number_format($group['paid']); ?></td><td><?= number_format($group['paid'] * $beneficiaryPayoutRate, 2); ?></td><td><?= number_format($group['unpaid']); ?></td><td><?= number_format($group['unpaid'] * $beneficiaryPayoutRate, 2); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div></div></div></div></div></div></div>
<script src="<?php echo $base_url;?>kodus/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo $base_url;?>kodus/dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function () {
    $('#sectoralTable').DataTable({ responsive: true, autoWidth: false, ordering: false, lengthMenu: [[10, 25, 50, 100, 200, -1], [10, 25, 50, 100, 200, 'All']] });
});
document.getElementById('exportBtn').addEventListener('click', function () { window.location.href = '../pages/payout_export'; });
function cloneGroupData(group) { return JSON.parse(JSON.stringify(group || {})); }
function normalizeWholeNumber(value) { const parsed = parseInt(value, 10); return Number.isFinite(parsed) && parsed > 0 ? parsed : 0; }
function getGroupDateBadge(group) {
    const dates = Array.isArray(group.records) ? group.records.map((record) => record.payoutDateIso).filter(Boolean) : [];
    const uniqueDates = [...new Set(dates)];
    if (uniqueDates.length === 0) return 'No payout date set';
    if (uniqueDates.length === 1) return group.records.find((record) => record.payoutDateIso === uniqueDates[0])?.payoutDateDisplay || uniqueDates[0];
    return 'Multiple payout dates';
}
function calculateGroupMetrics(records) {
    const normalizedRecords = Array.isArray(records) ? records : [];
    return normalizedRecords.reduce((summary, record) => {
        const benes = normalizeWholeNumber(record.benesNumber);
        const paid = Math.min(normalizeWholeNumber(record.paid), benes);
        const unpaid = Math.max(benes - paid, 0);
        summary.barangayCount += 1; summary.benesNumber += benes; summary.paid += paid; summary.unpaid += unpaid; summary.amount += benes * beneficiaryPayoutRate;
        return summary;
    }, { barangayCount: 0, benesNumber: 0, paid: 0, unpaid: 0, amount: 0 });
}
function renderMunicipalityHero(group, badgeText, modeLabel) {
    const metrics = calculateGroupMetrics(group.records);
    return `
        <div class="payout-edit-hero">
            <div>
                <span class="payout-edit-eyebrow"><i class="fas fa-layer-group"></i>Payout Municipality Session</span>
                <h3 class="payout-edit-title">${escapeHtml(group.lgu || 'Unspecified municipality')}</h3>
                <p class="payout-edit-subtitle">${escapeHtml(group.province || 'Unspecified province')}<br>Review the municipality summary and the linked barangay payout rows in one place.</p>
            </div>
            <div class="payout-edit-badge">${escapeHtml(badgeText)}</div>
        </div>
        <div class="payout-edit-meta">
            <div class="payout-edit-meta-card"><span class="payout-edit-meta-label">Mode</span><span class="payout-edit-meta-value">${escapeHtml(modeLabel)}</span></div>
            <div class="payout-edit-meta-card"><span class="payout-edit-meta-label">Barangays</span><span class="payout-edit-meta-value">${formatNumber(metrics.barangayCount)}</span></div>
            <div class="payout-edit-meta-card"><span class="payout-edit-meta-label">Beneficiaries</span><span class="payout-edit-meta-value">${formatNumber(metrics.benesNumber)}</span></div>
            <div class="payout-edit-meta-card"><span class="payout-edit-meta-label">Total Amount</span><span class="payout-edit-meta-value">${formatCurrency(metrics.amount)}</span></div>
        </div>
    `;
}
function renderMunicipalityDetails(group) {
    const metrics = calculateGroupMetrics(group.records);
    const paidAmount = metrics.paid * beneficiaryPayoutRate;
    const unpaidAmount = metrics.unpaid * beneficiaryPayoutRate;
    const recordsHtml = (group.records || []).map((record) => `
        <tr>
            <td>${escapeHtml(record.barangay || '')}</td>
            <td>${formatNumber(record.benesNumber)}</td>
            <td>${formatNumber(record.paid)}</td>
            <td>${formatNumber(record.unpaid)}</td>
            <td>${formatCurrency(record.amount)}</td>
            <td>${escapeHtml(record.payoutDateDisplay || 'No payout date set')}</td>
        </tr>
    `).join('');
    return `
        <div class="kodus-detail-modal">
            ${renderMunicipalityHero(group, getGroupDateBadge(group), 'View Details')}
            <div class="kodus-detail-grid">
                <div class="kodus-detail-stat"><span class="kodus-detail-label">Barangays</span><span class="kodus-detail-value">${formatNumber(metrics.barangayCount)}</span></div>
                <div class="kodus-detail-stat"><span class="kodus-detail-label">Beneficiaries</span><span class="kodus-detail-value">${formatNumber(metrics.benesNumber)}</span></div>
                <div class="kodus-detail-stat"><span class="kodus-detail-label">Paid</span><span class="kodus-detail-value kodus-detail-value--positive">${formatNumber(metrics.paid)}</span></div>
                <div class="kodus-detail-stat"><span class="kodus-detail-label">Unpaid</span><span class="kodus-detail-value kodus-detail-value--warning">${formatNumber(metrics.unpaid)}</span></div>
            </div>
            <table class="kodus-detail-table">
                <tr><th>Province</th><td>${escapeHtml(group.province || '')}</td></tr>
                <tr><th>City / Municipality</th><td>${escapeHtml(group.lgu || '')}</td></tr>
                <tr><th>Computed Total Amount</th><td>${formatCurrency(metrics.amount)}</td></tr>
                <tr><th>Amount Paid</th><td>${formatCurrency(paidAmount)}</td></tr>
                <tr><th>Amount Unpaid</th><td>${formatCurrency(unpaidAmount)}</td></tr>
                <tr><th>Computation Rule</th><td>${formatCurrency(dailyWageRate)} x ${formatNumber(payoutDays)} days = ${formatCurrency(beneficiaryPayoutRate)} per beneficiary</td></tr>
            </table>
            <div class="kodus-detail-section">
                <h6 class="kodus-detail-section-title">Linked Barangay Records</h6>
                <table class="payout-detail-records">
                    <thead><tr><th>Barangay</th><th>Beneficiaries</th><th>Paid</th><th>Unpaid</th><th>Amount</th><th>Payout Date</th></tr></thead>
                    <tbody>${recordsHtml}</tbody>
                </table>
            </div>
        </div>
    `;
}
function renderLinkedRow(record, index) {
    const benes = normalizeWholeNumber(record.benesNumber);
    const paid = Math.min(normalizeWholeNumber(record.paid), benes);
    const unpaid = Math.max(benes - paid, 0);
    const amount = benes * beneficiaryPayoutRate;
    return `
        <div class="payout-linked-row${index === 0 ? '' : ' is-collapsed'}" data-record-id="${escapeHtml(record.id)}">
            <div class="payout-linked-row-header">
                <div>
                    <h6 class="payout-row-title">${escapeHtml(record.barangay || 'Unspecified barangay')}</h6>
                    <div class="payout-row-meta"><span>${formatNumber(benes)} beneficiaries</span><span>${formatCurrency(amount)}</span><span>${escapeHtml(record.payoutDateDisplay || 'No payout date set')}</span></div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm payout-row-toggle"><i class="fas fa-chevron-down"></i></button>
            </div>
            <div class="payout-linked-row-body">
                <div class="payout-edit-grid">
                    <div class="payout-edit-field"><label>Barangay</label><input type="text" class="form-control payout-row-barangay" value="${escapeHtml(record.barangay || '')}"></div>
                    <div class="payout-edit-field"><label>No. of Partner-Beneficiaries</label><input type="number" min="0" class="form-control payout-row-benes" value="${escapeHtml(String(benes))}" readonly></div>
                    <div class="payout-edit-field"><label>Paid</label><input type="number" min="0" class="form-control payout-row-paid" value="${escapeHtml(String(paid))}"></div>
                    <div class="payout-edit-field"><label>Payout Date</label><input type="date" class="form-control payout-row-date" value="${escapeHtml(getDateInputValue(record.payoutDateIso || ''))}"></div>
                </div>
                <div class="payout-edit-summary-grid mt-3">
                    <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Total Amount</span><span class="payout-edit-summary-value payout-row-amount">${formatCurrency(amount)}</span></div>
                    <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Unpaid Beneficiaries</span><span class="payout-edit-summary-value payout-row-unpaid">${formatNumber(unpaid)}</span></div>
                    <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Amount Paid</span><span class="payout-edit-summary-value payout-metric-positive payout-row-paid-amount">${formatCurrency(paid * beneficiaryPayoutRate)}</span></div>
                    <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Amount Unpaid</span><span class="payout-edit-summary-value payout-metric-warning payout-row-unpaid-amount">${formatCurrency(unpaid * beneficiaryPayoutRate)}</span></div>
                </div>
            </div>
        </div>
    `;
}
function syncLinkedRow($row) {
    const $benes = $row.find('.payout-row-benes');
    const $paid = $row.find('.payout-row-paid');
    const benesValue = normalizeWholeNumber($benes.val());
    let paidValue = normalizeWholeNumber($paid.val());
    if (paidValue > benesValue) {
        paidValue = benesValue;
        $paid.val(String(paidValue));
    }
    const unpaidValue = Math.max(benesValue - paidValue, 0);
    const totalAmount = benesValue * beneficiaryPayoutRate;
    const paidAmount = paidValue * beneficiaryPayoutRate;
    const unpaidAmount = unpaidValue * beneficiaryPayoutRate;
    const payoutDate = $row.find('.payout-row-date').val();
    $row.find('.payout-row-amount').html(formatCurrency(totalAmount));
    $row.find('.payout-row-unpaid').text(formatNumber(unpaidValue));
    $row.find('.payout-row-paid-amount').html(formatCurrency(paidAmount));
    $row.find('.payout-row-unpaid-amount').html(formatCurrency(unpaidAmount));
    $row.find('.payout-row-meta').html(`<span>${formatNumber(benesValue)} beneficiaries</span><span>${formatCurrency(totalAmount)}</span><span>${escapeHtml(payoutDate || 'No payout date set')}</span>`);
}
function syncGroupSummary() {
    const records = [];
    $('#payout-linked-list .payout-linked-row').each(function () {
        const $row = $(this);
        const benesValue = normalizeWholeNumber($row.find('.payout-row-benes').val());
        const paidValue = Math.min(normalizeWholeNumber($row.find('.payout-row-paid').val()), benesValue);
        records.push({ benesNumber: benesValue, paid: paidValue });
    });
    const metrics = calculateGroupMetrics(records);
    $('#group-summary-barangays').text(formatNumber(metrics.barangayCount));
    $('#group-summary-benes').text(formatNumber(metrics.benesNumber));
    $('#group-summary-amount').html(formatCurrency(metrics.amount));
    $('#group-summary-paid').html(formatCurrency(metrics.paid * beneficiaryPayoutRate));
    $('#group-summary-unpaid').html(formatCurrency(metrics.unpaid * beneficiaryPayoutRate));
    if (metrics.benesNumber === 0) {
        $('#group-summary-note').text(`Enter barangay beneficiary values to calculate the ${selectedYear} municipality totals.`);
    } else {
        $('#group-summary-note').text(`${formatNumber(metrics.benesNumber)} total beneficiaries x PHP ${dailyWageRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} x ${formatNumber(payoutDays)} days = PHP ${metrics.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`);
    }
}
function showEditForm(group) {
    const recordsHtml = (group.records || []).map((record, index) => renderLinkedRow(record, index)).join('');
    Swal.fire({
        title: 'Edit Payout Details',
        customClass: { popup: 'kodus-edit-popup' },
        width: 1040,
        showCancelButton: true,
        focusConfirm: false,
        confirmButtonText: '<i class="fas fa-save"></i>',
        cancelButtonText: '<i class="fas fa-times"></i>',
        html: `
            <form id="payoutGroupEditForm" class="payout-edit-shell">
                ${renderMunicipalityHero(group, getGroupDateBadge(group), 'Edit Session')}
                <div class="payout-edit-section">
                    <h6>Municipality Details</h6>
                    <p class="payout-edit-section-note">Province and municipality values apply to all linked barangay payout rows in this municipality.</p>
                    <div class="payout-edit-grid">
                        <div class="payout-edit-field"><label>Province</label><input id="edit-group-province" type="text" class="form-control" value="${escapeHtml(group.province || '')}"></div>
                        <div class="payout-edit-field"><label>City / Municipality</label><input id="edit-group-lgu" type="text" class="form-control" value="${escapeHtml(group.lgu || '')}"></div>
                    </div>
                </div>
                <div class="payout-edit-section">
                    <h6>Municipality Summary</h6>
                    <p id="group-summary-note" class="payout-edit-section-note">Amounts update automatically from the linked barangay rows.</p>
                    <div class="payout-edit-summary-grid">
                        <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Barangays</span><span id="group-summary-barangays" class="payout-edit-summary-value">0</span></div>
                        <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Beneficiaries</span><span id="group-summary-benes" class="payout-edit-summary-value">0</span></div>
                        <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Total Amount</span><span id="group-summary-amount" class="payout-edit-summary-value">0</span></div>
                        <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Amount Paid</span><span id="group-summary-paid" class="payout-edit-summary-value payout-metric-positive">0</span></div>
                        <div class="payout-edit-summary-card"><span class="payout-edit-summary-label">Amount Unpaid</span><span id="group-summary-unpaid" class="payout-edit-summary-value payout-metric-warning">0</span></div>
                    </div>
                </div>
                <div class="payout-edit-section">
                    <div class="payout-linked-header"><div><span>Linked Barangay Rows</span><h6>Update each barangay record under ${escapeHtml(group.lgu || 'this municipality')}</h6></div></div>
                    <div id="payout-linked-list" class="payout-linked-list">${recordsHtml}</div>
                </div>
            </form>
        `,
        didOpen: () => {
            $('#payout-linked-list .payout-linked-row').each(function () { syncLinkedRow($(this)); });
            syncGroupSummary();
        },
        preConfirm: () => {
            const provinceValue = $('#edit-group-province').val().trim();
            const lguValue = $('#edit-group-lgu').val().trim();
            const records = [];
            if (!provinceValue || !lguValue) {
                Swal.showValidationMessage('Province and city / municipality are required.');
                return false;
            }
            let hasValidationError = false;
            $('#payout-linked-list .payout-linked-row').each(function () {
                const $row = $(this);
                const barangayValue = $row.find('.payout-row-barangay').val().trim();
                const benesValue = Number.parseInt($row.find('.payout-row-benes').val(), 10);
                const paidValue = Number.parseInt($row.find('.payout-row-paid').val(), 10);
                const payoutDateValue = $row.find('.payout-row-date').val();
                if (!barangayValue) {
                    Swal.showValidationMessage('Each linked row must have a barangay.');
                    hasValidationError = true;
                    return false;
                }
                if (!Number.isFinite(benesValue) || benesValue < 0) {
                    Swal.showValidationMessage(`Barangay ${barangayValue}: beneficiaries must be 0 or greater.`);
                    hasValidationError = true;
                    return false;
                }
                if (!Number.isFinite(paidValue) || paidValue < 0) {
                    Swal.showValidationMessage(`Barangay ${barangayValue}: paid must be 0 or greater.`);
                    hasValidationError = true;
                    return false;
                }
                if (paidValue > benesValue) {
                    Swal.showValidationMessage(`Barangay ${barangayValue}: paid cannot be greater than beneficiaries.`);
                    hasValidationError = true;
                    return false;
                }
                records.push({ id: Number($row.data('record-id')) || 0, barangay: barangayValue, benesNumber: benesValue, paid: paidValue, payoutDate: payoutDateValue });
            });
            if (hasValidationError) return false;
            return $.ajax({
                url: 'update_payout_group.php',
                type: 'POST',
                dataType: 'json',
                data: { province: provinceValue, lgu: lguValue, records: JSON.stringify(records) }
            }).then((response) => {
                if (!response || !response.success) throw new Error(response?.message || 'Failed to update payout records.');
                return response;
            }).catch((error) => {
                Swal.showValidationMessage(error.message || 'Failed to update payout records.');
                return false;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Success!', 'Municipality payout records updated successfully.', 'success').then(() => { location.reload(); });
        }
    });
}
$(document).on('click', '.details-btn', function () {
    const group = cloneGroupData($(this).data('group'));
    Swal.fire({
        title: 'Payout Municipality Details',
        customClass: { popup: 'kodus-detail-popup' },
        icon: 'info',
        showCancelButton: true,
        showConfirmButton: userType === 'admin' || userType === 'aa',
        confirmButtonText: '<i class="fas fa-pen"></i>',
        cancelButtonText: '<i class="fas fa-times"></i>',
        html: renderMunicipalityDetails(group)
    }).then((result) => {
        if (result.isConfirmed && (userType === 'admin' || userType === 'aa')) showEditForm(group);
    });
});
$(document).on('click', '.payout-row-toggle', function () {
    $(this).closest('.payout-linked-row').toggleClass('is-collapsed');
});
$(document).on('input change', '.payout-row-benes, .payout-row-paid, .payout-row-date', function () {
    const $row = $(this).closest('.payout-linked-row');
    syncLinkedRow($row);
    syncGroupSummary();
});
</script>
</body>
</html>
