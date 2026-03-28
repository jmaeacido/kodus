<?php
    include('../header.php');
    include('../sidenav.php');
    require_once __DIR__ . '/../project_variable_helpers.php';

    // Ensure a fiscal year is selected
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

    // Use prepared statement (safer)
    $stmt = $conn->prepare("
        SELECT id, province, lgu, barangay, benesNumber, amount, paid, payoutDate
        FROM breakdown
        WHERE YEAR(payoutDate) = ?
        ORDER BY province, lgu, barangay
    ");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();

    // Initialize totals (VERY IMPORTANT)
    $rows = [];
    $totalBenes = 0;
    $totalAmount = 0;
    $totalPaid = 0;
    $totalUnpaid = 0;
    $totalAmountPaid = 0;
    $totalAmountUnpaid = 0;

    // Fetch rows
    while ($row = $result->fetch_assoc()) {

        $row['benesNumber'] = (int)$row['benesNumber'];
        $row['paid'] = (int)$row['paid'];
        $row['amount'] = (float)$row['amount'];

        $row['unpaid'] = $row['benesNumber'] - $row['paid'];

        $totalBenes += $row['benesNumber'];
        $totalAmount += $row['amount'];
        $totalPaid += $row['paid'];
        $totalUnpaid += $row['unpaid'];

        $rows[] = $row;
    }

    // Calculate final totals AFTER loop
    $totalAmountPaid = $totalPaid * $beneficiaryPayoutRate;
    $totalAmountUnpaid = $totalUnpaid * $beneficiaryPayoutRate;

    $stmt->close();
?>

<script>
const userType = '<?php echo $userType; ?>';
const selectedYear = <?php echo json_encode($year); ?>;
const dailyWageRate = <?php echo json_encode($dailyWageRate); ?>;
const payoutDays = <?php echo json_encode($payoutDays); ?>;
const beneficiaryPayoutRate = <?php echo json_encode($beneficiaryPayoutRate); ?>;
</script>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payout</title>
</head>

<body>
<div class="wrapper">

<!-- Preloader -->

<div class="content-wrapper">
<div class="content">
<div class="container-fluid">
<div class="row">
<div class="col-lg-12">

<div class="card card-primary card-outline">
<div class="card-header d-flex align-items-center">
<h5 class="m-0 flex-grow-1">Payout Details</h5>
<button id="exportBtn" class="btn btn-info btn-sm">Export to Excel</button>
</div>

<div class="card-body">
<?php if ($dailyWageRate <= 0): ?>
  <div class="alert alert-warning">
    No project variable is configured yet for <strong>daily_wage_rate</strong> in fiscal year <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>.
    Ask an administrator to update <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/admin/project_variables">Project Variables</a>.
  </div>
<?php endif; ?>
<?php if ($payoutDays <= 0): ?>
  <div class="alert alert-warning">
    No project variable is configured yet for <strong>working_days</strong> in fiscal year <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>.
    Ask an administrator to update <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>kodus/admin/project_variables">Project Variables</a>.
  </div>
<?php endif; ?>
<div class="table-container">

<table id="sectoralTable" class="table table-bordered table-striped" style="text-align:center; width:100%;">
<thead>
<tr>
<th>Action</th>
<th>Province</th>
<th>City or Municipality</th>
<th>Barangay</th>
<th>No. of Partner-Beneficiaries</th>
<th>Amount</th>
<th>Payout Date</th>
<th>Paid</th>
<th>Amount Paid</th>
<th>Unpaid</th>
<th>Amount Unpaid</th>
</tr>

<tr style="font-weight:bold;">
<td colspan="4">Total</td>
<td><?= number_format($totalBenes) ?></td>
<td><?= number_format($totalAmount, 2) ?></td>
<td></td>
<td><?= number_format($totalPaid) ?></td>
<td><?= number_format($totalAmountPaid, 2) ?></td>
<td><?= number_format($totalUnpaid) ?></td>
<td><?= number_format($totalAmountUnpaid, 2) ?></td>
</tr>
</thead>

<tbody style="font-size:12px;">
<?php foreach ($rows as $row): ?>
<tr>
<td>
<span class="kodus-row-actions"><button 
class="btn btn-info btn-sm details-btn"
data-id="<?= $row['id']; ?>"
data-province="<?= htmlspecialchars($row['province']); ?>"
data-lgu="<?= htmlspecialchars($row['lgu']); ?>"
data-barangay="<?= htmlspecialchars($row['barangay']); ?>"
data-benes="<?= $row['benesNumber']; ?>"
data-amount="<?= htmlspecialchars((string) $row['amount'], ENT_QUOTES, 'UTF-8'); ?>"
data-paid="<?= $row['paid']; ?>"
data-unpaid="<?= $row['unpaid']; ?>"
data-date-display="<?= !empty($row['payoutDate']) ? date("F d, Y", strtotime($row['payoutDate'])) : ''; ?>"
data-date-iso="<?= !empty($row['payoutDate']) ? htmlspecialchars($row['payoutDate'], ENT_QUOTES, 'UTF-8') : ''; ?>"
>
<i class="nav-icon fas fa-eye" aria-hidden="true"></i>
</button></span>
</td>

<td><?= htmlspecialchars($row['province']); ?></td>
<td><?= htmlspecialchars($row['lgu']); ?></td>
<td><?= htmlspecialchars($row['barangay']); ?></td>
<td><?= $row['benesNumber']; ?></td>
<td><?= number_format($row['amount'], 2); ?></td>
<td><?= !empty($row['payoutDate']) ? date("F d, Y", strtotime($row['payoutDate'])) : ''; ?></td>
<td><?= $row['paid']; ?></td>
<td><?= number_format($row['paid'] * $beneficiaryPayoutRate, 2); ?></td>
<td><?= $row['unpaid']; ?></td>
<td><?= number_format($row['unpaid'] * $beneficiaryPayoutRate, 2); ?></td>
</tr>
<?php endforeach; ?>
</tbody>

</table>
</div>
</div>
</div>

</div>
</div>
</div>
</div>
</div>

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="<?php echo $base_url;?>kodus/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo $base_url;?>kodus/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
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
<!-- AdminLTE App -->
<script src="<?php echo $base_url;?>kodus/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function () {
    $('#sectoralTable').DataTable({
        responsive: true,
        autoWidth: false,
        ordering: false,
        lengthMenu: [[10,25,50,100,200,-1], [10,25,50,100,200,"All"]],
    });
});
</script>

<script>
  document.getElementById('exportBtn').addEventListener('click', function () {
      window.location.href = '../pages/payout_export';
});
</script>
<script>
$(document).on("click", ".details-btn", function () {
    const id = $(this).data("id");
    const province = $(this).data("province");
    const lgu = $(this).data("lgu");
    const barangay = $(this).data("barangay");
    const benes = Number($(this).data("benes")) || 0;
    const amount = Number($(this).data("amount")) || 0;
    const paid = Number($(this).data("paid")) || 0;
    const unpaid = Number($(this).data("unpaid")) || 0;
    const payoutDateDisplay = $(this).data("date-display");
    const payoutDateIso = $(this).data("date-iso");
    const formatCurrency = (value) => `&#8369;${Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
    const paidAmount = paid * beneficiaryPayoutRate;
    const unpaidAmount = unpaid * beneficiaryPayoutRate;

    Swal.fire({
        title: 'Payout Breakdown Details',
        customClass: {
            popup: 'kodus-detail-popup'
        },
        html: `
            <div class="kodus-detail-modal">
                <div class="kodus-detail-hero">
                    <div>
                        <p class="kodus-detail-eyebrow">Payout Location</p>
                        <h3 class="kodus-detail-title">${barangay}, ${lgu}</h3>
                        <p class="kodus-detail-subtitle">${province}</p>
                    </div>
                    <div class="kodus-detail-pill">${payoutDateDisplay || 'No payout date set'}</div>
                </div>

                <div class="kodus-detail-grid">
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Beneficiaries</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${Number(benes).toLocaleString()}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Total Amount</span>
                        <span class="kodus-detail-value kodus-detail-value--strong">${formatCurrency(amount)}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Paid</span>
                        <span class="kodus-detail-value kodus-detail-value--strong kodus-detail-value--positive">${Number(paid).toLocaleString()}</span>
                    </div>
                    <div class="kodus-detail-stat">
                        <span class="kodus-detail-label">Unpaid</span>
                        <span class="kodus-detail-value kodus-detail-value--strong kodus-detail-value--warning">${Number(unpaid).toLocaleString()}</span>
                    </div>
                </div>

                <table class="kodus-detail-table">
                    <tr><th>Province</th><td>${province}</td></tr>
                    <tr><th>City / Municipality</th><td>${lgu}</td></tr>
                    <tr><th>Barangay</th><td>${barangay}</td></tr>
                    <tr><th>No. of Partner-Beneficiaries</th><td>${Number(benes).toLocaleString()}</td></tr>
                    <tr><th>Total Amount</th><td>${formatCurrency(amount)}</td></tr>
                    <tr><th>Paid Amount</th><td>${Number(paid).toLocaleString()} beneficiaries (${formatCurrency(paidAmount)})</td></tr>
                    <tr><th>Unpaid Amount</th><td>${Number(unpaid).toLocaleString()} beneficiaries (${formatCurrency(unpaidAmount)})</td></tr>
                    <tr><th>Computation Rule</th><td>${formatCurrency(dailyWageRate)} x ${Number(payoutDays).toLocaleString()} days</td></tr>
                    <tr><th>Payout Date</th><td>${payoutDateDisplay || 'No payout date set'}</td></tr>
                </table>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        showConfirmButton: userType === 'admin' || userType === 'aa',
        confirmButtonText: '<i class="fas fa-pen"></i>',
        cancelButtonText: '<i class="fas fa-times"></i>'
    }).then((result) => {
        if (result.isConfirmed && (userType === 'admin' || userType === 'aa')) {
            showEditForm(id, province, lgu, barangay, benes, amount, paid, payoutDateIso);
        }
    });
});
</script>
<script>
  function showEditForm(id, province, lgu, barangay, benes, amount, paid, payoutDate) {
    const safeAmount = Number(amount) || 0;
    Swal.fire({
        title: 'Edit Payout Details',
        customClass: {
            popup: 'kodus-edit-popup'
        },
        html: `
            <form id="editForm" class="kodus-edit-shell">
              <div class="kodus-edit-header">
                <h3 class="kodus-edit-header-title">${barangay}, ${lgu}</h3>
                <p class="kodus-edit-header-note">Adjust the payout location and counts. Totals are computed automatically for ${selectedYear} using project variables: &#8369;${dailyWageRate.toFixed(2)} for ${payoutDays} days.</p>
              </div>

              <div class="kodus-edit-section">
                <h6 class="kodus-edit-section-title">Coverage</h6>
                <div class="kodus-edit-grid">
                  <div class="kodus-edit-field">
                    <label>Province</label>
                    <input id="edit-province" type="text" class="form-control" value="${province}">
                  </div>
                  <div class="kodus-edit-field">
                    <label>City / Municipality</label>
                    <input id="edit-lgu" type="text" class="form-control" value="${lgu}">
                  </div>
                  <div class="kodus-edit-field">
                    <label>Barangay</label>
                    <input id="edit-barangay" type="text" class="form-control" value="${barangay}">
                  </div>
                  <div class="kodus-edit-field">
                    <label>Payout Date</label>
                    <input id="edit-date" type="date" class="form-control" value="${payoutDate ? new Date(payoutDate).toISOString().split('T')[0] : ''}">
                  </div>
                </div>
              </div>

              <div class="kodus-edit-section">
                <h6 class="kodus-edit-section-title">Counts and Amounts</h6>
                <div class="kodus-edit-grid kodus-edit-grid--compact">
                  <div class="kodus-edit-field">
                    <label>No. of Partner-Beneficiaries</label>
                    <input id="edit-benes" type="number" min="0" class="form-control" value="${benes}">
                  </div>
                  <div class="kodus-edit-field">
                    <label>Amount (Auto-calculated)</label>
                    <input id="edit-amount" type="number" step="0.01" class="form-control" value="${safeAmount.toFixed(2)}" readonly>
                  </div>
                  <div class="kodus-edit-field">
                    <label>Paid</label>
                    <input id="edit-paid" type="number" min="0" class="form-control" value="${paid}">
                  </div>
                  <div class="kodus-edit-field">
                    <label>Unpaid (Auto-calculated)</label>
                    <input id="edit-unpaid" type="number" class="form-control" value="${Math.max(Number(benes) - Number(paid), 0)}" readonly>
                  </div>
                </div>
              </div>

              <div class="kodus-edit-section">
                <h6 class="kodus-edit-section-title">Calculated Summary</h6>
                <div class="kodus-detail-grid">
                  <div class="kodus-detail-stat">
                    <span class="kodus-detail-label">Daily Wage Rate</span>
                    <span class="kodus-detail-value kodus-detail-value--strong">&#8369;${dailyWageRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                  </div>
                  <div class="kodus-detail-stat">
                    <span class="kodus-detail-label">Payout Days</span>
                    <span class="kodus-detail-value kodus-detail-value--strong">${Number(payoutDays).toLocaleString()}</span>
                  </div>
                  <div class="kodus-detail-stat">
                    <span class="kodus-detail-label">Amount Paid</span>
                    <span id="edit-amount-paid" class="kodus-detail-value kodus-detail-value--strong kodus-detail-value--positive">&#8369;0.00</span>
                  </div>
                  <div class="kodus-detail-stat">
                    <span class="kodus-detail-label">Amount Unpaid</span>
                    <span id="edit-amount-unpaid" class="kodus-detail-value kodus-detail-value--strong kodus-detail-value--warning">&#8369;0.00</span>
                  </div>
                  <div class="kodus-edit-field" style="grid-column: 1 / -1;">
                    <small id="edit-calculation-note" class="text-muted d-block">Amounts update automatically from the beneficiary and paid counts.</small>
                  </div>
                </div>
              </div>
            </form>
        `,
        icon: "warning",
        width: 600,
        showCancelButton: true,
        focusConfirm: false,
        confirmButtonText: '<i class="fas fa-save"></i>',
        cancelButtonText: '<i class="fas fa-times"></i>',
        didOpen: () => {
            const benesInput = document.getElementById('edit-benes');
            const paidInput = document.getElementById('edit-paid');
            const amountInput = document.getElementById('edit-amount');
            const unpaidInput = document.getElementById('edit-unpaid');
            const amountPaidNode = document.getElementById('edit-amount-paid');
            const amountUnpaidNode = document.getElementById('edit-amount-unpaid');
            const noteNode = document.getElementById('edit-calculation-note');

            function normalizeWholeNumber(value) {
                const parsed = parseInt(value, 10);
                return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
            }

            function syncComputedFields() {
                const benesValue = normalizeWholeNumber(benesInput.value);
                let paidValue = normalizeWholeNumber(paidInput.value);

                if (paidValue > benesValue) {
                    paidValue = benesValue;
                    paidInput.value = String(paidValue);
                }

                const unpaidValue = Math.max(benesValue - paidValue, 0);
                const totalAmount = benesValue * beneficiaryPayoutRate;
                const paidAmount = paidValue * beneficiaryPayoutRate;
                const unpaidAmount = unpaidValue * beneficiaryPayoutRate;

                amountInput.value = totalAmount.toFixed(2);
                unpaidInput.value = String(unpaidValue);
                amountPaidNode.innerHTML = `&#8369;${paidAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                amountUnpaidNode.innerHTML = `&#8369;${unpaidAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

                if (benesValue === 0) {
                    noteNode.textContent = `Enter the number of beneficiaries to calculate the ${selectedYear} payout totals.`;
                } else {
                    noteNode.textContent = `${benesValue.toLocaleString()} beneficiaries x PHP ${dailyWageRate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} x ${Number(payoutDays).toLocaleString()} days = PHP ${totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`;
                }
            }

            benesInput.addEventListener('input', syncComputedFields);
            paidInput.addEventListener('input', syncComputedFields);
            syncComputedFields();
        },
        preConfirm: () => {
            const provinceValue = $('#edit-province').val().trim();
            const lguValue = $('#edit-lgu').val().trim();
            const barangayValue = $('#edit-barangay').val().trim();
            const benesValue = Number.parseInt($('#edit-benes').val(), 10);
            const paidValue = Number.parseInt($('#edit-paid').val(), 10);
            const amountValue = Number.parseFloat($('#edit-amount').val());

            if (!provinceValue || !lguValue || !barangayValue) {
                Swal.showValidationMessage('Province, city / municipality, and barangay are required.');
                return false;
            }

            if (!Number.isFinite(benesValue) || benesValue < 0) {
                Swal.showValidationMessage('No. of Partner-Beneficiaries must be 0 or greater.');
                return false;
            }

            if (!Number.isFinite(paidValue) || paidValue < 0) {
                Swal.showValidationMessage('Paid must be 0 or greater.');
                return false;
            }

            if (paidValue > benesValue) {
                Swal.showValidationMessage('Paid cannot be greater than the total number of beneficiaries.');
                return false;
            }

            const payload = {
                id: id,
                province: provinceValue,
                lgu: lguValue,
                barangay: barangayValue,
                benesNumber: benesValue,
                amount: amountValue,
                paid: paidValue,
                payoutDate: $('#edit-date').val()
            };

            // Send using jQuery.ajax
            return $.ajax({
                url: 'update_payout.php',
                type: 'POST',
                data: payload, // No FormData
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire("Success!", "Record updated successfully.", "success").then(() => {
                            if (typeof table !== 'undefined') {
                                table.ajax.reload(); // Reload table if DataTable is used
                            } else {
                                location.reload(); // Fallback
                            }
                        });
                    } else {
                        Swal.fire("Error!", response.message || "Failed to update the record.", "error");
                    }
                },
                error: function () {
                    Swal.fire("Error!", "Something went wrong. Please try again.", "error");
                }
            });
        }
    });
}
</script>

</body>
</html>
