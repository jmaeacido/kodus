<?php
  include('../header.php');

  if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
      header("HTTP/1.1 403 Forbidden");
      echo "Access denied. Admins only.";
      exit;
  }

  include('../sidenav.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KODUS | MEB</title>
</head>
<body>
<div class="wrapper">

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">NHTS-PR Validation</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../home">Home</a></li>
              <li class="breadcrumb-item active">MEB</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
              <h3 class="card-title mb-0">Imported Master list of Eligible Beneficiaries</h3>
              <a href="export_meb_validation.php" class="btn btn-success btn-sm mt-2 mt-sm-0">Export to Excel</a>
            </div>
          </div>
          <div class="table-container">
            <div class="alert alert-info m-3 mb-0">
              Validation is based on target versus imported actual counts for the selected fiscal year.
            </div>
            <form id="bulkActionForm" action="bulk_action" method="POST">
              <table id="tableValidation" class="table table-bordered table-striped" style="text-align: center; width: 100%;">
                <thead>
                  <tr>
                    <th>Province</th>
                    <th>Municipality</th>
                    <th>Barangay</th>
                    <th>Target Partner-Beneficiaries</th>
                    <th>Imported Partner-Beneficiaries</th>
                    <th>Variance</th>
                    <th>Validation</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody style="font-size: 10px; white-space: nowrap;"></tbody>
              </table>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $base_url;?>kodus/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script>
$(document).ready(function() {
    if ($("#tableValidation").length) {
        let table = $("#tableValidation").DataTable({
            "responsive": true,
            "processing": false,
            "serverSide": true,
            "ajax": {
                "url": "fetch_data_validation_admin.php",
                "type": "GET",
                "dataSrc": "data"
            },
            "columns": [
                { "data": "province" },
                { "data": "municipality" },
                { "data": "barangay" },
                { "data": "target_beneficiaries" },
                { "data": "actual_beneficiaries" },
                { "data": "variance" },
                { "data": "validation", "orderable": false, "searchable": false },
                { "data": "action", "orderable": false, "searchable": false }
            ],
            "lengthMenu": [[10,25,50,100,-1],[10,25,50,100,"All"]],
            "pageLength": 10
        });

        if (window.KODUSLiveRefresh) {
            window.KODUSLiveRefresh.watchDataTable({
                channels: ['meb_validation_table'],
                table: table
            });
        }
    }
});
</script>
</body>
</html>
