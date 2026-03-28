<?php
    include('../header.php');
    include('../sidenav.php');

    // Ensure a fiscal year is selected
    if (!isset($_SESSION['selected_year'])) {
        echo "<p style='color:red;'>Fiscal year not selected. Please go back and select a year.</p>";
        exit;
    }

    $year = (int) $_SESSION['selected_year'];
    $userType = $_SESSION['user_type'] ?? 'user';

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
    $totalAmountPaid = $totalPaid * 7700;
    $totalAmountUnpaid = $totalUnpaid * 7700;

    $stmt->close();
?>

<script>
const userType = '<?php echo $userType; ?>';
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
data-amount="<?= number_format($row['amount'], 2); ?>"
data-paid="<?= $row['paid']; ?>"
data-unpaid="<?= $row['unpaid']; ?>"
data-date="<?= !empty($row['payoutDate']) ? date("F d, Y", strtotime($row['payoutDate'])) : ''; ?>"
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
<td><?= number_format($row['paid'] * 7700, 2); ?></td>
<td><?= $row['unpaid']; ?></td>
<td><?= number_format($row['unpaid'] * 7700, 2); ?></td>
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
    const benes = $(this).data("benes");
    const amount = $(this).data("amount");
    const paid = $(this).data("paid");
    const unpaid = $(this).data("unpaid");
    const payoutDate = $(this).data("date");
    const formatCurrency = (value) => `&#8369;${Number(String(value).replace(/,/g, '')).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
    const paidAmount = paid * 7700;
    const unpaidAmount = unpaid * 7700;

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
                    <div class="kodus-detail-pill">${payoutDate || 'No payout date set'}</div>
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
                    <tr><th>Payout Date</th><td>${payoutDate || 'No payout date set'}</td></tr>
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
            showEditForm(id, province, lgu, barangay, benes, amount, paid, payoutDate);
        }
    });
});
</script>
<script>
  function showEditForm(id, province, lgu, barangay, benes, amount, paid, payoutDate) {
    Swal.fire({
        title: 'Edit Payout Details',
        customClass: {
            popup: 'kodus-edit-popup'
        },
        html: `
            <form id="editForm" class="kodus-edit-shell">
              <div class="kodus-edit-header">
                <h3 class="kodus-edit-header-title">${barangay}, ${lgu}</h3>
                <p class="kodus-edit-header-note">Adjust the payout location, beneficiary count, and payment totals for this record.</p>
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
                    <input id="edit-benes" type="number" class="form-control" value="${benes}">
                  </div>
                  <div class="kodus-edit-field">
                    <label>Amount</label>
                    <input id="edit-amount" type="number" step="0.01" class="form-control" value="${parseFloat(amount.toString().replace(/,/g, ''))}">
                  </div>
                  <div class="kodus-edit-field">
                    <label>Paid</label>
                    <input id="edit-paid" type="number" class="form-control" value="${paid}">
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
        preConfirm: () => {
            // Collect values manually
            const payload = {
                id: id,
                province: $('#edit-province').val(),
                lgu: $('#edit-lgu').val(),
                barangay: $('#edit-barangay').val(),
                benesNumber: $('#edit-benes').val(),
                amount: $('#edit-amount').val(),
                paid: $('#edit-paid').val(),
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
