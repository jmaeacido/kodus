<?php
// crossmatch/index.php
// Entry UI: choose mode + threshold + upload files
  include('../header.php');

  // if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
      // header("HTTP/1.1 403 Forbidden");
      // echo "Access denied. Admins only.";
      // exit;
  // }
  
  include('../sidenav.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>KODUS | Crossmatch</title>
  <style>
    /* Center all table headers and cells in #recent-crossmatch-table */
    #recent-crossmatch-table th,
    #recent-crossmatch-table td {
        text-align: center;
        vertical-align: middle; /* optional: center vertically */
    }

    #recent-crossmatch-table th {
      font-size: 14px;
    }
  </style>
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
            <h1 class="m-0">Beneficiary Crossmatching</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="../../home">Home</a></li>
              <li class="breadcrumb-item active">Crossmatching</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
      <div class="container-fluid">
            <div class="card">
              <div class="card-header d-flex align-items-center">
                <h4 class="m-0 flex-grow-1">Beneficiary Crossmatching</h4>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <form id="uploadForm" action="upload_handler.php" method="post" enctype="multipart/form-data">
                  <div class="row g-3">

                    <div class="col-md-4">
                      <label class="form-label">Mode</label><br>
                      <select name="mode" id="mode" class="form-control" style="height:37px;" required>
                        <option value="db_vs_file">KODUS DB vs File</option>
                        <option value="file_vs_file">File vs File</option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Threshold (%)</label><br>
                      <input type="number" name="threshold" class="form-control" min="50" max="100" step="1" value="85" required>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Birthdate rule</label><br>
                      <select name="birthdate_rule" class="form-control">
                        <option value="soft">Soft (fuzzy)</option>
                        <option value="strict">Strict (exact)</option>
                      </select>
                    </div>

                    <div class="col-12"><hr></div>

                    <div class="col-md-6">
                      <label class="form-label">Upload File A (.xlsx or .csv) — required</label>
                      <input type="file" name="file1" class="form-control" accept=".xlsx,.csv" required>
                      <div class="form-text" style="color:orange; font-style: italic;">Headers required: lastName, firstName, middleName, ext, birthDate, barangay, lgu, province</div>
                    </div>

                    <div class="col-md-6" id="file2wrap" style="display:none;">
                      <label class="form-label">Upload File B (.xlsx or .csv) — only for File vs File</label>
                      <input type="file" name="file2" class="form-control" accept=".xlsx,.csv">
                    </div><div style="height: 120px; display:block"></div>

                    <div class="col-12">
                      <button class="btn btn-primary">Upload & Start</button>
                      <a class="btn btn-link" href="helpers/Beneficiaries_Template.xlsx" download>Download Template</a>
                    </div>

                  </div>
                </form>
              </div>
              <!-- /.card-body -->
            </div>
        <!-- /.row -->
            <div class="card">
              <div class="card-header d-flex align-items-center">
                <h4 class="m-0 flex-grow-1">Recent Crossmatchings</h4>
              </div>
              <div class="card-body" id="recent-crossmatch-container" style="height: 46vh; overflow-y: auto;">
                <table class="table table-bordered table-striped" id="recent-crossmatch-table">
                  <thead>
                    <tr>
                      <th>Job</th>
                      <th>Mode</th>
                      <th>Rule</th>
                      <th>Threshold</th>
                      <th>Possible Matches</th>
                      <th>Created at</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Rows will be loaded via AJAX -->
                  </tbody>
                </table>
              </div>
            </div>

      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- AdminLTE App -->
<script src="../dist/js/adminlte.min.js"></script>
<!-- DataTables CSS & JS -->
<link rel="stylesheet" href="../cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="../cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="../cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
document.getElementById('mode').addEventListener('change', function(){
  document.getElementById('file2wrap').style.display = this.value === 'file_vs_file' ? 'block' : 'none';
});
</script>
<script>
let recentCrossmatchTable;

function loadRecentCrossmatchings() {
    $.ajax({
        url: 'fetch_recent_crossmatch.php',
        method: 'GET',
        dataType: 'html',
        success: function(data) {
            if (!recentCrossmatchTable) {
                $('#recent-crossmatch-table tbody').html(data);
                recentCrossmatchTable = $('#recent-crossmatch-table').DataTable({
                    paging: true,
                    pageLength: 10,
                    lengthChange: false,
                    searching: true,
                    info: true,
                    autoWidth: false,
                    order: [[0, 'desc']]
                });
            } else {
                recentCrossmatchTable.clear().draw();
                $('#recent-crossmatch-table tbody').fadeOut(200, function() {
                    $(this).html(data).fadeIn(400, function() {
                        recentCrossmatchTable.rows.add($('#recent-crossmatch-table tbody tr')).draw(false);
                    });
                });
            }
        },
        error: function() {
            console.error('Failed to load recent crossmatchings.');
        }
    });
}

// Initial load
loadRecentCrossmatchings();

if (window.KODUSLiveRefresh) {
    window.KODUSLiveRefresh.watch({
        channels: ['crossmatch_recent_table'],
        onChange: loadRecentCrossmatchings
    });
}
</script>
</body>
</html>
