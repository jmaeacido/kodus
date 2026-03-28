<?php
  include('../header.php');
  include('../sidenav.php');

  $query = "
    SELECT 
        province, lgu, barangay, benesNumber, amount, paid
    FROM breakdown 
    ORDER BY province, lgu, barangay;
  ";

  $result = mysqli_query($conn, $query);
?>

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

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Starter Page</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Starter Page</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-12">

            <div class="card card-primary card-outline">
              <div class="card-header d-flex align-items-center">
                <h5 class="m-0 flex-grow-1">Summary of Partner-Beneficiaries per Sector</h5>
                <button id="exportBtn" class="btn btn-info btn-sm" style="font-size: 10px; width: auto;">Export to Excel</button>
              </div>
              <div class="card-body">
                <div class="table-container">
                  <table id="sectoralTable" class="table table-bordered table-striped" style="text-align: center; width: 100%; table-layout: auto;">
                    <thead>
  <tr>
    <th>Province</th>
    <th>City or Municipality</th>
    <th>Barangay</th>
    <th>No. of Partner-Beneficiaries</th>
    <th>Amount</th>
    <th>Paid</th>
    <th>Amount Paid</th>
    <th>Unpaid</th>
    <th>Amount Unpaid</th>
  </tr>

  <?php
    $rows = [];
    $totalBenes = $totalAmount = $totalPaid = $totalUnpaid = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $row['unpaid'] = $row['benesNumber'] - $row['paid'];

        $totalBenes += $row['benesNumber'];
        $totalAmount += $row['amount'];
        $totalPaid += $row['paid'];
        $totalAmountPaid = $totalPaid * 7700;
        $totalUnpaid += $row['unpaid'];
        $totalAmountUnpaid = $totalUnpaid * 7700;

        $rows[] = $row;
    }
  ?>

  <tr style="font-weight: bold; font-size: 12px;">
    <td colspan="3">Total</td>
    <td><?php echo number_format($totalBenes); ?></td>
    <td><?php echo number_format($totalAmount, 2); ?></td>
    <td><?php echo number_format($totalPaid); ?></td>
    <td><?php echo number_format($totalAmountPaid, 2); ?></td>
    <td><?php echo number_format($totalUnpaid); ?></td>
    <td><?php echo number_format($totalAmountUnpaid, 2); ?></td>
  </tr>
</thead>

<tbody style="font-size: 10px;">
  <?php foreach ($rows as $row) { ?>
    <tr>
      <td style="white-space: nowrap;"><?php echo $row['province']; ?></td>
      <td><?php echo $row['lgu']; ?></td>
      <td><?php echo $row['barangay']; ?></td>
      <td><?php echo $row['benesNumber']; ?></td>
      <td><?php echo number_format($row['amount'], 2); ?></td>
      <td><?php echo $row['paid']; ?></td>
      <td><?php echo number_format($row['paid'] * 7700, 2); ?></td>
      <td><?php echo $row['unpaid']; ?></td>
      <td><?php echo number_format($row['unpaid'] * 7700, 2); ?></td>
    </tr>
  <?php } ?>
</tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
    <div class="p-3">
      <h5>Title</h5>
      <p>Sidebar content</p>
    </div>
  </aside>
  <!-- /.control-sidebar -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">
      Anything you want
    </div>
    <!-- Default to the left -->
    <strong>Copyright &copy; 2014-2021 <a href="https://adminlte.io">AdminLTE.io</a>.</strong> All rights reserved.
  </footer>
</div>
<!-- ./wrapper -->

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
          "lengthMenu": [[10,25,50,100,200,300,-1], [10,25,50,100,200,300,"All"]],
      });
  });
</script>
<script>
  document.getElementById('exportBtn').addEventListener('click', function () {
      window.location.href = 'export';
});
</script>
</body>
</html>
